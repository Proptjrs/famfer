<?php

namespace Tests\Feature;

use App\Models\Adresse;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use App\Services\Panier;
use App\Services\PasseCommande;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Chaque page répond, dans le rôle qui la concerne.
 *
 * Une application peut avoir tous ses tests métier au vert et rester
 * inutilisable : il suffit qu'un écran tombe en erreur parce qu'une variable
 * manque, ou qu'un lien pointe vers une route jamais ouverte. Ce fichier
 * parcourt toutes les pages, et sert de garde-fou à la question « est-ce que
 * ça marche vraiment ».
 */
class ToutesLesPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $vendeur;
    private User $admin;
    private Produit $produit;
    private Commande $commande;

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

        $boutique = Boutique::where('statut', 'active')->orderBy('id')->firstOrFail();
        $this->vendeur = $boutique->utilisateur;

        $this->produit = Produit::where('boutique_id', $boutique->id)
            ->where('stock', '>', 3)->where('actif', true)->orderBy('id')->firstOrFail();

        $adresse = Adresse::create([
            'utilisateur_id' => $this->client->id, 'destinataire' => 'Awa BA',
            'telephone' => '+221 77 000 00 00', 'region' => 'Dakar',
            'ville' => 'Dakar', 'quartier' => 'Grand Yoff', 'par_defaut' => true,
        ]);

        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit, 1);
        $this->commande = app(PasseCommande::class)->creer($this->client, $adresse);
    }

    // ── Les pages publiques ──────────────────────────────────────────────────

    public function test_les_pages_publiques_repondent(): void
    {
        $categorie = Categorie::whereNull('parente_id')->orderBy('rang')->firstOrFail();
        $boutique = Boutique::where('statut', 'active')->orderBy('id')->firstOrFail();

        $pages = [
            route('accueil'),
            route('recherche'),
            route('recherche', ['q' => 'fer']),
            route('recherche', ['tri' => 'remise']),
            route('recherche', ['min' => 1000, 'max' => 50000, 'stock' => 1]),
            route('rayon', $categorie),
            route('rayon', $categorie->enfants->first()),
            route('produit', $this->produit),
            route('boutique', $boutique),
            route('conditions'),
            route('credits'),
            route('panier'),
            route('connexion'),
            route('inscription'),
            route('mdp.oubli'),
        ];

        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
    }

    // ── Le client ────────────────────────────────────────────────────────────

    public function test_les_pages_du_client_repondent(): void
    {
        app(Panier::class)->ajouter($this->produit->fresh(), 1);

        foreach ([
            route('commande'),
            route('mes-commandes'),
            route('mes-commandes.detail', $this->commande),
            route('compte'),
            route('adresses'),
        ] as $url) {
            $this->actingAs($this->client)->get($url)->assertOk();
        }
    }

    // ── Le vendeur ───────────────────────────────────────────────────────────

    public function test_les_pages_du_vendeur_repondent(): void
    {
        foreach ([
            route('vendeur.tableau'),
            route('vendeur.produits'),
            route('vendeur.produit.nouveau'),
            route('vendeur.produit.editer', $this->produit),
            route('vendeur.commandes'),
            route('vendeur.commandes', ['etat' => 'en_preparation']),
            route('vendeur.commissions'),
            route('vendeur.boutique'),
        ] as $url) {
            $this->actingAs($this->vendeur)->get($url)->assertOk();
        }
    }

    /** Un compte sans boutique voit le formulaire d'ouverture, pas une erreur. */
    public function test_le_formulaire_douverture_repond(): void
    {
        $this->actingAs($this->client)->get(route('vendeur.ouvrir'))->assertOk();
    }

    // ── L'administration ─────────────────────────────────────────────────────

    public function test_les_pages_de_ladministration_repondent(): void
    {
        foreach ([
            route('admin.tableau'),
            route('admin.boutiques'),
            route('admin.boutiques', ['statut' => 'en_attente']),
            route('admin.commandes'),
            route('admin.commandes', ['etat' => 'en_preparation']),
            route('admin.revenus'),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    // ── Aucune route oubliée ─────────────────────────────────────────────────

    /**
     * Toutes les routes GET sont couvertes par ce fichier.
     *
     * Sans ce contrôle, une page ajoutée demain échapperait à la vérification
     * sans que personne ne s'en aperçoive.
     */
    public function test_aucune_page_nechappe_a_la_verification(): void
    {
        $couvertes = [
            'accueil', 'recherche', 'rayon', 'produit', 'boutique', 'conditions',
            'credits', 'panier', 'connexion', 'inscription', 'mdp.oubli',
            'password.reset', 'commande', 'mes-commandes', 'mes-commandes.detail',
            'compte', 'adresses', 'vendeur.ouvrir', 'vendeur.tableau',
            'vendeur.produits', 'vendeur.produit.nouveau', 'vendeur.produit.editer',
            'vendeur.commandes', 'vendeur.commissions', 'vendeur.boutique',
            'admin.tableau', 'admin.boutiques', 'admin.commandes', 'admin.revenus',
        ];

        $oubliees = collect(Route::getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true))
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->reject(fn ($n) => in_array($n, $couvertes, true))
            // Les routes techniques de Laravel ne sont pas des pages.
            ->reject(fn ($n) => str_starts_with($n, 'storage.') || $n === 'sanctum.csrf-cookie')
            ->values();

        $this->assertEmpty(
            $oubliees->all(),
            'Ces pages ne sont vérifiées nulle part : ' . $oubliees->implode(', ')
        );
    }
}
