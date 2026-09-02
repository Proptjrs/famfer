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
 * Devenir vendeur, puis publier son stock.
 *
 * C'est le parcours qui manquait : la plateforme savait déjà vendre, mais rien
 * ne permettait à une nouvelle quincaillerie d'y entrer.
 */
class DevenirVendeurTest extends TestCase
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

    private array $demande = [
        'raison_sociale' => 'Quincaillerie Fall & Fils',
        'ninea' => '0099887766',
        'telephone' => '+221 77 555 44 33',
        'adresse' => 'Route de Rufisque, lot 12',
        'commune' => 'Rufisque',
        'latitude' => '14.7167',
        'longitude' => '-17.2667',
    ];

    /** La demande part en attente : on ne s'auto-déclare pas commerçant. */
    public function test_une_demande_part_en_attente_de_verification(): void
    {
        $this->actingAs($this->client)
            ->post(route('vendeur.demande'), $this->demande)
            ->assertRedirect(route('vendeur.tableau'));

        $v = Vendeur::where('utilisateur_id', $this->client->id)->firstOrFail();

        $this->assertSame('en_attente', $v->statut);
        $this->assertFalse($v->estVisible());
        // La commission par défaut s'applique tant que rien n'est négocié.
        $this->assertSame(80, $v->taux_commission_pour_mille);
    }

    public function test_on_ne_depose_pas_deux_demandes(): void
    {
        $this->actingAs($this->client)->post(route('vendeur.demande'), $this->demande);

        $this->actingAs($this->client->fresh())
            ->post(route('vendeur.demande'), $this->demande)
            ->assertStatus(409);
    }

    /**
     * Un vendeur non vérifié prépare ses offres, mais personne ne les voit.
     *
     * C'est ce qui lui permet d'être prêt le jour où l'administration valide,
     * sans que la place de marché n'expose une maison inconnue.
     */
    public function test_un_vendeur_en_attente_publie_sans_etre_visible(): void
    {
        $this->actingAs($this->client)->post(route('vendeur.demande'), $this->demande);
        $client = $this->client->fresh();

        $t10 = Article::where('reference', 'T10-12M')->firstOrFail();

        $this->actingAs($client)->post(route('vendeur.offre.publier'), [
            'article_id' => $t10->id, 'prix_par_unite' => 4_000,
            'unite_affichee' => 'barre', 'delai_preparation_h' => 3, 'quantite' => '50',
        ])->assertRedirect(route('vendeur.offres'));

        $offre = Offre::where('vendeur_id', $client->vendeur->id)->firstOrFail();

        // Le stock est bien entré, par un mouvement.
        $this->assertSame(50 * 7_404, $offre->quantite_pivot);
        $this->assertSame($offre->quantite_pivot, app(\App\Services\StockService::class)
            ->stockJournalise($offre));

        // Mais l'offre n'apparaît pas chez les acheteurs.
        $this->assertFalse($t10->offresVisibles()->contains('id', $offre->id));

        // Une fois vérifiée, elle apparaît — sans rien republier.
        $client->vendeur->update(['statut' => 'verifie', 'verifie_le' => now()]);
        $this->assertTrue($t10->fresh()->offresVisibles()->contains('id', $offre->id));
    }

    /** On ne vend pas une brouette « à la tonne ». */
    public function test_une_unite_etrangere_a_larticle_est_refusee(): void
    {
        $this->actingAs($this->client)->post(route('vendeur.demande'), $this->demande);

        $brouette = Article::where('reference', 'BROUETTE-100')->firstOrFail();

        $this->actingAs($this->client->fresh())->post(route('vendeur.offre.publier'), [
            'article_id' => $brouette->id, 'prix_par_unite' => 28_000,
            'unite_affichee' => 'tonne', 'delai_preparation_h' => 2, 'quantite' => '3',
        ])->assertSessionHas('erreur');

        $this->assertSame(0, Offre::where('article_id', $brouette->id)
            ->where('vendeur_id', $this->client->fresh()->vendeur->id)->count());
    }

    public function test_on_ne_publie_pas_deux_fois_le_meme_article(): void
    {
        $this->actingAs($this->client)->post(route('vendeur.demande'), $this->demande);
        $client = $this->client->fresh();
        $t10 = Article::where('reference', 'T10-12M')->firstOrFail();

        $ligne = [
            'article_id' => $t10->id, 'prix_par_unite' => 4_000,
            'unite_affichee' => 'barre', 'delai_preparation_h' => 2, 'quantite' => '10',
        ];

        $this->actingAs($client)->post(route('vendeur.offre.publier'), $ligne);
        $this->actingAs($client)->post(route('vendeur.offre.publier'), $ligne)
            ->assertSessionHas('erreur');

        $this->assertSame(1, Offre::where('vendeur_id', $client->vendeur->id)
            ->where('article_id', $t10->id)->count());
    }

    /** Changer un prix ne touche pas aux commandes déjà passées. */
    public function test_le_prix_se_modifie(): void
    {
        $vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();
        $offre = Offre::where('vendeur_id', $vendeur->id)->orderBy('id')->firstOrFail();

        $this->actingAs($vendeur->utilisateur)
            ->put(route('vendeur.offre.modifier', $offre), [
                'prix_par_unite' => 9_999, 'delai_preparation_h' => 6,
            ])->assertRedirect();

        $this->assertSame(9_999, $offre->fresh()->prix_par_unite);
        $this->assertSame(6, $offre->fresh()->delai_preparation_h);
    }

    /**
     * Retirer une offre la rend invisible sans rien effacer.
     *
     * La supprimer emporterait l'historique des mouvements de stock et des
     * commandes passées.
     */
    public function test_retirer_une_offre_ne_leffacce_pas(): void
    {
        $vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();
        $offre = Offre::where('vendeur_id', $vendeur->id)->orderBy('id')->firstOrFail();
        $mouvements = $offre->mouvements()->count();

        $this->actingAs($vendeur->utilisateur)
            ->post(route('vendeur.offre.bascule', $offre))->assertRedirect();

        $offre->refresh();
        $this->assertFalse($offre->actif);
        $this->assertSame($mouvements, $offre->mouvements()->count());
        $this->assertFalse($offre->article->offresVisibles()->contains('id', $offre->id));

        // Et l'on peut la remettre en vente.
        $this->actingAs($vendeur->utilisateur)->post(route('vendeur.offre.bascule', $offre));
        $this->assertTrue($offre->fresh()->actif);
    }

    public function test_un_vendeur_ne_modifie_pas_loffre_dun_autre(): void
    {
        $vendeurs = Vendeur::where('statut', 'verifie')->orderBy('id')->take(2)->get();
        $offreDeLautre = Offre::where('vendeur_id', $vendeurs[1]->id)->orderBy('id')->firstOrFail();

        $this->actingAs($vendeurs[0]->utilisateur)
            ->put(route('vendeur.offre.modifier', $offreDeLautre), [
                'prix_par_unite' => 1, 'delai_preparation_h' => 0,
            ])->assertForbidden();

        $this->assertNotSame(1, $offreDeLautre->fresh()->prix_par_unite);
    }

    /**
     * Le journal de stock, tel que le vendeur le lit.
     *
     * Le stock n'est pas un compteur mais la somme d'un journal : le vendeur
     * doit donc pouvoir lire ce journal, sinon le choix devient invérifiable
     * pour celui qu'il concerne.
     */
    public function test_le_vendeur_lit_le_journal_de_son_stock(): void
    {
        $vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();
        $offre = Offre::where('vendeur_id', $vendeur->id)->orderBy('id')->firstOrFail();

        $reponse = $this->actingAs($vendeur->utilisateur)
            ->get(route('vendeur.journal', $offre))->assertOk()
            ->assertSee('Concordant');

        // Le cumul affiché est bien la somme du journal, et il tombe sur le
        // compteur de l'offre : c'est tout l'intérêt de la page.
        $this->assertSame($offre->quantite_pivot, $reponse->viewData('cumul'));
        $this->assertSame(
            app(\App\Services\StockService::class)->stockJournalise($offre),
            $reponse->viewData('cumul')
        );
    }

    public function test_un_vendeur_ne_lit_pas_le_journal_dun_autre(): void
    {
        $vendeurs = Vendeur::where('statut', 'verifie')->orderBy('id')->take(2)->get();
        $offreDeLautre = Offre::where('vendeur_id', $vendeurs[1]->id)->orderBy('id')->firstOrFail();

        $this->actingAs($vendeurs[0]->utilisateur)
            ->get(route('vendeur.journal', $offreDeLautre))->assertForbidden();
    }

    /** La page « mon argent » dit le disponible, et d'où il vient. */
    public function test_le_vendeur_voit_son_argent(): void
    {
        $vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();

        $reponse = $this->actingAs($vendeur->utilisateur)
            ->get(route('vendeur.argent'))->assertOk();

        // Le disponible vient du grand livre, pas d'un cumul de commandes.
        $this->assertSame(
            app(\App\Services\GrandLivre::class)->solde('vendeur:' . $vendeur->id),
            $reponse->viewData('solde')
        );
        $this->assertSame(0, $reponse->viewData('litiges'));
    }

    public function test_lespace_argent_est_ferme_a_qui_nest_pas_vendeur(): void
    {
        $this->actingAs($this->client)->get(route('vendeur.argent'))->assertForbidden();
    }
}
