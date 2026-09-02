<?php

namespace Tests\Feature;

use App\Exceptions\StockInsuffisant;
use App\Exceptions\TransitionInterdite;
use App\Models\Acheteur;
use App\Models\Article;
use App\Models\Commande;
use App\Models\Offre;
use App\Models\User;
use App\Services\CommandeService;
use App\Services\ConversionUnites;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandeTest extends TestCase
{
    use RefreshDatabase;

    private CommandeService $service;
    private ConversionUnites $conversion;
    private Acheteur $acheteur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        $this->service = app(CommandeService::class);
        $this->conversion = app(ConversionUnites::class);

        $u = User::create(['name' => 'Modou FALL', 'email' => 'modou@chantier.sn', 'password' => 'password']);
        $this->acheteur = Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 111 22 33',
        ]);
    }

    private function offreT10(): Offre
    {
        $t10 = Article::where('reference', 'T10-12M')->firstOrFail();

        // « orderBy » est indispensable : sans tri explicite, PostgreSQL ne
        // garantit aucun ordre, et deux appels successifs peuvent renvoyer deux
        // offres différentes. Le test comparait alors le stock d'un vendeur à
        // celui d'un autre.
        return Offre::where('article_id', $t10->id)->orderBy('id')->firstOrFail();
    }

    private function commanderBarres(int $barres = 20): Commande
    {
        $offre = $this->offreT10();

        return $this->service->creer($this->acheteur, [
            ['offre' => $offre, 'quantite' => $barres, 'unite' => 'barre'],
        ]);
    }

    public function test_une_commande_reserve_la_marchandise_sans_la_sortir(): void
    {
        $offre = $this->offreT10();
        $stockAvant = $offre->quantite_pivot;

        $commande = $this->commanderBarres(20);
        $offre->refresh();

        $this->assertSame('en_attente_paiement', $commande->etat);
        $this->assertSame($stockAvant, $offre->quantite_pivot, 'le fer est encore chez le vendeur');
        $this->assertSame(
            $this->conversion->versPivot($offre->article, 'barre', 20),
            $offre->quantite_reservee_pivot
        );
    }

    /** Le prix se calcule au pivot : 20 barres à 4 200 F, quelle que soit l'unité. */
    public function test_le_montant_et_la_commission_sont_figes_a_la_commande(): void
    {
        $commande = $this->commanderBarres(20);

        $this->assertSame(84_000, $commande->montant_total);
        $this->assertSame(80, $commande->taux_commission_pour_mille);
        $this->assertSame(6_720, $commande->montant_commission);
        $this->assertSame(77_280, $commande->montantVendeur());

        // Le vendeur double son prix : la commande passée n'en dépend pas.
        $this->offreT10()->update(['prix_par_unite' => 8_400]);
        $this->assertSame(84_000, $commande->fresh()->montant_total);
    }

    public function test_une_commande_ne_melange_pas_deux_vendeurs(): void
    {
        $t10 = Article::where('reference', 'T10-12M')->firstOrFail();
        $deux = Offre::where('article_id', $t10->id)->take(2)->get();

        $this->expectExceptionMessage('un seul vendeur');
        $this->service->creer($this->acheteur, [
            ['offre' => $deux[0], 'quantite' => 1, 'unite' => 'barre'],
            ['offre' => $deux[1], 'quantite' => 1, 'unite' => 'barre'],
        ]);
    }

    /** Le parcours complet, jusqu'au paiement du vendeur. */
    public function test_le_parcours_nominal_va_du_paiement_a_la_reception(): void
    {
        $offre = $this->offreT10();
        $stockAvant = $offre->quantite_pivot;

        $c = $this->commanderBarres(20);
        $c = $this->service->marquerPayee($c);
        $this->assertSame('payee', $c->etat);
        $this->assertNotNull($c->acceptation_due_le);

        $c = $this->service->accepter($c);
        $c = $this->service->marquerPrete($c);
        $c = $this->service->remettre($c);

        // Retrait au comptoir : la remise vaut réception.
        $this->assertSame('receptionnee', $c->etat);

        $offre->refresh();
        $this->assertSame(
            $stockAvant - $this->conversion->versPivot($offre->article, 'barre', 20),
            $offre->quantite_pivot,
            'la marchandise doit avoir quitté le stock'
        );
        $this->assertSame(0, $offre->quantite_reservee_pivot);
    }

    /**
     * La machine à états n'est pas décorative.
     *
     * Sans elle, une commande payée pourrait être soldée sans livraison : le
     * vendeur serait payé pour du fer qu'il a encore dans son magasin.
     */
    public function test_on_ne_saute_pas_les_etapes(): void
    {
        $c = $this->service->marquerPayee($this->commanderBarres(5));

        $this->expectException(TransitionInterdite::class);
        $this->service->confirmerReception($c);
    }

    public function test_une_commande_annulee_rend_la_marchandise(): void
    {
        $offre = $this->offreT10();
        $dispoAvant = $offre->disponiblePivot();

        $c = $this->service->marquerPayee($this->commanderBarres(30));
        $this->assertLessThan($dispoAvant, $this->offreT10()->disponiblePivot());

        $this->service->annuler($c, 'Le vendeur ne répond pas');

        $this->assertSame($dispoAvant, $this->offreT10()->disponiblePivot());
    }

    public function test_le_delai_de_paiement_libere_la_reservation(): void
    {
        $offre = $this->offreT10();
        $dispoAvant = $offre->disponiblePivot();

        $c = $this->commanderBarres(15);
        $this->service->expirer($c);

        $this->assertSame('expiree', $c->fresh()->etat);
        $this->assertSame($dispoAvant, $this->offreT10()->disponiblePivot());
    }

    public function test_on_ne_commande_pas_plus_que_le_stock(): void
    {
        $offre = $this->offreT10();
        $tout = $this->conversion->nombreEntier($offre->article, 'barre', $offre->disponiblePivot());

        $this->expectException(StockInsuffisant::class);
        $this->commanderBarres($tout + 1);
    }

    /** Chaque transition laisse une trace : c'est ce qui tranche un litige. */
    public function test_chaque_transition_est_journalisee(): void
    {
        $c = $this->commanderBarres(10);
        $c = $this->service->marquerPayee($c);
        $c = $this->service->accepter($c);

        $suite = $c->transitions()->orderBy('id')->pluck('etat_arrivee')->all();

        $this->assertSame(['en_attente_paiement', 'payee', 'acceptee'], $suite);
    }
}
