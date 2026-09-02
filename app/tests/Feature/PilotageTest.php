<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Article;
use App\Models\Commande;
use App\Models\Offre;
use App\Models\User;
use App\Services\CommandeService;
use App\Services\LitigeService;
use App\Services\PaiementService;
use App\Services\PilotageService;
use App\Services\ReversementService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotageTest extends TestCase
{
    use RefreshDatabase;

    private PilotageService $pilotage;
    private CommandeService $commandes;
    private PaiementService $paiements;
    private ReversementService $reversements;
    private Acheteur $acheteur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        $this->pilotage = app(PilotageService::class);
        $this->commandes = app(CommandeService::class);
        $this->paiements = app(PaiementService::class);
        $this->reversements = app(ReversementService::class);

        $u = User::create(['name' => 'Modou FALL', 'email' => 'modou@chantier.sn', 'password' => 'password']);
        $this->acheteur = Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 111 22 33',
        ]);
    }

    private function commandeRecue(int $barres = 20): Commande
    {
        $t10 = Article::where('reference', 'T10-12M')->firstOrFail();
        $offre = Offre::where('article_id', $t10->id)->orderBy('id')->firstOrFail();

        $c = $this->commandes->creer($this->acheteur, [
            ['offre' => $offre, 'quantite' => $barres, 'unite' => 'barre'],
        ]);
        $this->paiements->traiterRappel($c, 'wave', 'WV-' . $c->id, $c->montant_total);
        $c = $this->commandes->accepter($c->fresh());

        return $this->commandes->remettre($this->commandes->marquerPrete($c));
    }

    /**
     * La commission ne compte qu'une fois la commande soldée.
     *
     * Compter celle d'une commande encore en séquestre gonflerait le chiffre
     * d'affaires d'un argent qui peut repartir chez l'acheteur.
     */
    public function test_la_commission_nest_acquise_quapres_le_solde(): void
    {
        $c = $this->commandeRecue(20);

        $avant = $this->pilotage->pourLaPlateforme();
        $this->assertSame(0, $avant['commission_acquise'], 'rien n\'est acquis avant le solde');
        $this->assertSame(84_000, $avant['sequestre_detenu']);

        $this->reversements->solderLesCommandesRecues($c->vendeur);

        $apres = $this->pilotage->pourLaPlateforme();
        $this->assertSame(6_720, $apres['commission_acquise']);
        $this->assertSame(0, $apres['sequestre_detenu']);
        $this->assertSame(77_280, $apres['du_aux_vendeurs']);
    }

    public function test_le_tableau_du_vendeur_distingue_le_brut_du_net(): void
    {
        $c = $this->commandeRecue(20);
        $this->reversements->solderLesCommandesRecues($c->vendeur);

        $t = $this->pilotage->pourVendeur($c->vendeur);

        $this->assertSame(1, $t['commandes_soldees']);
        $this->assertSame(84_000, $t['chiffre_affaires']);
        $this->assertSame(6_720, $t['commission_versee']);
        $this->assertSame(77_280, $t['net_percu']);
        $this->assertSame(77_280, $t['reste_du'], 'pas encore viré');
    }

    public function test_le_tableau_de_la_plateforme_porte_les_invariants(): void
    {
        $this->commandeRecue(10);

        $t = $this->pilotage->pourLaPlateforme();

        $this->assertTrue($t['livre_equilibre']);
        $this->assertTrue($t['sequestre_justifie']);
        $this->assertSame(3, $t['vendeurs_verifies']);
        $this->assertSame(1, $t['vendeurs_en_attente']);
    }

    public function test_le_taux_dannulation_se_calcule(): void
    {
        $this->commandeRecue(5);

        $t10 = Article::where('reference', 'T10-12M')->firstOrFail();
        $offre = Offre::where('article_id', $t10->id)->orderBy('id')->firstOrFail();
        $perdue = $this->commandes->creer($this->acheteur, [
            ['offre' => $offre, 'quantite' => 2, 'unite' => 'barre'],
        ]);
        $this->commandes->expirer($perdue);

        $t = $this->pilotage->pourLaPlateforme();

        $this->assertSame(2, $t['commandes']);
        $this->assertSame(50.0, $t['taux_annulation_pour_cent']);
    }

    /** Du fer immobilisé, c'est de la trésorerie qui dort. */
    public function test_les_articles_dormants_sont_reperes(): void
    {
        $c = $this->commandeRecue(20);

        $dormants = collect($this->pilotage->dormants($c->vendeur));

        // Le T10 vient de sortir : il n'est pas dormant.
        $this->assertFalse($dormants->contains('reference', 'T10-12M'));
        // Le T12 du même vendeur n'a jamais bougé.
        $this->assertTrue($dormants->contains('reference', 'T12-12M'));
    }

    public function test_un_litige_ouvert_remonte_au_vendeur(): void
    {
        $c = $this->commandeRecue(20);
        app(LitigeService::class)->ouvrir(
            $c, $this->acheteur->utilisateur, 'quantite_manquante', 'Deux barres manquent.'
        );

        $this->assertSame(1, $this->pilotage->pourVendeur($c->vendeur)['litiges_ouverts']);
        $this->assertSame(1, $this->pilotage->pourLaPlateforme()['litiges_ouverts']);
    }
}
