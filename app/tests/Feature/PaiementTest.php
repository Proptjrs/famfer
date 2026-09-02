<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Article;
use App\Models\Commande;
use App\Models\Ecriture;
use App\Models\Offre;
use App\Models\Paiement;
use App\Models\User;
use App\Services\CommandeService;
use App\Services\GrandLivre;
use App\Services\PaiementService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le rappel de l'opérateur de paiement.
 *
 * Deux défauts qu'on ne peut pas corriger côté opérateur : le rappel peut
 * arriver deux fois, et il peut ne jamais arriver. Ces tests vérifient que la
 * plateforme survit aux deux.
 */
class PaiementTest extends TestCase
{
    use RefreshDatabase;

    private PaiementService $paiements;
    private GrandLivre $livre;
    private Acheteur $acheteur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        $this->paiements = app(PaiementService::class);
        $this->livre = app(GrandLivre::class);

        $u = User::create(['name' => 'Modou FALL', 'email' => 'modou@chantier.sn', 'password' => 'password']);
        $this->acheteur = Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 111 22 33',
        ]);
    }

    private function commande(int $barres = 20): Commande
    {
        $t10 = Article::where('reference', 'T10-12M')->firstOrFail();
        $offre = Offre::where('article_id', $t10->id)->orderBy('id')->firstOrFail();

        return app(CommandeService::class)->creer($this->acheteur, [
            ['offre' => $offre, 'quantite' => $barres, 'unite' => 'barre'],
        ]);
    }

    public function test_un_rappel_confirme_la_commande_et_credite_le_sequestre(): void
    {
        $c = $this->commande(20);

        $traite = $this->paiements->traiterRappel(
            $c, 'wave', 'WV-2026-0001', $c->montant_total, fraisOperateur: 1_500,
            chargeUtile: ['reference' => 'WV-2026-0001']
        );

        $this->assertTrue($traite);
        $this->assertSame('payee', $c->fresh()->etat);
        $this->assertSame(84_000, $this->livre->solde(GrandLivre::SEQUESTRE));
        $this->assertSame(1_500, $this->livre->solde(GrandLivre::FRAIS));
    }

    /**
     * Le même rappel, deux fois.
     *
     * C'est le cas qui coûte le plus cher : sans idempotence, le séquestre est
     * crédité deux fois et le grand livre annonce de l'argent qui n'existe pas.
     */
    public function test_un_rappel_recu_deux_fois_ne_credite_quune_fois(): void
    {
        $c = $this->commande(20);

        $premier = $this->paiements->traiterRappel($c, 'wave', 'WV-DOUBLON', $c->montant_total);
        $second = $this->paiements->traiterRappel($c, 'wave', 'WV-DOUBLON', $c->montant_total);

        $this->assertTrue($premier, 'le premier rappel doit être traité');
        $this->assertFalse($second, 'le second doit être reconnu comme déjà vu');

        $this->assertSame(1, Paiement::where('cle_idempotence', 'WV-DOUBLON')->count());
        $this->assertSame(84_000, $this->livre->solde(GrandLivre::SEQUESTRE));
        $this->assertSame(2, Ecriture::where('operation', 'encaissement')->count(), 'une seule opération, deux lignes');
        $this->assertTrue($this->livre->estEquilibre());
    }

    /** Un montant qui ne correspond pas ne solde rien : un humain doit voir. */
    public function test_un_montant_different_nengage_pas_le_sequestre(): void
    {
        $c = $this->commande(20);

        $this->paiements->traiterRappel($c, 'wave', 'WV-PARTIEL', 50_000);

        $this->assertSame('echoue', Paiement::where('cle_idempotence', 'WV-PARTIEL')->first()->etat);
        $this->assertSame('en_attente_paiement', $c->fresh()->etat);
        $this->assertSame(0, $this->livre->solde(GrandLivre::SEQUESTRE));
    }

    /** Le rappel perdu : le relevé de l'opérateur le rattrape. */
    public function test_la_reconciliation_repere_un_rappel_perdu(): void
    {
        $c = $this->commande(10);
        $this->paiements->traiterRappel($c, 'wave', 'WV-RECU', $c->montant_total);

        $bilan = $this->paiements->reconcilier([
            ['cle' => 'WV-RECU', 'montant' => $c->montant_total],
            ['cle' => 'WV-JAMAIS-ARRIVE', 'montant' => 42_000],
        ]);

        $this->assertSame(['WV-JAMAIS-ARRIVE'], $bilan['rattrapes']);
        $this->assertSame([], $bilan['anomalies']);
    }

    /** L'inverse, plus grave : nous avons crédité ce que l'opérateur ignore. */
    public function test_la_reconciliation_signale_un_paiement_sans_contrepartie(): void
    {
        $c = $this->commande(10);
        $this->paiements->traiterRappel($c, 'wave', 'WV-FANTOME', $c->montant_total);

        $bilan = $this->paiements->reconcilier([
            ['cle' => 'WV-AUTRE', 'montant' => 1_000],
        ]);

        $this->assertContains('WV-FANTOME', $bilan['anomalies']);
    }
}
