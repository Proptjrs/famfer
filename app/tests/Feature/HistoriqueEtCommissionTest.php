<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Commande;
use App\Models\Offre;
use App\Models\User;
use App\Models\Vendeur;
use App\Services\CommandeService;
use App\Services\PaiementService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'historique du vendeur, et le taux négocié par l'administration.
 *
 * Deux manques de la même nature : une donnée existait, et rien ne permettait
 * de la voir ou de la changer. Le vendeur ne voyait que ses commandes à
 * traiter — dès qu'une commande était remise, elle disparaissait de son écran.
 * Et le taux de commission, prévu par vendeur dans la table, valait 8 % pour
 * tout le monde faute de moyen d'y toucher.
 */
class HistoriqueEtCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Vendeur $vendeur;
    private Acheteur $acheteur;
    private Offre $offre;
    private User $admin;

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

        // L'administrateur vient du semis : le recréer heurterait la
        // contrainte d'unicité sur l'adresse.
        $this->admin = User::firstWhere('est_admin', true);

        $this->vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();
        $this->offre = Offre::with('article.unitesVente')
            ->where('vendeur_id', $this->vendeur->id)->orderBy('id')
            ->get()->first(fn (Offre $o) => $o->disponiblePivot() > 0);
    }

    private function commande(bool $annuler = false): Commande
    {
        $c = app(CommandeService::class)->creer($this->acheteur, [[
            'offre' => $this->offre, 'quantite' => '1', 'unite' => $this->offre->unite_affichee,
        ]]);
        app(PaiementService::class)->traiterRappel(
            $c, 'wave', 'H-' . $c->reference, $c->montant_total
        );

        if ($annuler) {
            app(CommandeService::class)->annuler($c->fresh(), 'Rupture de stock');
        }

        return $c->fresh();
    }

    // ── L'historique ─────────────────────────────────────────────────────────

    /**
     * Une commande annulée reste consultable.
     *
     * C'est tout l'objet de la page : le tableau de bord ne montrait que
     * « payée, acceptée, prête », donc tout ce qui avait échoué s'évanouissait.
     */
    public function test_lhistorique_montre_aussi_ce_qui_na_pas_abouti(): void
    {
        $vivante = $this->commande();
        $morte = $this->commande(annuler: true);

        $reponse = $this->actingAs($this->vendeur->utilisateur)
            ->get(route('vendeur.commandes'))->assertOk();

        $references = collect($reponse->viewData('liste')->items())->pluck('reference');
        $this->assertTrue($references->contains($vivante->reference));
        $this->assertTrue($references->contains($morte->reference));

        // Et le motif de l'annulation est dit, pas seulement l'état.
        $reponse->assertSee('Rupture de stock');
    }

    public function test_le_filtre_par_etat_restreint_la_liste(): void
    {
        $this->commande();
        $annulee = $this->commande(annuler: true);

        $liste = $this->actingAs($this->vendeur->utilisateur)
            ->get(route('vendeur.commandes', ['etat' => 'annulee']))
            ->assertOk()->viewData('liste');

        $this->assertCount(1, $liste->items());
        $this->assertSame($annulee->reference, $liste->items()[0]->reference);
    }

    /** Un état inventé ne filtre rien plutôt que de renvoyer une liste vide. */
    public function test_un_etat_invente_est_ignore(): void
    {
        $this->commande();

        $liste = $this->actingAs($this->vendeur->utilisateur)
            ->get(route('vendeur.commandes', ['etat' => 'etat-qui-nexiste-pas']))
            ->assertOk()->viewData('liste');

        $this->assertCount(1, $liste->items());
    }

    public function test_un_vendeur_ne_voit_pas_les_commandes_dun_autre(): void
    {
        $mienne = $this->commande();
        $autre = Vendeur::where('statut', 'verifie')->where('id', '!=', $this->vendeur->id)
            ->orderBy('id')->firstOrFail();

        $liste = $this->actingAs($autre->utilisateur)
            ->get(route('vendeur.commandes'))->assertOk()->viewData('liste');

        $this->assertFalse(
            collect($liste->items())->pluck('reference')->contains($mienne->reference)
        );
    }

    // ── Le taux négocié ──────────────────────────────────────────────────────

    public function test_ladministration_fixe_un_taux(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.commission', $this->vendeur), ['taux_pour_cent' => 5.5])
            ->assertRedirect();

        // 5,5 % s'écrit 55 pour mille : l'entier évite tout arrondi flottant.
        $this->assertSame(55, $this->vendeur->fresh()->taux_commission_pour_mille);
    }

    public function test_un_taux_aberrant_est_refuse(): void
    {
        $avant = $this->vendeur->taux_commission_pour_mille;

        foreach ([-1, 25, 'gratuit'] as $mauvais) {
            $this->actingAs($this->admin)
                ->put(route('admin.commission', $this->vendeur), ['taux_pour_cent' => $mauvais])
                ->assertSessionHasErrors('taux_pour_cent');
        }

        $this->assertSame($avant, $this->vendeur->fresh()->taux_commission_pour_mille);
    }

    /**
     * Le nouveau taux ne vaut que pour l'avenir.
     *
     * Chaque commande fige le sien à sa création. Recalculer les anciennes
     * changerait après coup ce que le vendeur a déjà encaissé, et ferait mentir
     * le grand livre.
     */
    public function test_un_taux_change_ne_touche_pas_aux_commandes_passees(): void
    {
        $ancienne = $this->commande();
        $tauxFige = $ancienne->taux_commission_pour_mille;
        $commissionFigee = $ancienne->montant_commission;

        $this->actingAs($this->admin)
            ->put(route('admin.commission', $this->vendeur), ['taux_pour_cent' => 2]);

        $this->assertSame($tauxFige, $ancienne->fresh()->taux_commission_pour_mille);
        $this->assertSame($commissionFigee, $ancienne->fresh()->montant_commission);

        // Mais la suivante prend le nouveau. On relit l'offre : son vendeur a
        // été chargé au premier passage, et l'instance en mémoire porte encore
        // l'ancien taux — ce que la vraie application ne connaît pas, chaque
        // requête repartant de la base.
        $this->offre = $this->offre->fresh();
        $this->assertSame(20, $this->commande()->taux_commission_pour_mille);
    }

    public function test_un_vendeur_ne_fixe_pas_sa_propre_commission(): void
    {
        $avant = $this->vendeur->taux_commission_pour_mille;

        $this->actingAs($this->vendeur->utilisateur)
            ->put(route('admin.commission', $this->vendeur), ['taux_pour_cent' => 0])
            ->assertForbidden();

        $this->assertSame($avant, $this->vendeur->fresh()->taux_commission_pour_mille);
    }

    /** L'administration voit les maisons vérifiées, et leur compte de versement. */
    public function test_ladministration_voit_les_maisons(): void
    {
        $reponse = $this->actingAs($this->admin)->get(route('admin.tableau'))->assertOk();

        $this->assertGreaterThan(0, $reponse->viewData('maisons')->total());
        $reponse->assertSee($this->vendeur->raison_sociale);
    }
}
