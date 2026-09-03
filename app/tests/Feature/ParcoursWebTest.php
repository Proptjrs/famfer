<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Article;
use App\Models\Offre;
use App\Models\User;
use App\Models\Vendeur;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le parcours vu du navigateur.
 *
 * Ces tests ne vérifient pas la comptabilité — d'autres s'en chargent — mais
 * que les écrans mènent bien d'un bout à l'autre, et surtout qu'aucun d'eux ne
 * laisse voir ce qu'il ne doit pas.
 */
class ParcoursWebTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        $this->client = User::create([
            'name' => 'Modou FALL', 'email' => 'modou@chantier.sn', 'password' => 'password',
        ]);
        Acheteur::create([
            'utilisateur_id' => $this->client->id, 'genre' => 'chantier', 'telephone' => '+221 77 111 22 33',
        ]);
    }

    private function t10(): Article
    {
        return Article::where('reference', 'T10-12M')->firstOrFail();
    }

    /** Comparer les prix ne demande aucun compte : c'est ce qui amène les acheteurs. */
    public function test_la_recherche_est_ouverte_a_tous(): void
    {
        $this->get('/')->assertOk()->assertSee('FamFer', false);

        $this->get('/?q=fer+10')->assertOk()->assertSee('T10');

        $this->get(route('article', $this->t10()))
            ->assertOk()
            ->assertSee('Quincaillerie Ndiaye')
            ->assertSee('Comptoir du Fer Dakarois');
    }

    /** Le vendeur non vérifié n'apparaît nulle part, même sur la page publique. */
    public function test_un_vendeur_non_verifie_reste_invisible(): void
    {
        $attente = Vendeur::where('statut', 'en_attente')->firstOrFail();

        $this->get(route('article', $this->t10()))
            ->assertOk()
            ->assertDontSee($attente->raison_sociale);
    }

    public function test_on_ne_commande_pas_sans_compte(): void
    {
        $offre = Offre::orderBy('id')->firstOrFail();

        $this->post(route('panier.ajouter', $offre), ['quantite' => 1, 'unite' => 'barre'])
            ->assertRedirect(route('connexion'));
    }

    public function test_du_panier_a_la_commande(): void
    {
        $t10 = $this->t10();
        $offre = Offre::where('article_id', $t10->id)->orderBy('id')->firstOrFail();

        $this->actingAs($this->client)
            ->post(route('panier.ajouter', $offre), ['quantite' => '20', 'unite' => 'barre'])
            ->assertRedirect(route('panier.voir'));

        $this->actingAs($this->client)->get(route('panier.voir'))
            ->assertOk()->assertSee('84 000');

        $this->actingAs($this->client)
            ->post(route('panier.valider'), ['mode_remise' => 'retrait'])
            ->assertRedirect(route('acheteur.commandes'));

        $this->actingAs($this->client)->get(route('acheteur.commandes'))
            ->assertOk()->assertSee('À régler');
    }

    /** Une quantité qui ne tombe pas juste est refusée, avec un message clair. */
    public function test_une_quantite_impossible_est_refusee(): void
    {
        $offre = Offre::where('article_id', $this->t10()->id)->orderBy('id')->firstOrFail();

        $this->actingAs($this->client)
            ->post(route('panier.ajouter', $offre), ['quantite' => '0.3333', 'unite' => 'barre'])
            ->assertSessionHas('erreur');
    }

    /** Un acheteur n'entre pas dans l'espace d'un vendeur. */
    public function test_lespace_vendeur_est_ferme_aux_acheteurs(): void
    {
        $this->actingAs($this->client)->get(route('vendeur.tableau'))->assertForbidden();
    }

    public function test_un_vendeur_voit_son_tableau_de_bord(): void
    {
        $vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();

        $this->actingAs($vendeur->utilisateur)->get(route('vendeur.tableau'))
            ->assertOk()
            ->assertSee($vendeur->raison_sociale);
    }

    /**
     * Un vendeur ne touche pas au commerce d'un autre.
     *
     * Sur une place de marché, c'est la garantie la plus élémentaire — et la
     * plus facile à oublier quand on ajoute un écran.
     */
    public function test_un_vendeur_ne_touche_pas_au_stock_dun_autre(): void
    {
        $vendeurs = Vendeur::where('statut', 'verifie')->orderBy('id')->take(2)->get();
        $offreDeLautre = Offre::where('vendeur_id', $vendeurs[1]->id)->firstOrFail();

        $this->actingAs($vendeurs[0]->utilisateur)
            ->post(route('vendeur.stock', $offreDeLautre), ['quantite' => '100', 'unite' => 'barre'])
            ->assertForbidden();
    }

    public function test_ladministration_affiche_les_invariants(): void
    {
        // L'administrateur vient du semis : le recréer heurterait la contrainte
        // d'unicité sur l'adresse.
        $admin = User::firstWhere('est_admin', true);

        $this->actingAs($admin)->get(route('admin.tableau'))
            ->assertOk()
            ->assertSee('Grand livre équilibré')
            ->assertSee('Séquestre justifié');
    }

    public function test_inscription_et_connexion(): void
    {
        // « role » est désormais obligatoire : l'inscription demande ce qu'on
        // vient faire, et c'est là que l'acteur se décide.
        $this->post(route('inscription'), [
            'name' => 'Awa NDIAYE', 'email' => 'awa@chantier.sn', 'telephone' => '+221 77 999 88 77',
            'genre' => 'particulier', 'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
            'role' => 'acheteur',
        ])->assertRedirect(route('accueil'));

        $this->assertAuthenticated();
        $this->assertNotNull(User::where('email', 'awa@chantier.sn')->first()->acheteur);
    }
}
