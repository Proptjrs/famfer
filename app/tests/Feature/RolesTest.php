<?php

namespace Tests\Feature;

use App\Models\Adresse;
use App\Models\Boutique;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use App\Services\Panier;
use App\Services\PasseCommande;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Qui a le droit de quoi.
 *
 * Trois rôles : client, vendeur, administration. Ce fichier existe pour que
 * les frontières ne bougent pas par accident — chaque porte est poussée
 * séparément, parce qu'une seule oubliée suffirait.
 *
 * La plus dangereuse est celle de l'administration : sans garde-fou, tout
 * compte connecté pourrait valider sa propre boutique et se mettre en avant
 * au catalogue.
 */
class RolesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;
    private Boutique $boutique;
    private User $vendeur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);

        $this->admin = User::create([
            'name' => 'Administration', 'email' => 'admin@essai.sn',
            'password' => 'motdepasse', 'role' => 'admin', 'telephone' => '+221 33 800 00 00',
        ]);
        $this->client = User::create([
            'name' => 'Awa BA', 'email' => 'awa@essai.sn',
            'password' => 'motdepasse', 'role' => 'client', 'telephone' => '+221 77 000 00 00',
        ]);

        $this->boutique = Boutique::where('statut', 'active')->orderBy('id')->firstOrFail();
        $this->vendeur = $this->boutique->utilisateur;
    }

    // ── L'administration ─────────────────────────────────────────────────────

    public static function portesAdmin(): array
    {
        return [
            'tableau de bord' => ['get', 'admin.tableau', false],
            'liste des boutiques' => ['get', 'admin.boutiques', false],
            'liste des commandes' => ['get', 'admin.commandes', false],
            'activer une boutique' => ['post', 'admin.activer', true],
            'suspendre une boutique' => ['post', 'admin.suspendre', true],
            'mettre en avant' => ['post', 'admin.officielle', true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('portesAdmin')]
    public function test_ladministration_refuse_un_compte_ordinaire(
        string $verbe, string $route, bool $avecBoutique
    ): void {
        $url = $avecBoutique ? route($route, $this->boutique) : route($route);

        $this->actingAs($this->client)->{$verbe}($url, ['motif' => 'Un motif quelconque.'])
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('portesAdmin')]
    public function test_ladministration_refuse_meme_un_vendeur(
        string $verbe, string $route, bool $avecBoutique
    ): void {
        $url = $avecBoutique ? route($route, $this->boutique) : route($route);

        $this->actingAs($this->vendeur)->{$verbe}($url, ['motif' => 'Un motif quelconque.'])
            ->assertForbidden();
    }

    public function test_ladministration_elle_meme_passe(): void
    {
        $this->actingAs($this->admin)->get(route('admin.tableau'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.boutiques'))->assertOk();
    }

    public function test_un_visiteur_est_renvoye_a_la_connexion(): void
    {
        $this->get(route('admin.tableau'))->assertRedirect(route('connexion'));
    }

    /**
     * Un vendeur ne valide pas sa propre boutique.
     *
     * C'est la porte la plus tentante : elle rend visible au catalogue une
     * boutique que personne n'a regardée.
     */
    public function test_un_vendeur_ne_valide_pas_sa_propre_boutique(): void
    {
        $enAttente = Boutique::where('statut', 'en_attente')->orderBy('id')->firstOrFail();

        $this->actingAs($enAttente->utilisateur)
            ->post(route('admin.activer', $enAttente))->assertForbidden();

        $this->assertSame('en_attente', $enAttente->fresh()->statut);
        $this->assertFalse($enAttente->fresh()->estVisible());
    }

    public function test_un_vendeur_ne_suspend_pas_un_concurrent(): void
    {
        $concurrent = Boutique::where('statut', 'active')
            ->where('id', '!=', $this->boutique->id)->orderBy('id')->firstOrFail();

        $this->actingAs($this->vendeur)
            ->post(route('admin.suspendre', $concurrent), ['motif' => 'Concurrence gênante.'])
            ->assertForbidden();

        $this->assertSame('active', $concurrent->fresh()->statut);
    }

    /** Un vendeur ne se décerne pas le badge « officielle ». */
    public function test_un_vendeur_ne_se_met_pas_en_avant(): void
    {
        $avant = $this->boutique->officielle;

        $this->actingAs($this->vendeur)
            ->post(route('admin.officielle', $this->boutique))->assertForbidden();

        $this->assertSame($avant, $this->boutique->fresh()->officielle);
    }

    // ── Entre vendeurs ───────────────────────────────────────────────────────

    public function test_un_vendeur_ne_modifie_pas_le_produit_dun_autre(): void
    {
        $autre = Boutique::where('id', '!=', $this->boutique->id)->orderBy('id')->firstOrFail();
        $sonProduit = Produit::where('boutique_id', $autre->id)->firstOrFail();
        $avant = $sonProduit->prix;

        $this->actingAs($this->vendeur)
            ->put(route('vendeur.produit.modifier', $sonProduit), [
                'categorie_id' => $sonProduit->categorie_id, 'nom' => 'Détourné',
                'prix' => 100, 'stock' => 0,
            ])->assertForbidden();

        $this->assertSame($avant, $sonProduit->fresh()->prix);
    }

    public function test_un_vendeur_ne_retire_pas_le_produit_dun_autre(): void
    {
        $autre = Boutique::where('id', '!=', $this->boutique->id)->orderBy('id')->firstOrFail();
        $sonProduit = Produit::where('boutique_id', $autre->id)->where('actif', true)->firstOrFail();

        $this->actingAs($this->vendeur)
            ->post(route('vendeur.produit.bascule', $sonProduit))->assertForbidden();

        $this->assertTrue($sonProduit->fresh()->actif);
    }

    /** Un vendeur n'expédie pas une commande qui ne le concerne pas. */
    public function test_un_vendeur_nexpedie_pas_la_commande_dun_autre(): void
    {
        $autre = Boutique::where('id', '!=', $this->boutique->id)
            ->where('statut', 'active')->orderBy('id')->firstOrFail();

        $commande = $this->commandeChez($autre);

        $this->actingAs($this->vendeur)
            ->post(route('vendeur.expedier', $commande))->assertForbidden();

        $this->assertSame('en_preparation', $commande->fresh()->etat);
    }

    public function test_un_vendeur_ne_voit_que_ses_ventes(): void
    {
        $autre = Boutique::where('id', '!=', $this->boutique->id)
            ->where('statut', 'active')->orderBy('id')->firstOrFail();
        $siennes = $this->commandeChez($autre);

        $liste = $this->actingAs($this->vendeur)
            ->get(route('vendeur.commandes'))->assertOk()->viewData('liste');

        $this->assertFalse(
            collect($liste->items())->pluck('reference')->contains($siennes->reference)
        );
    }

    /** L'espace vendeur est fermé à qui n'a pas de boutique. */
    public function test_un_client_sans_boutique_nentre_pas_dans_lespace_vendeur(): void
    {
        foreach (['vendeur.tableau', 'vendeur.produits', 'vendeur.commandes', 'vendeur.boutique'] as $r) {
            $this->actingAs($this->client)->get(route($r))->assertForbidden();
        }
    }

    /** Mais il peut ouvrir une boutique : c'est la porte d'entrée. */
    public function test_un_client_peut_ouvrir_une_boutique(): void
    {
        $this->actingAs($this->client)->get(route('vendeur.ouvrir'))->assertOk();

        $this->actingAs($this->client)->post(route('vendeur.ouvrir'), [
            'nom' => 'Quincaillerie Awa', 'telephone' => '+221 77 000 00 00',
            'adresse' => 'Marché HLM', 'ville' => 'Dakar',
        ])->assertRedirect(route('vendeur.tableau'));

        $b = $this->client->fresh()->boutique;
        $this->assertNotNull($b);
        // Personne ne s'auto-valide.
        $this->assertSame('en_attente', $b->statut);
        $this->assertFalse($b->estVisible());
        // Et le rôle du compte suit.
        $this->assertSame('vendeur', $this->client->fresh()->role);
    }

    public function test_on_nouvre_pas_deux_boutiques(): void
    {
        $this->actingAs($this->vendeur)->post(route('vendeur.ouvrir'), [
            'nom' => 'Deuxième boutique', 'telephone' => '+221 77 000 00 00',
            'adresse' => 'Ailleurs', 'ville' => 'Dakar',
        ])->assertStatus(409);
    }

    // ── Entre clients ────────────────────────────────────────────────────────

    public function test_un_client_ne_voit_pas_la_commande_dun_autre(): void
    {
        $commande = $this->commandeChez($this->boutique);

        $intrus = User::create([
            'name' => 'Intrus', 'email' => 'intrus@essai.sn',
            'password' => 'motdepasse', 'role' => 'client', 'telephone' => '+221 70 000 00 00',
        ]);

        $this->actingAs($intrus)
            ->get(route('mes-commandes.detail', $commande))->assertForbidden();
        $this->actingAs($intrus)
            ->post(route('commande.annuler', $commande))->assertForbidden();
    }

    // ── Où l'on arrive après s'être connecté ─────────────────────────────────

    public function test_larrivee_depend_du_role(): void
    {
        $this->post('/connexion', ['email' => $this->client->email, 'password' => 'motdepasse'])
            ->assertRedirect(route('accueil'));
        $this->post('/deconnexion');

        $this->vendeur->update(['password' => 'motdepasse']);
        $this->post('/connexion', ['email' => $this->vendeur->email, 'password' => 'motdepasse'])
            ->assertRedirect(route('vendeur.tableau'));
        $this->post('/deconnexion');

        $this->post('/connexion', ['email' => $this->admin->email, 'password' => 'motdepasse'])
            ->assertRedirect(route('admin.tableau'));
    }

    /** Le rôle se décide à l'inscription, et il oriente. */
    public function test_qui_sinscrit_pour_vendre_arrive_sur_le_dossier(): void
    {
        $this->post('/inscription', [
            'name' => 'Quincaillerie Ba', 'email' => 'ba@essai.sn',
            'telephone' => '+221 77 111 11 11', 'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse', 'role' => 'vendeur',
        ])->assertRedirect(route('vendeur.ouvrir'));

        $this->assertSame('vendeur', User::firstWhere('email', 'ba@essai.sn')->role);
    }

    public function test_sans_role_linscription_est_refusee(): void
    {
        $this->post('/inscription', [
            'name' => 'Sans rôle', 'email' => 'sansrole@essai.sn',
            'telephone' => '+221 77 000 00 00', 'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
        ])->assertSessionHasErrors('role');

        $this->assertNull(User::firstWhere('email', 'sansrole@essai.sn'));
    }

    // ── Outils ───────────────────────────────────────────────────────────────

    private function commandeChez(Boutique $b): Commande
    {
        $produit = Produit::where('boutique_id', $b->id)
            ->where('stock', '>', 2)->where('actif', true)->firstOrFail();

        $adresse = Adresse::firstOrCreate(
            ['utilisateur_id' => $this->client->id],
            ['destinataire' => 'Awa BA', 'telephone' => '+221 77 000 00 00',
             'region' => 'Dakar', 'ville' => 'Dakar', 'quartier' => 'Grand Yoff',
             'par_defaut' => true]
        );

        app(Panier::class)->vider();
        app(Panier::class)->ajouter($produit, 1);

        return app(PasseCommande::class)->creer($this->client, $adresse);
    }
}
