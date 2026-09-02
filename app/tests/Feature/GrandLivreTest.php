<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Article;
use App\Models\Commande;
use App\Models\Ecriture;
use App\Models\Offre;
use App\Models\User;
use App\Services\CommandeService;
use App\Services\GrandLivre;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Le grand livre — le cœur du sujet.
 *
 * Ces tests ne vérifient pas des écrans : ils vérifient que l'argent ne se perd
 * pas et ne se crée pas. Ce sont eux qu'il faut exécuter devant un jury.
 */
class GrandLivreTest extends TestCase
{
    use RefreshDatabase;

    private GrandLivre $livre;
    private CommandeService $commandes;
    private Acheteur $acheteur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        $this->livre = app(GrandLivre::class);
        $this->commandes = app(CommandeService::class);

        $u = User::create(['name' => 'Modou FALL', 'email' => 'modou@chantier.sn', 'password' => 'password']);
        $this->acheteur = Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 111 22 33',
        ]);
    }

    private function commande(int $barres = 20): Commande
    {
        $t10 = Article::where('reference', 'T10-12M')->firstOrFail();
        $offre = Offre::where('article_id', $t10->id)->orderBy('id')->firstOrFail();

        return $this->commandes->creer($this->acheteur, [
            ['offre' => $offre, 'quantite' => $barres, 'unite' => 'barre'],
        ]);
    }

    /** Le parcours complet de l'argent, de l'encaissement au reversement. */
    public function test_le_cycle_complet_laisse_le_livre_equilibre(): void
    {
        $c = $this->commande(20);                       // 84 000 F, commission 6 720 F
        $c = $this->commandes->marquerPayee($c);

        $this->livre->encaisser($c, $c->montant_total, fraisOperateur: 1_500);

        $this->assertSame(84_000, $this->livre->solde(GrandLivre::SEQUESTRE));
        $this->assertSame(82_500, $this->livre->solde('wave'));      // 84 000 − 1 500
        $this->assertSame(0, $this->livre->solde(GrandLivre::COMMISSION), 'aucun revenu avant la réception');

        $c = $this->commandes->accepter($c);
        $c = $this->commandes->marquerPrete($c);
        $c = $this->commandes->remettre($c);            // retrait : vaut réception

        $this->livre->solder($c);

        $this->assertSame(0, $this->livre->solde(GrandLivre::SEQUESTRE));
        $this->assertSame(6_720, $this->livre->solde(GrandLivre::COMMISSION));
        $this->assertSame(77_280, $this->livre->solde(GrandLivre::compteVendeur($c->vendeur_id)));

        $this->livre->reverser($c->vendeur_id, 77_280);

        $this->assertSame(0, $this->livre->solde(GrandLivre::compteVendeur($c->vendeur_id)));
        $this->assertSame(5_220, $this->livre->solde('wave'));       // 82 500 − 77 280
        $this->assertTrue($this->livre->estEquilibre());
    }

    /**
     * Le séquestre n'est pas un revenu.
     *
     * C'est la confusion la plus fréquente, et la plus grave : elle ferait
     * apparaître comme chiffre d'affaires de l'argent qui appartient encore aux
     * acheteurs.
     */
    public function test_le_sequestre_nest_pas_un_revenu(): void
    {
        $c = $this->commandes->marquerPayee($this->commande(20));
        $this->livre->encaisser($c, $c->montant_total);

        $this->assertSame(84_000, $this->livre->solde(GrandLivre::SEQUESTRE));
        $this->assertSame(0, $this->livre->solde(GrandLivre::COMMISSION));
        $this->assertTrue($this->livre->sequestreJustifie());
    }

    public function test_une_operation_desequilibree_est_refusee(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('déséquilibrée');

        $this->livre->enregistrer('essai', [
            ['compte' => 'wave', 'sens' => 'debit', 'montant' => 1_000],
            ['compte' => GrandLivre::SEQUESTRE, 'sens' => 'credit', 'montant' => 900],
        ], 'Essai');

        $this->assertSame(0, Ecriture::count(), 'rien ne doit être écrit');
    }

    /** Le remboursement ne laisse aucune commission derrière lui. */
    public function test_un_litige_rembourse_sans_commission(): void
    {
        $c = $this->commandes->marquerPayee($this->commande(20));
        $this->livre->encaisser($c, $c->montant_total, fraisOperateur: 1_500);

        $this->livre->rembourser($c);

        $this->assertSame(0, $this->livre->solde(GrandLivre::SEQUESTRE));
        $this->assertSame(0, $this->livre->solde(GrandLivre::COMMISSION));
        // La plateforme perd les frais de l'opérateur : c'est le coût du service.
        $this->assertSame(1_500, $this->livre->solde(GrandLivre::FRAIS));
        $this->assertSame(-1_500, $this->livre->solde('wave'));
        $this->assertTrue($this->livre->estEquilibre());
    }

    public function test_on_ne_reverse_pas_plus_que_ce_qui_est_du(): void
    {
        $c = $this->commandes->marquerPayee($this->commande(10));
        $this->livre->encaisser($c, $c->montant_total);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('seulement est dû');
        $this->livre->reverser($c->vendeur_id, 1_000_000);
    }

    /** Les quatre invariants, vérifiés ensemble après plusieurs commandes. */
    public function test_les_quatre_invariants_tiennent(): void
    {
        // Une commande soldée et reversée.
        $a = $this->commandes->marquerPayee($this->commande(10));
        $this->livre->encaisser($a, $a->montant_total, fraisOperateur: 800);
        $a = $this->commandes->remettre($this->commandes->marquerPrete($this->commandes->accepter($a)));
        $this->livre->solder($a);
        $a = $this->commandes->marquerSoldee($a);
        $this->livre->reverser($a->vendeur_id, $a->montantVendeur());

        // Une commande payée, encore en séquestre.
        $b = $this->commandes->marquerPayee($this->commande(5));
        $this->livre->encaisser($b, $b->montant_total);

        // Une commande remboursée.
        $d = $this->commandes->marquerPayee($this->commande(3));
        $this->livre->encaisser($d, $d->montant_total);
        $this->livre->rembourser($d);
        $this->commandes->annuler($d, 'Litige tranché pour l\'acheteur');

        $this->assertTrue($this->livre->estEquilibre(), 'le livre doit être équilibré');
        $this->assertSame([], $this->livre->operationsDesequilibrees(), 'chaque opération doit l\'être aussi');
        $this->assertSame([], $this->livre->dettesNegatives(), 'aucune dette négative');
        $this->assertTrue($this->livre->sequestreJustifie(), 'le séquestre doit correspondre aux commandes en cours');

        // Seule la commande b reste en séquestre.
        $this->assertSame($b->montant_total, $this->livre->solde(GrandLivre::SEQUESTRE));
    }

    /**
     * L'immuabilité est garantie par la base, pas par le code applicatif.
     *
     * Une commande SQL lancée depuis un client d'administration doit se heurter
     * au même refus qu'un appel passé par l'application.
     */
    public function test_une_ecriture_ne_peut_pas_etre_modifiee(): void
    {
        $c = $this->commandes->marquerPayee($this->commande(5));
        $this->livre->encaisser($c, $c->montant_total);

        $this->expectException(QueryException::class);
        DB::statement('UPDATE ecritures SET montant = 1 WHERE id = (SELECT MIN(id) FROM ecritures)');
    }

    public function test_une_ecriture_ne_peut_pas_etre_supprimee(): void
    {
        $c = $this->commandes->marquerPayee($this->commande(5));
        $this->livre->encaisser($c, $c->montant_total);

        $this->expectException(QueryException::class);
        DB::statement('DELETE FROM ecritures WHERE id = (SELECT MIN(id) FROM ecritures)');
    }
}
