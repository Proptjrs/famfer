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
 * L'en-tête, et ce qu'il propose selon qui regarde.
 *
 * Trois étages, comme sur les places de marché : un bandeau de service pour ce
 * qui ne s'achète pas, l'en-tête avec la recherche et le menu du compte, puis
 * les rayons. Le compte tient dans un seul menu déroulant plutôt qu'en cinq
 * liens alignés — c'est ce qui laisse la place à la recherche et ce qui permet
 * de nommer les espaces au lieu de les juxtaposer.
 */
class EnteteTest extends TestCase
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
            'name' => 'Awa BA', 'email' => 'awa@chantier.sn', 'password' => 'password',
        ]);
        Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 000 11 22',
        ]);

        return $u;
    }

    public function test_le_bandeau_porte_la_porte_des_vendeurs(): void
    {
        $this->get(route('accueil'))->assertOk()
            ->assertSee('Vendez sur FamFer')
            ->assertSee(route('vendeur.demande'), false);
    }

    /** Un visiteur se voit proposer de se connecter, et de créer un compte. */
    public function test_le_menu_dun_visiteur_propose_dentrer(): void
    {
        $this->get(route('accueil'))->assertOk()
            ->assertSee('Se connecter')
            ->assertSee('En créer un');
    }

    /**
     * Le menu d'un vendeur nomme ses quatre écrans.
     *
     * Avant, seul « Mon commerce » y figurait : les offres, les ventes et
     * l'argent ne s'atteignaient qu'en passant par le tableau de bord.
     */
    public function test_le_menu_dun_vendeur_ouvre_sur_ses_quatre_ecrans(): void
    {
        $v = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();

        $this->actingAs($v->utilisateur)->get(route('accueil'))->assertOk()
            ->assertSee('Mon commerce')
            ->assertSee(route('vendeur.offres'), false)
            ->assertSee(route('vendeur.commandes'), false)
            ->assertSee(route('vendeur.argent'), false);
    }

    public function test_le_menu_dun_acheteur_ne_montre_pas_le_commerce(): void
    {
        $this->actingAs($this->acheteur())->get(route('accueil'))->assertOk()
            ->assertDontSee(route('vendeur.argent'), false)
            ->assertDontSee(route('admin.tableau'), false)
            // On lui propose en revanche de devenir vendeur.
            ->assertSee(route('vendeur.demande'), false);
    }

    public function test_le_menu_de_ladministration_mene_a_son_tableau(): void
    {
        $admin = User::firstWhere('est_admin', true);

        $this->actingAs($admin)->get(route('accueil'))->assertOk()
            ->assertSee(route('admin.tableau'), false);
    }

    /** Les sept familles, avec leur icône et leur compte. */
    public function test_les_rayons_portent_les_sept_familles(): void
    {
        $reponse = $this->get(route('accueil'))->assertOk();

        foreach (['Fer à béton', 'Tôles', 'Tubes et profilés', 'Treillis et fils',
                  'Quincaillerie', 'Outillage et soudure', 'Pièces détachées'] as $nom) {
            $reponse->assertSee($nom);
        }

        // Les familles viennent du « view composer » de la mise en page :
        // elles n'appartiennent donc pas aux données de la vue racine et ne se
        // lisent pas par « viewData ». On compte les liens rendus.
        $this->assertSame(
            7,
            substr_count($reponse->getContent(), '?famille=')
        );
    }

    /** La recherche part de l'en-tête, donc de n'importe quelle page. */
    public function test_la_recherche_est_dans_len_tete_de_toutes_les_pages(): void
    {
        // Un identifiant en dur ne tient pas : les séquences ne repartent pas
        // de un entre deux tests.
        $article = \App\Models\Article::orderBy('id')->firstOrFail();

        foreach ([route('accueil'), route('conditions'), route('article', $article)] as $url) {
            $this->get($url)->assertOk()->assertSee('Cherchez un article', false);
        }
    }
}
