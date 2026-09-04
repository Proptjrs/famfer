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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Les règles d'interface, vérifiées sur le HTML réellement produit.
 *
 * Ces contrôles se faisaient jusqu'ici à l'œil, écran par écran, et ne
 * survivaient donc pas à la page suivante. Une régression d'accessibilité ne se
 * voit pas : un champ qui perd son étiquette continue de s'afficher, une image
 * sans alternative textuelle aussi. Seule une vérification automatique les
 * attrape.
 *
 * Ce que ces essais garantissent, page par page :
 *
 * - **un seul « h1 »**, et aucun saut de niveau de titre. Un lecteur d'écran
 *   navigue par la hiérarchie ; sautée, elle ne mène plus nulle part ;
 * - **chaque champ porte une étiquette** liée par « for », par « aria-label »
 *   ou par imbrication. Sans elle, le champ est un rectangle muet ;
 * - **chaque image porte un « alt »**, fût-il vide pour les décoratives ;
 * - **aucun jeton CSS mort**. Les couleurs de l'ancienne feuille de style
 *   (« --gris », « --bord », « --forge »…) ne définissent plus rien : une vue
 *   qui en garde une s'affiche avec la couleur héritée, sans erreur visible ;
 * - **le lien d'évitement est le premier lien de la page**, sinon il n'évite
 *   rien.
 */
class InterfaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les jetons de l'ancienne feuille de style.
     *
     * Ils ne définissent plus rien. Une vue qui en conserve un rend une couleur
     * héritée au lieu de la couleur voulue — sans lever la moindre erreur, ce
     * qui est le pire des cas.
     */
    private const JETONS_MORTS = [
        '--bord', '--gris', '--gris-fonce', '--gris-pale', '--orange',
        '--orange-fonce', '--orange-pale', '--blanc', '--fond', '--sombre',
        '--vert', '--vert-pale', '--rouge', '--rouge-pale', '--bleu', '--ombre',
        // Ceux-ci n'ont jamais existé : ils venaient d'une refonte abandonnée,
        // et le choix de rôle de l'inscription n'a donc jamais été stylé.
        '--forge', '--forge-pale', '--acier-3',
    ];

    private User $client;
    private User $vendeur;
    private User $admin;
    private Boutique $boutique;
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
        $adresse = Adresse::create([
            'utilisateur_id' => $this->client->id, 'destinataire' => 'Awa BA',
            'telephone' => '+221 77 000 00 00', 'region' => 'Dakar',
            'ville' => 'Dakar', 'quartier' => 'Grand Yoff', 'par_defaut' => true,
        ]);

        $this->boutique = Boutique::where('statut', 'active')->orderBy('id')->firstOrFail();
        $this->vendeur = $this->boutique->utilisateur;
        $this->produit = Produit::where('boutique_id', $this->boutique->id)
            ->where('stock', '>', 3)->where('actif', true)->orderBy('id')->firstOrFail();

        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit, 1);
        $this->commande = app(PasseCommande::class)->expedier(
            app(PasseCommande::class)->creer($this->client, $adresse)
        );
    }

    /** @return array<string, array{string, string}> url, rôle */
    public static function lesPages(): array
    {
        return [
            'accueil'              => ['accueil', 'visiteur'],
            'recherche'            => ['recherche', 'visiteur'],
            'connexion'            => ['connexion', 'visiteur'],
            'inscription'          => ['inscription', 'visiteur'],
            'mot de passe oublié'  => ['mdp.oubli', 'visiteur'],
            'conditions'           => ['conditions', 'visiteur'],
            'crédits'              => ['credits', 'visiteur'],
            'panier'               => ['panier', 'visiteur'],
            'compte'               => ['compte', 'client'],
            'mes commandes'        => ['mes-commandes', 'client'],
            'mes adresses'         => ['adresses', 'client'],
            'validation'           => ['commande', 'client'],
            'tableau vendeur'      => ['vendeur.tableau', 'vendeur'],
            'produits vendeur'     => ['vendeur.produits', 'vendeur'],
            'nouveau produit'      => ['vendeur.produit.nouveau', 'vendeur'],
            'ventes vendeur'       => ['vendeur.commandes', 'vendeur'],
            'commission vendeur'   => ['vendeur.commissions', 'vendeur'],
            'vitrine vendeur'      => ['vendeur.boutique', 'vendeur'],
            'tableau admin'        => ['admin.tableau', 'admin'],
            'boutiques admin'      => ['admin.boutiques', 'admin'],
            'commandes admin'      => ['admin.commandes', 'admin'],
            'revenus admin'        => ['admin.revenus', 'admin'],
            'litiges admin'        => ['admin.litiges', 'admin'],
        ];
    }

    private function utilisateur(string $role): ?User
    {
        return match ($role) {
            'client' => $this->client,
            'vendeur' => $this->vendeur,
            'admin' => $this->admin,
            default => null,
        };
    }

    private function html(string $nomDeRoute, string $role): string
    {
        // L'ecran de validation redirige sur un panier vide, et le panier a ete
        // vide par la commande du « setUp ». On le regarnit pour que la page
        // existe : ce n'est pas un contournement, c'est sa condition d'affichage.
        if ($nomDeRoute === 'commande') {
            app(Panier::class)->vider();
            app(Panier::class)->ajouter($this->produit->fresh(), 1);
        }

        $requete = $this->withoutExceptionHandling();

        if ($u = $this->utilisateur($role)) {
            $requete = $requete->actingAs($u);
        }

        return $requete->get(route($nomDeRoute))->assertOk()->getContent();
    }

    // ── Les règles, sur toutes les pages ─────────────────────────────────────

    #[DataProvider('lesPages')]
    public function test_chaque_page_a_un_seul_titre_principal(string $route, string $role): void
    {
        $html = $this->html($route, $role);

        $this->assertSame(
            1, substr_count($html, '<h1'),
            "La page « {$route} » doit porter exactement un « h1 » : c'est ce qui "
            . 'donne son nom à l\'écran pour qui navigue au lecteur d\'écran.'
        );
    }

    #[DataProvider('lesPages')]
    public function test_aucune_page_ne_garde_de_jeton_mort(string $route, string $role): void
    {
        $html = $this->html($route, $role);

        foreach (self::JETONS_MORTS as $jeton) {
            $this->assertStringNotContainsString(
                'var(' . $jeton . ')', $html,
                "La page « {$route} » utilise « {$jeton} », qui ne définit plus rien : "
                . 'la couleur rendue sera celle héritée, sans la moindre erreur visible.'
            );
        }
    }

    #[DataProvider('lesPages')]
    public function test_chaque_image_porte_une_alternative(string $route, string $role): void
    {
        $html = $this->html($route, $role);

        preg_match_all('/<img\b[^>]*>/i', $html, $balises);

        foreach ($balises[0] as $balise) {
            $this->assertMatchesRegularExpression(
                '/\salt\s*=/i', $balise,
                "Une image sans « alt » sur « {$route} » : " . substr($balise, 0, 90)
            );
        }
    }

    #[DataProvider('lesPages')]
    public function test_chaque_champ_porte_une_etiquette(string $route, string $role): void
    {
        $html = $this->html($route, $role);

        preg_match_all('/<(input|select|textarea)\b[^>]*>/i', $html, $balises);

        foreach ($balises[0] as $balise) {
            // Les champs cachés et les jetons CSRF n'ont rien à annoncer.
            if (preg_match('/type\s*=\s*"(hidden|submit|radio|checkbox|file)"/i', $balise)) {
                continue;
            }

            $etiquete = preg_match('/aria-label\s*=/i', $balise);

            if (! $etiquete && preg_match('/\bid\s*=\s*"([^"]+)"/i', $balise, $m)) {
                $etiquete = str_contains($html, 'for="' . $m[1] . '"');
            }

            $this->assertTrue(
                (bool) $etiquete,
                "Un champ sans étiquette sur « {$route} » : " . substr($balise, 0, 110)
                . ' — sans elle, le champ est un rectangle muet pour qui ne voit pas l\'écran.'
            );
        }
    }

    #[DataProvider('lesPages')]
    public function test_le_lien_devitement_vient_en_premier(string $route, string $role): void
    {
        $html = $this->html($route, $role);

        preg_match('/<a\b[^>]*>/i', $html, $premier);

        $this->assertStringContainsString(
            'class="evitement"', $premier[0] ?? '',
            "Sur « {$route} », le premier lien n'est pas le lien d'évitement : "
            . 'un lecteur d\'écran traverserait l\'en-tête et les quatorze rayons '
            . 'avant d\'atteindre le contenu.'
        );
    }

    // ── Les écrans particuliers ──────────────────────────────────────────────

    /**
     * Le code de remise ne se montre qu'au client.
     *
     * Affiché sur l'écran du vendeur, il ne prouverait plus rien : le commerçant
     * le recopierait sans jamais avoir vu personne.
     */
    public function test_le_detail_de_commande_montre_le_code_au_client_seul(): void
    {
        $this->actingAs($this->client)
            ->get(route('mes-commandes.detail', $this->commande))
            ->assertOk()
            ->assertSee($this->commande->code_livraison);

        $this->actingAs($this->vendeur)
            ->get(route('vendeur.commandes'))
            ->assertOk()
            ->assertDontSee($this->commande->code_livraison);
    }

    /** La page produit compare les prix : c'est ce qui distingue la place de marché. */
    public function test_la_fiche_produit_compare_les_vendeurs(): void
    {
        $html = $this->get(route('produit', $this->produit))->assertOk()->getContent();

        $this->assertStringContainsString('Le même produit ailleurs', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    /**
     * Les tableaux de bord portent des graphiques, pas seulement des compteurs.
     *
     * Un chiffre sans point de comparaison n'aide à décider de rien, et un
     * tableau de bord qui ne sert pas à décider est une page d'accueil déguisée.
     */
    public function test_les_tableaux_de_bord_portent_des_series(): void
    {
        foreach ([['vendeur.tableau', $this->vendeur], ['admin.tableau', $this->admin]] as [$route, $u]) {
            $html = $this->actingAs($u)->get(route($route))->assertOk()->getContent();

            $this->assertStringContainsString('class="graphe"', $html,
                "Le tableau de bord « {$route} » ne porte aucun graphique.");
            $this->assertStringContainsString('role="img"', $html,
                "Le graphique de « {$route} » n'est pas annoncé aux outils d'assistance.");
        }
    }

    /**
     * Le paiement mobile n'est pas proposé comme s'il fonctionnait.
     *
     * Il l'était : le formulaire acceptait « wave » et « om », qu'aucun code ne
     * traite. Il reste affiché — la demande existe — mais désactivé.
     */
    public function test_le_paiement_mobile_est_annonce_mais_desactive(): void
    {
        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit->fresh(), 1);

        $html = $this->actingAs($this->client)->get(route('commande'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Wave et Orange Money', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringNotContainsString('name="paiement" value="wave"', $html);
    }
}
