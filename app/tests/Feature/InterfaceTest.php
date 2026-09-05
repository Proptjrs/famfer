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

    // ── Les pages d'erreur ───────────────────────────────────────────────────

    /**
     * Une erreur reste une page de FamFer.
     *
     * Laravel affichait « Not Found » : en anglais, sans marque, sans
     * navigation, sans issue. Un visiteur qui tombe dessus quitte le site.
     */
    public function test_la_page_introuvable_est_habillee(): void
    {
        $reponse = $this->get('/cette-page-nexiste-pas')->assertNotFound();
        $html = $reponse->getContent();

        $this->assertStringContainsString('Cette page n', $html);
        $this->assertStringContainsString('FAM', $html, 'La marque doit rester visible.');
        $this->assertStringContainsString(route('accueil'), $html,
            'Une page d\'erreur sans chemin de retour est un cul-de-sac.');
        $this->assertStringNotContainsString('Not Found', $html);
    }

    /**
     * Les pages de panne se rendent sans la base de données.
     *
     * C'est leur seule exigence réelle, et elle n'est pas théorique : une erreur
     * 500 est le plus souvent causée par une base indisponible. Une page
     * d'erreur qui interroge la base déclencherait une seconde erreur à
     * l'intérieur de la première, et le visiteur verrait la page blanche du
     * serveur.
     */
    public function test_les_pages_de_panne_ne_dependent_de_rien(): void
    {
        foreach ([500, 503] as $code) {
            $html = view('errors.' . $code)->render();

            $this->assertStringContainsString('FAM', $html);
            $this->assertStringNotContainsString('rayons', $html,
                "La page {$code} ne doit pas rendre la barre des rayons : elle "
                . 'interroge la base, précisément ce qui vient de tomber.');
            $this->assertStringNotContainsString('css/famfer.css', $html,
                "La page {$code} doit porter son style : si le serveur de "
                . 'fichiers est en cause, la feuille ne se chargerait pas non plus.');
        }
    }

    /**
     * Le gabarit se rend même sans session.
     *
     * « $errors » est partagé par l'intergiciel de session. Une page d'erreur
     * servie avant son démarrage le recevait nul, et « $errors->any() » faisait
     * alors échouer la page qui devait justement rattraper l'échec.
     */
    public function test_le_gabarit_supporte_labsence_de_session(): void
    {
        foreach ([403, 404, 419] as $code) {
            $html = view('errors.' . $code)->render();

            $this->assertStringContainsString('<h1', $html,
                "La page {$code} ne se rend pas hors contexte de requête.");
        }
    }

    // ── Les ressources ───────────────────────────────────────────────────────

    /**
     * Aucune page ne dépend d'un serveur tiers.
     *
     * Les polices venaient de Google Fonts : une résolution DNS, une poignée de
     * main TLS et un aller-retour avant que le premier caractère ne s'affiche
     * dans sa vraie fonte — sur une connexion lente, cela se compte en
     * secondes. Chaque visiteur déclenchait en outre une requête vers un
     * serveur qui voyait passer son adresse.
     *
     * Elles sont désormais servies par l'application. Cet essai empêche de
     * réintroduire la dépendance par distraction, en recopiant une balise
     * trouvée ailleurs.
     */
    #[DataProvider('lesPages')]
    public function test_aucune_page_ne_charge_de_ressource_externe(string $route, string $role): void
    {
        $html = $this->html($route, $role);

        foreach (['fonts.googleapis.com', 'fonts.gstatic.com', 'cdn.jsdelivr.net',
                  'cdnjs.cloudflare.com', 'unpkg.com'] as $hote) {
            $this->assertStringNotContainsString(
                $hote, $html,
                "La page « {$route} » charge une ressource depuis « {$hote} » : "
                . 'le site ne doit dépendre d\'aucun serveur tiers pour s\'afficher.'
            );
        }
    }

    /**
     * Les fontes embarquées existent et sont valides.
     *
     * Une déclaration « @font-face » qui pointe vers un fichier absent ou
     * tronqué ne lève aucune erreur : le navigateur retombe silencieusement sur
     * la police de repli, et le site s'affiche simplement moins bien. C'est
     * arrivé pendant la mise en place — un téléchargement interrompu avait
     * laissé un fichier de zéro octet.
     */
    public function test_les_fontes_embarquees_sont_valides(): void
    {
        $css = file_get_contents(public_path('css/polices.css'));

        preg_match_all("#url\('/(fonts/[^']+\.woff2)'\)#", $css, $trouvees);

        $this->assertNotEmpty($trouvees[1], 'Aucune fonte déclarée.');

        foreach ($trouvees[1] as $relatif) {
            $chemin = public_path($relatif);

            $this->assertFileExists($chemin, "La fonte « {$relatif} » est déclarée mais absente.");
            $this->assertGreaterThan(1024, filesize($chemin),
                "La fonte « {$relatif} » fait moins d'un kilo-octet : téléchargement tronqué.");
            $this->assertSame('wOF2', file_get_contents($chemin, false, null, 0, 4),
                "La fonte « {$relatif} » n'est pas un fichier WOFF2 valide.");
        }
    }

    /** Les deux fontes du premier écran sont préchargées, les autres non. */
    public function test_seules_les_fontes_du_premier_ecran_sont_prechargees(): void
    {
        $html = $this->get(route('accueil'))->assertOk()->getContent();

        preg_match_all('/<link[^>]+rel="preload"[^>]*>/i', $html, $preloads);

        $this->assertCount(2, $preloads[0],
            'Précharger toutes les fontes ferait concurrence au HTML lui-même : '
            . 'seules celles du premier écran le méritent.');

        foreach ($preloads[0] as $balise) {
            $this->assertStringContainsString('crossorigin', $balise,
                'Une fonte préchargée sans « crossorigin » est téléchargée deux fois.');
        }
    }
}
