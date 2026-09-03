<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Offre;
use App\Models\Reversement;
use App\Models\User;
use App\Models\Vendeur;
use App\Notifications\ChangementCompteVersement;
use App\Services\CommandeService;
use App\Services\GrandLivre;
use App\Services\PaiementService;
use App\Services\ReversementService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Où envoyer l'argent d'un vendeur.
 *
 * La page « Mon argent » annonçait que le virement partait « vers le compte
 * Wave ou Orange Money enregistré à votre nom ». Aucun champ ne portait ce
 * compte : la phrase promettait ce que la base ne pouvait pas tenir.
 *
 * Le test qui compte est celui du refus. Préparer un reversement passe une
 * écriture qui éteint la dette envers le vendeur ; la passer sans destination
 * effacerait ce qu'on lui doit sans le lui avoir versé — et le grand livre
 * resterait équilibré, donc la faute serait invisible.
 */
class CompteDeVersementTest extends TestCase
{
    use RefreshDatabase;

    private Vendeur $vendeur;
    private Acheteur $acheteur;
    private Offre $offre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        $u = User::create([
            'name' => 'Awa BA', 'email' => 'awa@chantier.sn', 'password' => 'password',
        ]);
        $this->acheteur = Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 000 11 22',
        ]);

        $this->vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();
        $this->offre = Offre::with('article.unitesVente')
            ->where('vendeur_id', $this->vendeur->id)->orderBy('id')
            ->get()->first(fn (Offre $o) => $o->disponiblePivot() > 0);
    }

    /** Mène une vente jusqu'à la réception, pour qu'une somme soit due. */
    private function venteRecue(): void
    {
        $c = app(CommandeService::class)->creer($this->acheteur, [[
            'offre' => $this->offre, 'quantite' => '1', 'unite' => $this->offre->unite_affichee,
        ]]);
        app(PaiementService::class)->traiterRappel($c, 'wave', 'V-' . $c->reference, $c->montant_total);

        $u = $this->vendeur->utilisateur_id;
        app(CommandeService::class)->accepter($c->fresh(), $u);
        app(CommandeService::class)->marquerPrete($c->fresh(), $u);
        app(CommandeService::class)->remettre($c->fresh(), $u);
    }

    // ── Le refus ─────────────────────────────────────────────────────────────

    /**
     * Sans destination, aucun virement — même quand une somme est due.
     *
     * C'est la propriété centrale : la dette du vendeur doit rester inscrite
     * tant qu'on ne sait pas où envoyer les fonds.
     */
    public function test_sans_compte_enregistre_le_virement_est_refuse(): void
    {
        $this->vendeur->update([
            'versement_operateur' => null, 'versement_numero' => null,
            'versement_titulaire' => null,
        ]);
        $this->venteRecue();

        $reversements = app(ReversementService::class);
        $reversements->solderLesCommandesRecues($this->vendeur->fresh());

        $du = app(GrandLivre::class)->solde('vendeur:' . $this->vendeur->id);
        $this->assertGreaterThan(0, $du, 'Une somme doit bien être due, sinon le test ne prouve rien.');

        try {
            $reversements->preparer($this->vendeur->fresh());
            $this->fail('Un virement sans destination aurait dû être refusé.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('compte de versement', $e->getMessage());
        }

        // Rien n'a bougé : la dette est intacte, aucun reversement créé.
        $this->assertSame($du, app(GrandLivre::class)->solde('vendeur:' . $this->vendeur->id));
        $this->assertSame(0, Reversement::where('vendeur_id', $this->vendeur->id)->count());
    }

    public function test_avec_un_compte_le_virement_part(): void
    {
        $this->venteRecue();

        $reversements = app(ReversementService::class);
        $reversements->solderLesCommandesRecues($this->vendeur);
        $rev = $reversements->preparer($this->vendeur->fresh());

        $this->assertGreaterThan(0, $rev->montant);
        $this->assertSame(0, app(GrandLivre::class)->solde('vendeur:' . $this->vendeur->id));
        $this->assertTrue(app(GrandLivre::class)->estEquilibre());
    }

    // ── L'enregistrement du compte ───────────────────────────────────────────

    public function test_le_vendeur_enregistre_son_compte(): void
    {
        $this->actingAs($this->vendeur->utilisateur)
            ->put(route('vendeur.versement'), [
                'versement_operateur' => 'om',
                'versement_numero' => '+221 78 123 45 67',
                'versement_titulaire' => 'Ndiaye et Frères SARL',
            ])->assertRedirect();

        $v = $this->vendeur->fresh();
        $this->assertSame('om', $v->versement_operateur);
        $this->assertSame('Ndiaye et Frères SARL', $v->versement_titulaire);
        $this->assertTrue($v->peutEtreVire());
        $this->assertNotNull($v->versement_modifie_le);
    }

    /** Un numéro qui n'en est pas un ne passe pas. */
    public function test_un_numero_invalide_est_refuse(): void
    {
        $avant = $this->vendeur->versement_numero;

        foreach (['12345', 'pas-un-numero', '+33 6 12 34 56 78'] as $mauvais) {
            $this->actingAs($this->vendeur->utilisateur)
                ->put(route('vendeur.versement'), [
                    'versement_operateur' => 'wave',
                    'versement_numero' => $mauvais,
                    'versement_titulaire' => 'Quelqu\'un',
                ])->assertSessionHasErrors('versement_numero');
        }

        $this->assertSame($avant, $this->vendeur->fresh()->versement_numero);
    }

    /**
     * Changer le compte prévient le titulaire.
     *
     * C'est le geste qu'un intrus ferait en premier : détourner la destination
     * des virements. Le courriel ne l'empêche pas, mais le vendeur l'apprend le
     * jour même plutôt que le jour où l'argent n'arrive pas.
     */
    public function test_un_changement_de_compte_est_signale(): void
    {
        Notification::fake();

        $this->actingAs($this->vendeur->utilisateur)
            ->put(route('vendeur.versement'), [
                'versement_operateur' => 'om',
                'versement_numero' => '78 999 88 77',
                'versement_titulaire' => 'Quelqu\'un d\'autre',
            ])->assertRedirect();

        Notification::assertSentTo(
            $this->vendeur->utilisateur, ChangementCompteVersement::class,
            fn (ChangementCompteVersement $n) => str_contains($n->ancien, 'WAVE')
                || str_contains($n->ancien, 'OM')
        );
    }

    /** Enregistrer le même compte deux fois ne prévient de rien. */
    public function test_un_enregistrement_identique_ne_previent_personne(): void
    {
        $v = $this->vendeur;
        Notification::fake();

        $this->actingAs($v->utilisateur)->put(route('vendeur.versement'), [
            'versement_operateur' => $v->versement_operateur,
            'versement_numero' => $v->versement_numero,
            'versement_titulaire' => $v->versement_titulaire,
        ])->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_un_vendeur_ne_touche_pas_au_compte_dun_autre(): void
    {
        $autre = Vendeur::where('statut', 'verifie')->where('id', '!=', $this->vendeur->id)
            ->orderBy('id')->firstOrFail();
        $avant = $autre->versement_numero;

        // Chacun n'agit que sur son propre commerce : la route ne prend pas
        // d'identifiant, elle lit le vendeur du compte connecté.
        $this->actingAs($this->vendeur->utilisateur)
            ->put(route('vendeur.versement'), [
                'versement_operateur' => 'wave',
                'versement_numero' => '70 000 00 00',
                'versement_titulaire' => 'Détournement',
            ])->assertRedirect();

        $this->assertSame($avant, $autre->fresh()->versement_numero);
    }
}
