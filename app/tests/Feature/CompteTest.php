<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\User;
use App\Models\Vendeur;
use App\Notifications\DecisionInscription;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Le compte : coordonnées, mot de passe, mot de passe oublié.
 *
 * Un commerçant qui perd son mot de passe perd son stock, ses commandes en
 * cours et l'argent qui l'attend au séquestre. Il n'y a aucune autre porte —
 * c'est pour cela que ces chemins comptent autant que la vente elle-même.
 */
class CompteTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::create([
            'name' => 'Modou FALL', 'email' => 'modou@chantier.sn', 'password' => 'ancien-mot-de-passe',
        ]);
        Acheteur::create([
            'utilisateur_id' => $this->client->id, 'genre' => 'particulier',
            'telephone' => '+221 77 111 22 33',
        ]);
    }

    public function test_lacheteur_corrige_ses_coordonnees(): void
    {
        $this->actingAs($this->client)->put(route('compte.maj'), [
            'name' => 'Modou FALL', 'email' => 'modou@chantier.sn',
            'telephone' => '+221 78 999 88 77', 'genre' => 'chantier',
            'adresse_defaut' => 'Chantier Diamniadio, lot 7',
            'latitude' => '14.7280', 'longitude' => '-17.1840',
        ])->assertRedirect();

        $a = $this->client->fresh()->acheteur;
        $this->assertSame('chantier', $a->genre);
        $this->assertSame('Chantier Diamniadio, lot 7', $a->adresse_defaut);

        // Les coordonnées comptent : sans elles, aucune livraison n'est chiffrable.
        $this->assertEqualsWithDelta(14.7280, $a->latitude, 0.0001);
        $this->assertEqualsWithDelta(-17.1840, $a->longitude, 0.0001);
    }

    public function test_on_ne_prend_pas_ladresse_dun_autre(): void
    {
        User::create(['name' => 'Autre', 'email' => 'occupee@famfer.sn', 'password' => 'password']);

        $this->actingAs($this->client)->put(route('compte.maj'), [
            'name' => 'Modou FALL', 'email' => 'occupee@famfer.sn',
            'telephone' => '+221 77 111 22 33', 'genre' => 'particulier',
        ])->assertSessionHasErrors('email');

        $this->assertSame('modou@chantier.sn', $this->client->fresh()->email);
    }

    /**
     * L'ancien mot de passe est exigé.
     *
     * Sans lui, une session laissée ouverte sur un téléphone posé au comptoir
     * suffirait à s'emparer du compte, et donc de l'argent qui y transite.
     */
    public function test_changer_de_mot_de_passe_exige_lancien(): void
    {
        $this->actingAs($this->client)->put(route('compte.mdp'), [
            'actuel' => 'ce-nest-pas-le-bon',
            'password' => 'un-nouveau-mot-de-passe', 'password_confirmation' => 'un-nouveau-mot-de-passe',
        ])->assertSessionHas('erreur');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $this->client->fresh()->password));
    }

    public function test_le_mot_de_passe_se_change(): void
    {
        $this->actingAs($this->client)->put(route('compte.mdp'), [
            'actuel' => 'ancien-mot-de-passe',
            'password' => 'un-nouveau-mot-de-passe', 'password_confirmation' => 'un-nouveau-mot-de-passe',
        ])->assertSessionHas('ok');

        $this->assertTrue(Hash::check('un-nouveau-mot-de-passe', $this->client->fresh()->password));
    }

    // ── Mot de passe oublié ──────────────────────────────────────────────────

    public function test_le_lien_de_reinitialisation_part(): void
    {
        Notification::fake();

        $this->post(route('mdp.oubli'), ['email' => 'modou@chantier.sn'])
            ->assertSessionHas('ok');

        Notification::assertSentTo($this->client, ResetPassword::class);
    }

    /**
     * Une adresse inconnue reçoit la même réponse.
     *
     * Répondre « ce compte n'existe pas » permettrait d'énumérer les inscrits
     * de la plateforme, quincailleries comprises.
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
        $jeton = Password::createToken($this->client);

        $this->post(route('mdp.reinitialiser'), [
            'token' => $jeton, 'email' => 'modou@chantier.sn',
            'password' => 'tout-a-fait-nouveau', 'password_confirmation' => 'tout-a-fait-nouveau',
        ])->assertRedirect(route('connexion'));

        $this->assertTrue(Hash::check('tout-a-fait-nouveau', $this->client->fresh()->password));
    }

    /** Un jeton inventé ne vaut rien — c'est tout l'objet du courtier. */
    public function test_un_jeton_invente_est_refuse(): void
    {
        $this->post(route('mdp.reinitialiser'), [
            'token' => 'jeton-fabrique-de-toutes-pieces', 'email' => 'modou@chantier.sn',
            'password' => 'tentative-de-vol', 'password_confirmation' => 'tentative-de-vol',
        ])->assertSessionHas('erreur');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $this->client->fresh()->password));
    }

    /** Le jeton ne sert qu'une fois. */
    public function test_le_jeton_ne_sert_quune_fois(): void
    {
        $jeton = Password::createToken($this->client);

        $this->post(route('mdp.reinitialiser'), [
            'token' => $jeton, 'email' => 'modou@chantier.sn',
            'password' => 'premier-changement', 'password_confirmation' => 'premier-changement',
        ])->assertRedirect(route('connexion'));

        $this->post(route('mdp.reinitialiser'), [
            'token' => $jeton, 'email' => 'modou@chantier.sn',
            'password' => 'second-changement', 'password_confirmation' => 'second-changement',
        ])->assertSessionHas('erreur');

        $this->assertTrue(Hash::check('premier-changement', $this->client->fresh()->password));
    }

    // ── La décision de l'administration ──────────────────────────────────────

    /** Une demande laissée sans réponse est ce qui fait fuir une quincaillerie. */
    public function test_le_demandeur_est_prevenu_de_la_decision(): void
    {
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        // L'administrateur est déjà semé.
        $admin = User::firstWhere('est_admin', true);

        $enAttente = Vendeur::where('statut', 'en_attente')->orderBy('id')->first();
        $this->assertNotNull($enAttente, 'Le seeder doit contenir une demande en attente.');

        Notification::fake();

        $this->actingAs($admin)->post(route('admin.verifier', $enAttente))->assertRedirect();

        Notification::assertSentTo($enAttente->utilisateur, DecisionInscription::class,
            fn (DecisionInscription $n) => $n->acceptee === true);
    }
}
