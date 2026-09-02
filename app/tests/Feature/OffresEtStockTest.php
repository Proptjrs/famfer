<?php

namespace Tests\Feature;

use App\Exceptions\StockInsuffisant;
use App\Models\Article;
use App\Models\Offre;
use App\Models\Vendeur;
use App\Services\ConversionUnites;
use App\Services\StockService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OffresEtStockTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private ConversionUnites $conversion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);
        $this->stock = app(StockService::class);
        $this->conversion = app(ConversionUnites::class);
    }

    private function t10(): Article
    {
        return Article::where('reference', 'T10-12M')->firstOrFail();
    }

    /**
     * La raison d'être du référentiel partagé.
     *
     * Trois vendeurs affichent leur prix dans trois unités différentes — la
     * tonne, la barre, le kilo. Sans article commun et sans conversion, aucun
     * classement n'aurait de sens.
     */
    public function test_trois_vendeurs_un_meme_article_se_comparent_au_gramme(): void
    {
        $offres = Offre::with('vendeur', 'article.unitesVente')
            ->where('article_id', $this->t10()->id)
            ->get()
            ->filter(fn (Offre $o) => $o->vendeur->estVisible())
            ->sortBy(fn (Offre $o) => $o->prixParPivot())
            ->values();

        $this->assertCount(3, $offres);
        $this->assertSame('Comptoir du Fer Dakarois', $offres[0]->vendeur->raison_sociale);
        $this->assertSame('Établissements Sow Métaux', $offres[2]->vendeur->raison_sociale);

        // Le moins cher affiche pourtant le plus gros nombre : 545 000 F la tonne
        // contre 590 F le kilo. C'est bien la conversion qui tranche.
        $this->assertGreaterThan($offres[2]->prix_par_unite, $offres[0]->prix_par_unite);
    }

    public function test_un_vendeur_non_verifie_reste_invisible(): void
    {
        $attente = Vendeur::where('statut', 'en_attente')->orderBy('id')->firstOrFail();

        $this->assertFalse($attente->estVisible());
        $this->assertTrue(Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail()->estVisible());
    }

    /** Le cache doit toujours valoir la somme du journal — sinon il ment. */
    public function test_le_stock_affiche_egale_la_somme_des_mouvements(): void
    {
        foreach (Offre::all() as $offre) {
            $this->assertSame(
                $this->stock->stockJournalise($offre),
                $offre->quantite_pivot,
                "cache et journal divergent sur l'offre {$offre->id}"
            );
        }
    }

    public function test_reserver_diminue_le_disponible_sans_toucher_au_stock(): void
    {
        $offre = Offre::where('article_id', $this->t10()->id)->orderBy('id')->firstOrFail();
        // On part de l'état observé, jamais d'un zéro supposé : une autre classe
        // de tests peut avoir laissé une réservation en base.
        $stockAvant = $offre->quantite_pivot;
        $dispoAvant = $offre->disponiblePivot();
        $dix = $this->conversion->versPivot($this->t10(), 'barre', 10);

        $this->stock->reserver($offre, $dix);
        $offre->refresh();

        // La marchandise est encore là : elle est seulement promise.
        $this->assertSame($stockAvant, $offre->quantite_pivot);
        $this->assertSame($dispoAvant - $dix, $offre->disponiblePivot());
    }

    /**
     * Deux commandes pour la dernière tonne.
     *
     * Le verrou pessimiste s'observe en concurrence réelle, ce qu'un test unitaire
     * sur une base de fichier ne reproduit pas. Ce test vérifie la règle que le
     * verrou protège : la seconde demande voit le stock déjà engagé et se voit
     * refusée. Le vrai test de concurrence viendra en bout de chaîne, avec deux
     * requêtes simultanées sur MySQL.
     */
    public function test_on_ne_reserve_pas_deux_fois_la_derniere_tonne(): void
    {
        $offre = Offre::where('article_id', $this->t10()->id)->orderBy('id')->firstOrFail();
        $tout = $offre->disponiblePivot();

        $this->stock->reserver($offre, $tout);
        $offre->refresh();
        $this->assertSame(0, $offre->disponiblePivot());

        $this->expectException(StockInsuffisant::class);
        $this->stock->reserver($offre, 1);
    }

    public function test_liberer_ne_rend_jamais_plus_que_ce_qui_etait_reserve(): void
    {
        $offre = Offre::where('article_id', $this->t10()->id)->orderBy('id')->firstOrFail();
        $cinq = $this->conversion->versPivot($this->t10(), 'barre', 5);

        $this->stock->reserver($offre, $cinq);
        $this->stock->liberer($offre, $cinq * 3);      // on tente d'en rendre trop
        $offre->refresh();

        $this->assertSame(0, $offre->quantite_reservee_pivot);
    }

    public function test_la_sortie_de_vente_retire_la_marchandise(): void
    {
        $offre = Offre::where('article_id', $this->t10()->id)->orderBy('id')->firstOrFail();
        $stockAvant = $offre->quantite_pivot;
        $reserveAvant = $offre->quantite_reservee_pivot;
        $vingt = $this->conversion->versPivot($this->t10(), 'barre', 20);

        $this->stock->reserver($offre, $vingt);
        $this->stock->sortir($offre, $vingt);
        $offre->refresh();

        $this->assertSame($stockAvant - $vingt, $offre->quantite_pivot);
        $this->assertSame($reserveAvant, $offre->quantite_reservee_pivot);
        $this->assertSame($this->stock->stockJournalise($offre), $offre->quantite_pivot);
    }

    /** La commission se calcule sur le taux du vendeur, en francs entiers. */
    public function test_la_commission_suit_le_taux_du_vendeur(): void
    {
        $vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();

        $this->assertSame(80, $vendeur->taux_commission_pour_mille);
        $this->assertSame(8_000, $vendeur->commissionSur(100_000));
        $this->assertSame(3_360, $vendeur->commissionSur(42_000));
    }
}
