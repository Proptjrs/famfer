<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Produit;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Les actions d'écriture que rien n'éprouvait dans le bon sens.
 *
 * Plusieurs boutons n'étaient testés que par leur refus : on vérifiait qu'un
 * intrus ne pouvait pas publier un produit, jamais que le vendeur légitime le
 * pouvait. Un refus qui fonctionne sur une action cassée passe pour un succès.
 */
class ActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $vendeur;
    private Boutique $boutique;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);

        $this->admin = User::create([
            'name' => 'Administration', 'email' => 'admin@essai.sn',
            'password' => 'motdepasse', 'role' => 'admin', 'telephone' => '+221 33 800 00 00',
        ]);

        $this->boutique = Boutique::where('statut', 'active')->orderBy('id')->firstOrFail();
        $this->vendeur = $this->boutique->utilisateur;
    }

    // ── Publier et modifier un produit ───────────────────────────────────────

    public function test_le_vendeur_publie_un_produit(): void
    {
        $categorie = Categorie::whereNotNull('parente_id')->orderBy('id')->firstOrFail();

        $this->actingAs($this->vendeur)->post(route('vendeur.produit.publier'), [
            'categorie_id' => $categorie->id,
            'nom' => 'Fer à béton HA T40 — barre de 12 m',
            'description' => 'Une barre bien épaisse.',
            'marque' => 'SENIRON',
            'prix' => 48_000,
            'prix_barre' => 55_000,
            'stock' => 12,
            'dessin' => 'rond-strie',
        ])->assertRedirect(route('vendeur.produits'));

        $p = Produit::where('nom', 'Fer à béton HA T40 — barre de 12 m')->firstOrFail();

        $this->assertSame($this->boutique->id, $p->boutique_id);
        $this->assertSame(48_000, $p->prix);
        $this->assertSame(13, $p->remise());
        $this->assertTrue($p->actif);
        // Le slug est fabriqué, pas saisi : deux produits du même nom coexistent.
        $this->assertNotEmpty($p->slug);
    }

    /**
     * Un prix barré inférieur au prix de vente est refusé.
     *
     * Sans ce contrôle, la remise affichée serait négative — ou nulle, et donc
     * mensongère. C'est la tricherie la plus facile d'une place de marché.
     */
    public function test_un_prix_barre_incoherent_est_refuse(): void
    {
        $categorie = Categorie::whereNotNull('parente_id')->orderBy('id')->firstOrFail();

        $this->actingAs($this->vendeur)->post(route('vendeur.produit.publier'), [
            'categorie_id' => $categorie->id, 'nom' => 'Fausse promotion',
            'prix' => 10_000, 'prix_barre' => 8_000, 'stock' => 5,
        ])->assertSessionHasErrors('prix_barre');

        $this->assertNull(Produit::firstWhere('nom', 'Fausse promotion'));
    }

    public function test_le_vendeur_modifie_son_produit(): void
    {
        $p = Produit::where('boutique_id', $this->boutique->id)->orderBy('id')->firstOrFail();

        $this->actingAs($this->vendeur)->put(route('vendeur.produit.modifier', $p), [
            'categorie_id' => $p->categorie_id,
            'nom' => $p->nom,
            'prix' => 7_777,
            'stock' => 3,
        ])->assertRedirect();

        $this->assertSame(7_777, $p->fresh()->prix);
        $this->assertSame(3, $p->fresh()->stock);
    }

    public function test_le_vendeur_retire_puis_remet_un_produit(): void
    {
        $p = Produit::where('boutique_id', $this->boutique->id)
            ->where('actif', true)->orderBy('id')->firstOrFail();

        $this->actingAs($this->vendeur)->post(route('vendeur.produit.bascule', $p));
        $this->assertFalse($p->fresh()->actif);

        // Retiré, il disparaît du catalogue mais n'est pas effacé.
        $this->get(route('produit', $p))->assertNotFound();

        $this->actingAs($this->vendeur)->post(route('vendeur.produit.bascule', $p->fresh()));
        $this->assertTrue($p->fresh()->actif);
    }

    public function test_le_vendeur_met_a_jour_sa_vitrine(): void
    {
        $this->actingAs($this->vendeur)->put(route('vendeur.boutique.maj'), [
            'description' => 'Trente ans de métier au marché de Pikine.',
            'telephone' => '+221 78 111 22 33',
            'adresse' => 'Rue 12, angle 5',
            'ville' => 'Pikine',
        ])->assertRedirect();

        $b = $this->boutique->fresh();
        $this->assertSame('Trente ans de métier au marché de Pikine.', $b->description);
        $this->assertSame('+221 78 111 22 33', $b->telephone);

        // Et le client le voit sur la vitrine.
        $this->get(route('boutique', $b))->assertOk()
            ->assertSee('Trente ans de métier au marché de Pikine.');
    }

    // ── L'administration, dans le sens qui marche ────────────────────────────

    public function test_ladministration_valide_une_boutique(): void
    {
        $enAttente = Boutique::where('statut', 'en_attente')->orderBy('id')->firstOrFail();
        $unProduit = Produit::where('boutique_id', $enAttente->id)
            ->where('actif', true)->where('stock', '>', 0)->orderBy('id')->first();

        // Avant : ni la vitrine ni ses produits ne sont visibles.
        $this->get(route('boutique', $enAttente))->assertNotFound();
        if ($unProduit) {
            $this->get(route('produit', $unProduit))->assertNotFound();
        }

        $this->actingAs($this->admin)
            ->post(route('admin.activer', $enAttente))->assertRedirect();

        $this->assertSame('active', $enAttente->fresh()->statut);
        $this->get(route('boutique', $enAttente->fresh()))->assertOk();
        if ($unProduit) {
            $this->get(route('produit', $unProduit->fresh()))->assertOk();
        }
    }

    public function test_ladministration_suspend_une_boutique(): void
    {
        $unProduit = Produit::where('boutique_id', $this->boutique->id)
            ->where('actif', true)->where('stock', '>', 0)->orderBy('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.suspendre', $this->boutique), [
                'motif' => 'Marchandise non conforme signalée par trois clients.',
            ])->assertRedirect();

        $b = $this->boutique->fresh();
        $this->assertSame('suspendue', $b->statut);
        $this->assertStringContainsString('non conforme', $b->motif_suspension);

        // Ses produits quittent le catalogue avec elle.
        $this->get(route('produit', $unProduit->fresh()))->assertNotFound();
        $this->get(route('boutique', $b))->assertNotFound();
    }

    public function test_la_suspension_exige_un_motif(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.suspendre', $this->boutique), ['motif' => ''])
            ->assertSessionHasErrors('motif');

        $this->assertSame('active', $this->boutique->fresh()->statut);
    }

    public function test_ladministration_met_une_boutique_en_avant(): void
    {
        $avant = $this->boutique->officielle;

        $this->actingAs($this->admin)
            ->post(route('admin.officielle', $this->boutique))->assertRedirect();

        $this->assertSame(! $avant, $this->boutique->fresh()->officielle);
    }

    // ── Le compte ────────────────────────────────────────────────────────────

    public function test_le_compte_se_corrige(): void
    {
        $this->actingAs($this->vendeur)->put(route('compte.maj'), [
            'name' => 'Amadou NDIAYE',
            'email' => 'amadou@essai.sn',
            'telephone' => '+221 76 555 44 33',
        ])->assertRedirect();

        $u = $this->vendeur->fresh();
        $this->assertSame('Amadou NDIAYE', $u->name);
        $this->assertSame('amadou@essai.sn', $u->email);
    }

    public function test_on_ne_prend_pas_ladresse_dun_autre(): void
    {
        $this->actingAs($this->vendeur)->put(route('compte.maj'), [
            'name' => 'Amadou', 'email' => $this->admin->email,
            'telephone' => '+221 76 555 44 33',
        ])->assertSessionHasErrors('email');

        $this->assertNotSame($this->admin->email, $this->vendeur->fresh()->email);
    }

    /** L'ancien mot de passe est exigé : une session ouverte ne suffit pas. */
    public function test_changer_de_mot_de_passe_exige_lancien(): void
    {
        $this->actingAs($this->vendeur)->put(route('compte.mdp'), [
            'actuel' => 'ce-nest-pas-le-bon',
            'password' => 'un-nouveau-mot-de-passe',
            'password_confirmation' => 'un-nouveau-mot-de-passe',
        ])->assertSessionHas('erreur');

        $this->assertTrue(Hash::check('password', $this->vendeur->fresh()->password));
    }

    public function test_le_mot_de_passe_se_change(): void
    {
        $this->actingAs($this->vendeur)->put(route('compte.mdp'), [
            'actuel' => 'password',
            'password' => 'un-nouveau-mot-de-passe',
            'password_confirmation' => 'un-nouveau-mot-de-passe',
        ])->assertSessionHas('ok');

        $this->assertTrue(Hash::check('un-nouveau-mot-de-passe', $this->vendeur->fresh()->password));
    }

    // ── Le mot de passe oublié ───────────────────────────────────────────────

    public function test_le_lien_de_reinitialisation_part(): void
    {
        Notification::fake();

        $this->post(route('mdp.oubli'), ['email' => $this->admin->email])
            ->assertSessionHas('ok');

        Notification::assertSentTo($this->admin, ResetPassword::class);
    }

    /**
     * Une adresse inconnue reçoit la même réponse.
     *
     * Répondre « ce compte n'existe pas » permettrait d'énumérer les inscrits.
     */
    public function test_une_adresse_inconnue_recoit_la_meme_reponse(): void
    {
        Notification::fake();

        $this->post(route('mdp.oubli'), ['email' => 'personne@nulle.part'])
            ->assertSessionHas('ok');

        Notification::assertNothingSent();
    }

    public function test_le_jeton_permet_de_choisir_un_nouveau_mot_de_passe(): void
    {
        $jeton = Password::createToken($this->admin);

        $this->post(route('mdp.reinitialiser'), [
            'token' => $jeton, 'email' => $this->admin->email,
            'password' => 'tout-a-fait-nouveau', 'password_confirmation' => 'tout-a-fait-nouveau',
        ])->assertRedirect(route('connexion'));

        $this->assertTrue(Hash::check('tout-a-fait-nouveau', $this->admin->fresh()->password));
    }

    public function test_un_jeton_invente_est_refuse(): void
    {
        $this->post(route('mdp.reinitialiser'), [
            'token' => 'jeton-fabrique-de-toutes-pieces', 'email' => $this->admin->email,
            'password' => 'tentative-de-vol', 'password_confirmation' => 'tentative-de-vol',
        ])->assertSessionHas('erreur');

        $this->assertTrue(Hash::check('motdepasse', $this->admin->fresh()->password));
    }

    public function test_le_jeton_ne_sert_quune_fois(): void
    {
        $jeton = Password::createToken($this->admin);

        $this->post(route('mdp.reinitialiser'), [
            'token' => $jeton, 'email' => $this->admin->email,
            'password' => 'premier-changement', 'password_confirmation' => 'premier-changement',
        ])->assertRedirect(route('connexion'));

        $this->post(route('mdp.reinitialiser'), [
            'token' => $jeton, 'email' => $this->admin->email,
            'password' => 'second-changement', 'password_confirmation' => 'second-changement',
        ])->assertSessionHas('erreur');

        $this->assertTrue(Hash::check('premier-changement', $this->admin->fresh()->password));
    }

    // ── Sortir ───────────────────────────────────────────────────────────────

    public function test_on_se_deconnecte(): void
    {
        $this->actingAs($this->vendeur)->post(route('deconnexion'))
            ->assertRedirect(route('accueil'));

        $this->assertGuest();
    }
}
