<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\User;
use App\Models\Vendeur;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Où l'on entre, et où l'on arrive.
 *
 * Tout le monde atterrissait sur le catalogue après s'être connecté. Un
 * quincaillier qui se connecte à sept heures du matin vient traiter ses
 * commandes, pas acheter du fer : lui présenter la vitrine lui fait chercher
 * son propre commerce dans le menu.
 *
 * Et rien, sur les pages publiques, n'indiquait à une quincaillerie que la
 * plateforme acceptait des vendeurs : la porte ne s'affichait qu'une fois le
 * compte créé.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);
    }

    private function acheteur(): User
    {
        $u = User::create([
            'name' => 'Awa BA', 'email' => 'awa@chantier.sn', 'password' => 'mot-de-passe-essai',
        ]);
        Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 000 11 22',
        ]);

        return $u;
    }

    // ── La porte se voit avant d'avoir un compte ─────────────────────────────

    public function test_un_visiteur_voit_la_porte_des_vendeurs(): void
    {
        $this->get(route('accueil'))->assertOk()->assertSee('Vendre sur FamFer');
    }

    /**
     * Cliquer dessus sans compte ramène au bon endroit après connexion.
     *
     * Sans « intended », la quincaillerie atterrissait sur le catalogue et
     * devait retrouver seule la porte par laquelle elle était entrée.
     */
    public function test_la_porte_ramene_au_formulaire_apres_connexion(): void
    {
        $this->get(route('vendeur.demande'))->assertRedirect(route('connexion'));

        $this->post('/connexion', [
            'email' => $this->acheteur()->email, 'password' => 'mot-de-passe-essai',
        ])->assertRedirect(route('vendeur.demande'));
    }

    // ── L'arrivée dépend du rôle ─────────────────────────────────────────────

    public function test_un_acheteur_arrive_sur_le_catalogue(): void
    {
        $this->post('/connexion', [
            'email' => $this->acheteur()->email, 'password' => 'mot-de-passe-essai',
        ])->assertRedirect(route('accueil'));
    }

    public function test_un_vendeur_arrive_sur_son_commerce(): void
    {
        $v = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();
        $v->utilisateur->update(['password' => 'mot-de-passe-essai']);

        $this->post('/connexion', [
            'email' => $v->utilisateur->email, 'password' => 'mot-de-passe-essai',
        ])->assertRedirect(route('vendeur.tableau'));
    }

    /** Y compris en attente : c'est là qu'il prépare ses offres. */
    public function test_un_vendeur_en_attente_arrive_aussi_sur_son_commerce(): void
    {
        $v = Vendeur::where('statut', 'en_attente')->orderBy('id')->firstOrFail();
        $v->utilisateur->update(['password' => 'mot-de-passe-essai']);

        $this->post('/connexion', [
            'email' => $v->utilisateur->email, 'password' => 'mot-de-passe-essai',
        ])->assertRedirect(route('vendeur.tableau'));
    }

    public function test_ladministration_arrive_sur_son_tableau(): void
    {
        $admin = User::create([
            'name' => 'Administration', 'email' => 'admin2@famfer.sn',
            'password' => 'mot-de-passe-essai', 'est_admin' => true,
        ]);

        $this->post('/connexion', [
            'email' => $admin->email, 'password' => 'mot-de-passe-essai',
        ])->assertRedirect(route('admin.tableau'));
    }

    // ── L'en-tête dit ce qu'on est ───────────────────────────────────────────

    public function test_un_vendeur_en_attente_le_lit_dans_len_tete(): void
    {
        $v = Vendeur::where('statut', 'en_attente')->orderBy('id')->firstOrFail();

        $this->actingAs($v->utilisateur)->get(route('vendeur.tableau'))
            ->assertOk()->assertSee('en attente');
    }

    /** Un acheteur n'a pas d'espace professionnel dans son menu. */
    public function test_un_acheteur_na_pas_de_lien_vers_ladministration(): void
    {
        $this->actingAs($this->acheteur())->get(route('accueil'))
            ->assertOk()->assertDontSee('Administration</a>', false);
    }

    /** Les conditions générales sont atteignables de partout. */
    public function test_les_conditions_sont_liees_depuis_le_pied_de_page(): void
    {
        $this->get(route('accueil'))->assertOk()->assertSee(route('conditions'), false);
        $this->get(route('conditions'))->assertOk()->assertSee('séquestre');
    }
}
