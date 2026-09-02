<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Article;
use App\Models\Commande;
use App\Models\Offre;
use App\Models\User;
use App\Models\Vendeur;
use App\Services\CommandeService;
use App\Services\GrandLivre;
use App\Services\LitigeService;
use App\Services\PaiementService;
use App\Services\ReversementService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Litiges et reversements.
 *
 * Le séquestre n'a de valeur que si la plateforme s'en sert : tant qu'elle
 * détient l'argent, elle peut trancher. Ces tests vérifient qu'un litige gèle
 * bien le versement, et que chaque issue laisse les comptes justes.
 */
class LitigeEtReversementTest extends TestCase
{
    use RefreshDatabase;

    private CommandeService $commandes;
    private PaiementService $paiements;
    private ReversementService $reversements;
    private LitigeService $litiges;
    private GrandLivre $livre;
    private Acheteur $acheteur;
    private User $arbitre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        $this->commandes = app(CommandeService::class);
        $this->paiements = app(PaiementService::class);
        $this->reversements = app(ReversementService::class);
        $this->litiges = app(LitigeService::class);
        $this->livre = app(GrandLivre::class);

        $u = User::create(['name' => 'Modou FALL', 'email' => 'modou@chantier.sn', 'password' => 'password']);
        $this->acheteur = Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 111 22 33',
        ]);
        $this->arbitre = User::create([
            'name' => 'Arbitrage FamFer', 'email' => 'arbitrage@famfer.sn', 'password' => 'password',
        ]);
    }

    private function offre(): Offre
    {
        $t10 = Article::where('reference', 'T10-12M')->firstOrFail();

        return Offre::where('article_id', $t10->id)->orderBy('id')->firstOrFail();
    }

    /** Une commande menée jusqu'à la réception, argent encaissé. */
    private function commandeRecue(int $barres = 20): Commande
    {
        $c = $this->commandes->creer($this->acheteur, [
            ['offre' => $this->offre(), 'quantite' => $barres, 'unite' => 'barre'],
        ]);
        $this->paiements->traiterRappel($c, 'wave', 'WV-' . $c->id, $c->montant_total);
        $c = $this->commandes->accepter($c->fresh());
        $c = $this->commandes->marquerPrete($c);

        return $this->commandes->remettre($c);       // retrait : vaut réception
    }

    public function test_le_reversement_verse_le_total_du_moins_la_commission(): void
    {
        $c = $this->commandeRecue(20);               // 84 000 F, commission 6 720 F
        $vendeur = $c->vendeur;

        $this->assertSame(1, $this->reversements->solderLesCommandesRecues($vendeur));

        $reversement = $this->reversements->preparer($vendeur);

        $this->assertSame(77_280, $reversement->montant);
        $this->assertSame(0, $this->livre->solde(GrandLivre::compteVendeur($vendeur->id)));
        $this->assertSame(6_720, $this->livre->solde(GrandLivre::COMMISSION));
        $this->assertTrue($this->livre->estEquilibre());
    }

    /**
     * Le litige gèle tout le reversement du vendeur.
     *
     * Volontairement brutal : on ne verse rien tant qu'une procédure est en
     * cours, même sur les commandes qui ne sont pas contestées. L'argent retenu
     * est le seul moyen de pression dont dispose la plateforme.
     */
    public function test_un_litige_ouvert_gele_le_reversement(): void
    {
        $c = $this->commandeRecue(20);
        $vendeur = $c->vendeur;

        $this->litiges->ouvrir(
            $c, $this->acheteur->utilisateur, 'quantite_manquante',
            'Dix-huit barres livrées au lieu de vingt.'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('litige est ouvert');
        $this->reversements->preparer($vendeur);
    }

    public function test_un_litige_tranche_pour_lacheteur_rembourse_sans_commission(): void
    {
        $c = $this->commandeRecue(20);

        $litige = $this->litiges->ouvrir(
            $c, $this->acheteur->utilisateur, 'non_livre', 'Rien n\'est arrivé sur le chantier.'
        );

        $this->litiges->trancherPourAcheteur($litige, $this->arbitre, 'Aucune preuve de remise.');

        $this->assertSame('remboursee', $c->fresh()->etat);
        $this->assertSame(0, $this->livre->solde(GrandLivre::SEQUESTRE));
        $this->assertSame(0, $this->livre->solde(GrandLivre::COMMISSION));
        $this->assertSame(0, $this->livre->solde(GrandLivre::compteVendeur($c->vendeur_id)));
        $this->assertTrue($this->livre->estEquilibre());
    }

    public function test_un_litige_tranche_pour_le_vendeur_solde_la_commande(): void
    {
        $c = $this->commandeRecue(20);

        $litige = $this->litiges->ouvrir(
            $c, $this->acheteur->utilisateur, 'autre', 'Contestation du poids.'
        );

        $this->litiges->trancherPourVendeur($litige, $this->arbitre, 'Bon de pesée conforme.');

        $this->assertSame('soldee', $c->fresh()->etat);
        $this->assertSame(6_720, $this->livre->solde(GrandLivre::COMMISSION));
        $this->assertSame(77_280, $this->livre->solde(GrandLivre::compteVendeur($c->vendeur_id)));
        $this->assertTrue($this->livre->estEquilibre());
    }

    /** Une fois l'argent versé, il n'y a plus rien à arbitrer. */
    public function test_on_nouvre_pas_de_litige_sur_une_commande_soldee(): void
    {
        $c = $this->commandeRecue(20);
        $this->reversements->solderLesCommandesRecues($c->vendeur);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('l\'argent n\'est plus retenu');
        $this->litiges->ouvrir($c->fresh(), $this->acheteur->utilisateur, 'autre', 'Trop tard.');
    }

    public function test_un_seul_litige_a_la_fois(): void
    {
        $c = $this->commandeRecue(20);
        $this->litiges->ouvrir($c, $this->acheteur->utilisateur, 'autre', 'Premier.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('déjà ouvert');
        $this->litiges->ouvrir($c->fresh(), $this->acheteur->utilisateur, 'autre', 'Second.');
    }

    /**
     * Un virement qui échoue remet la dette, par écriture inverse.
     *
     * On ne modifie pas l'écriture d'origine : la trace de la tentative est
     * précisément ce qu'on demandera si le vendeur conteste.
     */
    public function test_un_virement_echoue_remet_la_dette(): void
    {
        $c = $this->commandeRecue(20);
        $vendeur = $c->vendeur;
        $this->reversements->solderLesCommandesRecues($vendeur);
        $reversement = $this->reversements->preparer($vendeur);

        $this->reversements->echouer($reversement, 'Numéro Wave erroné');

        $this->assertSame('echoue', $reversement->fresh()->etat);
        $this->assertSame(77_280, $this->livre->solde(GrandLivre::compteVendeur($vendeur->id)));
        $this->assertTrue($this->livre->estEquilibre());
    }

    public function test_on_ne_reverse_rien_quand_rien_nest_du(): void
    {
        $vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rien n\'est dû');
        $this->reversements->preparer($vendeur);
    }
}
