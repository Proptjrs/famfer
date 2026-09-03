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

    // ── Le rôle se décide à l'inscription ────────────────────────────────────

    /**
     * Le formulaire demande ce qu'on vient faire.
     *
     * Rien ne le demandait : tout compte naissait acheteur, et une
     * quincaillerie devait ensuite retrouver seule la porte « Vendez sur
     * FamFer ». L'acteur n'était déterminé par personne.
     */
    public function test_linscription_demande_le_role(): void
    {
        $this->get(route('inscription'))->assertOk()
            ->assertSee('Vous venez sur FamFer pour')
            ->assertSee('Acheter du fer')
            ->assertSee('Vendre du fer');
    }

    public function test_sans_role_linscription_est_refusee(): void
    {
        $this->post('/inscription', [
            'name' => 'Sans rôle', 'email' => 'sansrole@essai.sn',
            'telephone' => '+221 77 000 00 00', 'genre' => 'particulier',
            'password' => 'mot-de-passe-essai', 'password_confirmation' => 'mot-de-passe-essai',
        ])->assertSessionHasErrors('role');

        $this->assertNull(User::firstWhere('email', 'sansrole@essai.sn'));
    }

    public function test_qui_sinscrit_pour_acheter_arrive_sur_le_catalogue(): void
    {
        $this->post('/inscription', [
            'name' => 'Awa BA', 'email' => 'awa2@chantier.sn',
            'telephone' => '+221 77 000 00 00', 'genre' => 'chantier',
            'password' => 'mot-de-passe-essai', 'password_confirmation' => 'mot-de-passe-essai',
            'role' => 'acheteur',
        ])->assertRedirect(route('accueil'));

        $u = User::firstWhere('email', 'awa2@chantier.sn');
        $this->assertNotNull($u->acheteur);
        $this->assertNull($u->vendeur);
    }

    /** Qui vient vendre part droit au dossier d'établissement. */
    public function test_qui_sinscrit_pour_vendre_arrive_sur_le_dossier(): void
    {
        $this->post('/inscription', [
            'name' => 'Quincaillerie Ba', 'email' => 'ba@quincaillerie.sn',
            'telephone' => '+221 77 111 11 11', 'genre' => 'entreprise',
            'password' => 'mot-de-passe-essai', 'password_confirmation' => 'mot-de-passe-essai',
            'role' => 'vendeur',
        ])->assertRedirect(route('vendeur.demande'));

        // Il peut acheter aussi : une quincaillerie s'approvisionne. Mais il
        // n'est pas vendeur tant que son dossier n'est pas déposé.
        $u = User::firstWhere('email', 'ba@quincaillerie.sn');
        $this->assertNotNull($u->acheteur);
        $this->assertNull($u->vendeur);
    }

    /** Venu par « Vendez sur FamFer », le bon choix est déjà coché. */
    public function test_le_role_vendeur_est_precoche_si_lon_vient_de_la(): void
    {
        $this->get(route('vendeur.demande'));

        // On vérifie la donnée passée à la vue, pas le balisage : les
        // attributs d'un « input » sont répartis sur plusieurs lignes, et une
        // chaîne exacte casserait à la première reformulation.
        $this->assertSame(
            'vendeur',
            $this->get(route('inscription'))->assertOk()->viewData('roleParDefaut')
        );
    }
}
