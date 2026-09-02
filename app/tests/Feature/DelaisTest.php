<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Article;
use App\Models\Commande;
use App\Models\Offre;
use App\Models\User;
use App\Services\CommandeService;
use App\Services\PaiementService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Les trois délais.
 *
 * Ils ne sont pas des détails de confort : sans eux, un panier abandonné bloque
 * le stock d'un vendeur pour toujours, et un acheteur distrait retient son
 * argent indéfiniment.
 */
class DelaisTest extends TestCase
{
    use RefreshDatabase;

    private CommandeService $commandes;
    private Acheteur $acheteur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        $this->commandes = app(CommandeService::class);

        $u = User::create(['name' => 'Modou FALL', 'email' => 'modou@chantier.sn', 'password' => 'password']);
        $this->acheteur = Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 111 22 33',
            // Depuis que la livraison se facture au poids et à la distance, un
            // acheteur sans point repéré ne peut plus se faire livrer : ces
            // coordonnées sont ce qui rend le scénario « livraison » possible.
            'latitude' => 14.7100, 'longitude' => -17.4400,
        ]);
    }

    private function offre(): Offre
    {
        $t10 = Article::where('reference', 'T10-12M')->firstOrFail();

        return Offre::where('article_id', $t10->id)->orderBy('id')->firstOrFail();
    }

    private function commande(int $barres = 10, string $remise = 'retrait'): Commande
    {
        return $this->commandes->creer($this->acheteur, [
            ['offre' => $this->offre(), 'quantite' => $barres, 'unite' => 'barre'],
        ], $remise, $remise === 'livraison' ? 'Chantier Golf Sud' : null);
    }

    public function test_un_panier_non_paye_libere_le_stock_apres_quinze_minutes(): void
    {
        $dispoAvant = $this->offre()->disponiblePivot();
        $this->commande(10);
        $this->assertLessThan($dispoAvant, $this->offre()->disponiblePivot());

        Carbon::setTestNow(now()->addMinutes(16));
        $this->artisan('famfer:delais')->assertSuccessful();

        $this->assertSame($dispoAvant, $this->offre()->disponiblePivot());
        Carbon::setTestNow();
    }

    /** Le vendeur ne répond pas : l'acheteur est remboursé, le stock revient. */
    public function test_une_commande_non_acceptee_est_annulee_apres_deux_heures(): void
    {
        $dispoAvant = $this->offre()->disponiblePivot();

        $c = $this->commande(10);
        app(PaiementService::class)->traiterRappel($c, 'wave', 'WV-1', $c->montant_total);
        $this->assertSame('payee', $c->fresh()->etat);

        Carbon::setTestNow(now()->addHours(3));
        $this->artisan('famfer:delais')->assertSuccessful();

        $this->assertSame('annulee', $c->fresh()->etat);
        $this->assertSame($dispoAvant, $this->offre()->disponiblePivot());
        Carbon::setTestNow();
    }

    /**
     * Sans réponse de l'acheteur, la réception est réputée acquise.
     *
     * Sinon un acheteur distrait — ou de mauvaise foi — retiendrait l'argent du
     * vendeur pour toujours.
     */
    public function test_la_reception_est_reputee_acquise_apres_soixante_douze_heures(): void
    {
        $c = $this->commande(10, 'livraison');
        app(PaiementService::class)->traiterRappel($c, 'wave', 'WV-2', $c->montant_total);
        $c = $this->commandes->accepter($c->fresh());
        $c = $this->commandes->remettre($this->commandes->marquerPrete($c));

        $this->assertSame('en_livraison', $c->etat);

        Carbon::setTestNow(now()->addHours(73));
        $this->artisan('famfer:delais')->assertSuccessful();

        $this->assertSame('receptionnee', $c->fresh()->etat);
        Carbon::setTestNow();
    }

    /** Avant l'échéance, rien ne bouge. */
    public function test_les_delais_ne_se_declenchent_pas_trop_tot(): void
    {
        $c = $this->commande(10);

        Carbon::setTestNow(now()->addMinutes(10));
        $this->artisan('famfer:delais')->assertSuccessful();

        $this->assertSame('en_attente_paiement', $c->fresh()->etat);
        Carbon::setTestNow();
    }
}
