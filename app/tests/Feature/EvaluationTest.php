<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Commande;
use App\Models\Offre;
use App\Models\User;
use App\Models\Vendeur;
use App\Services\CommandeService;
use App\Services\EvaluationService;
use App\Services\PaiementService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * La note d'un vendeur.
 *
 * Ce qui est éprouvé ici n'est pas l'affichage des étoiles, mais ce qui donne
 * sa valeur à une note : elle n'existe qu'adossée à une commande reçue, elle ne
 * s'écrit qu'une fois, et la moyenne affichée est toujours recalculée depuis les
 * avis — jamais saisie.
 */
class EvaluationTest extends TestCase
{
    use RefreshDatabase;

    private Acheteur $acheteur;
    private Vendeur $vendeur;
    private Offre $offre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        $utilisateur = User::create([
            'name' => 'Awa BA', 'email' => 'awa@chantier.sn', 'password' => 'password',
        ]);
        $this->acheteur = Acheteur::create([
            'utilisateur_id' => $utilisateur->id, 'genre' => 'chantier',
            'telephone' => '+221 77 333 22 11',
        ]);

        $this->vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();
        $this->offre = Offre::with('article.unitesVente')
            ->where('vendeur_id', $this->vendeur->id)->orderBy('id')
            ->get()->first(fn (Offre $o) => $o->disponiblePivot() > 0);
    }

    /** Mène une commande jusqu'à l'état voulu, par les services. */
    private function commande(string $jusqua = 'receptionnee'): Commande
    {
        $commandes = app(CommandeService::class);

        $c = $commandes->creer($this->acheteur, [[
            'offre' => $this->offre, 'quantite' => '1', 'unite' => $this->offre->unite_affichee,
        ]]);

        if ($jusqua === 'en_attente_paiement') {
            return $c->fresh();
        }

        app(PaiementService::class)->traiterRappel(
            $c, 'wave', 'T-' . $c->reference, $c->montant_total
        );

        if ($jusqua === 'payee') {
            return $c->fresh();
        }

        $u = $this->vendeur->utilisateur_id;
        $commandes->accepter($c->fresh(), $u);
        $commandes->marquerPrete($c->fresh(), $u);
        $commandes->remettre($c->fresh(), $u);

        // En retrait au comptoir, la remise vaut réception : la marchandise
        // change de mains devant le vendeur. Il n'y a rien de plus à confirmer.
        if ($c->fresh()->etat === 'en_livraison') {
            $commandes->confirmerReception($c->fresh(), $this->acheteur->utilisateur_id);
        }

        return $c->fresh();
    }

    public function test_la_note_recalcule_la_moyenne_du_vendeur(): void
    {
        // Rien n'est semé : un vendeur neuf n'a pas de note, il n'a pas zéro.
        $this->assertNull($this->vendeur->note_sur_cent);
        $this->assertNull($this->vendeur->noteSurCinq());

        app(EvaluationService::class)->noter($this->commande(), 4);
        $this->assertSame(80, $this->vendeur->fresh()->note_sur_cent);

        app(EvaluationService::class)->noter($this->commande(), 5);

        // Moyenne de 4 et 5 : 4,5 sur cinq, donc 90 sur cent.
        $v = $this->vendeur->fresh();
        $this->assertSame(90, $v->note_sur_cent);
        $this->assertSame(2, $v->nombre_evaluations);
        $this->assertSame(4.5, $v->noteSurCinq());
    }

    /** Sans achat, une note ne vaut rien : c'est ce qui protège le classement. */
    public function test_une_commande_non_recue_ne_se_note_pas(): void
    {
        $this->expectException(RuntimeException::class);
        app(EvaluationService::class)->noter($this->commande('payee'), 5);
    }

    public function test_une_commande_ne_se_note_quune_fois(): void
    {
        $c = $this->commande();
        app(EvaluationService::class)->noter($c, 5);

        try {
            app(EvaluationService::class)->noter($c->fresh(), 1);
            $this->fail('Une seconde note aurait dû être refusée.');
        } catch (RuntimeException $e) {
            // La moyenne n'a pas bougé : le refus est intervenu avant l'écriture.
            $this->assertSame(100, $this->vendeur->fresh()->note_sur_cent);
            $this->assertSame(1, $this->vendeur->fresh()->nombre_evaluations);
        }
    }

    public function test_la_note_reste_dans_le_bareme(): void
    {
        foreach ([0, 6, -1] as $horsBareme) {
            try {
                app(EvaluationService::class)->noter($this->commande(), $horsBareme);
                $this->fail("La note {$horsBareme} aurait dû être refusée.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('1 à 5', $e->getMessage());
            }
        }
    }

    /** Le parcours web complet, du bouton à l'étoile affichée. */
    public function test_lacheteur_note_depuis_ses_commandes(): void
    {
        $c = $this->commande();

        $this->actingAs($this->acheteur->utilisateur)
            ->post(route('acheteur.noter', $c), [
                'note' => 5, 'commentaire' => 'Fer conforme, pesé devant moi.',
            ])->assertRedirect();

        $this->assertSame(100, $this->vendeur->fresh()->note_sur_cent);

        // L'avis apparaît sur la fiche publique du vendeur.
        $this->get(route('vendeur.public', $this->vendeur))
            ->assertOk()
            ->assertSee('Fer conforme, pesé devant moi.');
    }

    /** Un acheteur ne note pas la commande d'un autre. */
    public function test_on_ne_note_pas_la_commande_dun_autre(): void
    {
        $c = $this->commande();

        $intrus = User::create([
            'name' => 'Intrus', 'email' => 'intrus@famfer.sn', 'password' => 'password',
        ]);
        Acheteur::create([
            'utilisateur_id' => $intrus->id, 'genre' => 'particulier', 'telephone' => '+221 70 000 00 00',
        ]);

        $this->actingAs($intrus)
            ->post(route('acheteur.noter', $c), ['note' => 1])
            ->assertForbidden();

        $this->assertNull($this->vendeur->fresh()->note_sur_cent);
    }

    /** Une maison non vérifiée n'a pas de vitrine publique. */
    public function test_la_fiche_dun_vendeur_non_verifie_est_introuvable(): void
    {
        $enAttente = Vendeur::where('statut', '!=', 'verifie')->orderBy('id')->first();
        $this->assertNotNull($enAttente, 'Le seeder doit contenir une maison non vérifiée.');

        $this->get(route('vendeur.public', $enAttente))->assertNotFound();
    }
}
