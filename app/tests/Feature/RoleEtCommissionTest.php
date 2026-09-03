<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Commande;
use App\Models\Offre;
use App\Models\User;
use App\Models\Vendeur;
use App\Services\CommandeService;
use App\Services\PaiementService;
use App\Services\ReversementService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le rôle porté par un compte, et ce que la plateforme prélève.
 *
 * Rien n'indiquait à un utilisateur ce qu'il était : acheteur, vendeur en
 * attente, commerçant vérifié. Il fallait le deviner à la présence d'un lien
 * dans la barre du haut. Et un vendeur ne voyait nulle part ce que FamFer
 * retenait sur ses ventes, alors que la plateforme encaisse à sa place.
 *
 * Ce qui est éprouvé ici n'est pas la mise en page mais le chiffre : la
 * commission affichée doit être celle réellement portée par le grand livre,
 * sans quoi la page rassure au lieu d'informer.
 */
class RoleEtCommissionTest extends TestCase
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

    // ── Le rôle, tel que le compte l'annonce ─────────────────────────────────

    public function test_un_compte_simple_est_annonce_acheteur_et_invite_a_vendre(): void
    {
        $this->actingAs($this->acheteur->utilisateur)
            ->get(route('compte'))->assertOk()
            ->assertSee('Mon rôle sur FamFer')
            ->assertSee('Acheteur')
            ->assertSee('Vendre sur FamFer');
    }

    public function test_un_vendeur_verifie_voit_son_statut_et_sa_maison(): void
    {
        $this->actingAs($this->vendeur->utilisateur)
            ->get(route('compte'))->assertOk()
            ->assertSee('Vendeur vérifié')
            ->assertSee($this->vendeur->raison_sociale)
            ->assertSee('Commission FamFer');
    }

    public function test_un_vendeur_en_attente_sait_quil_attend(): void
    {
        $enAttente = Vendeur::where('statut', 'en_attente')->orderBy('id')->first();
        $this->assertNotNull($enAttente, 'Le seeder doit contenir une demande en attente.');

        $this->actingAs($enAttente->utilisateur)
            ->get(route('compte'))->assertOk()
            ->assertSee('vérification en cours')
            // On lui dit quoi faire en attendant, plutôt que de le laisser sans suite.
            ->assertSee('Préparer mes offres');
    }

    /** Un compte sans rôle d'administration ne se voit pas administrateur. */
    public function test_le_badge_administration_ne_sinvente_pas(): void
    {
        $this->actingAs($this->acheteur->utilisateur)
            ->get(route('compte'))->assertOk()
            ->assertDontSee('Administration');
    }

    // ── Ce que la plateforme prélève ─────────────────────────────────────────

    /** Mène une vente jusqu'au solde, en passant par tous les services. */
    private function venteAboutie(): Commande
    {
        $c = app(CommandeService::class)->creer($this->acheteur, [[
            'offre' => $this->offre, 'quantite' => '1', 'unite' => $this->offre->unite_affichee,
        ]]);

        app(PaiementService::class)->traiterRappel(
            $c, 'wave', 'T-' . $c->reference, $c->montant_total
        );

        $u = $this->vendeur->utilisateur_id;
        app(CommandeService::class)->accepter($c->fresh(), $u);
        app(CommandeService::class)->marquerPrete($c->fresh(), $u);
        app(CommandeService::class)->remettre($c->fresh(), $u);

        // Le solde fait basculer la commission de dette en revenu.
        app(ReversementService::class)->solderLesCommandesRecues($this->vendeur);

        return $c->fresh();
    }

    /**
     * Le chiffre affiché est celui du grand livre, pas une approximation.
     *
     * C'est tout l'intérêt du contrôle : une page qui annoncerait une
     * commission calculée autrement que celle réellement prélevée serait pire
     * que pas de page du tout.
     */
    public function test_la_commission_affichee_est_celle_du_grand_livre(): void
    {
        $c = $this->venteAboutie();
        $this->assertSame('soldee', $c->etat);

        $reponse = $this->actingAs($this->vendeur->utilisateur)
            ->get(route('vendeur.argent'))->assertOk();

        $chiffres = $reponse->viewData('chiffres');

        $this->assertSame($c->montant_total, $chiffres['chiffre_affaires']);
        $this->assertSame($c->montant_commission, $chiffres['commission_versee']);
        $this->assertSame($c->montantVendeur(), $chiffres['net_percu']);

        // Et les trois s'additionnent : le brut moins la commission fait le net.
        $this->assertSame(
            $chiffres['chiffre_affaires'] - $chiffres['commission_versee'],
            $chiffres['net_percu']
        );

        // La commission acquise par la plateforme est bien la même somme.
        $this->assertSame(
            $c->montant_commission,
            app(\App\Services\GrandLivre::class)->solde('commission')
        );
    }

    /** Une vente annulée ne coûte rien : c'est ce que la page promet. */
    public function test_une_vente_annulee_ne_prend_aucune_commission(): void
    {
        $c = app(CommandeService::class)->creer($this->acheteur, [[
            'offre' => $this->offre, 'quantite' => '1', 'unite' => $this->offre->unite_affichee,
        ]]);
        app(PaiementService::class)->traiterRappel($c, 'wave', 'A-' . $c->reference, $c->montant_total);
        app(CommandeService::class)->annuler($c->fresh(), 'Refusée par le vendeur');

        $chiffres = $this->actingAs($this->vendeur->utilisateur)
            ->get(route('vendeur.argent'))->viewData('chiffres');

        $this->assertSame(0, $chiffres['commission_versee']);
        $this->assertSame(0, app(\App\Services\GrandLivre::class)->solde('commission'));
    }

    /** Le compte d'un vendeur montre le même chiffre que sa page d'argent. */
    public function test_les_deux_pages_disent_la_meme_chose(): void
    {
        $this->venteAboutie();

        $argent = $this->actingAs($this->vendeur->utilisateur)
            ->get(route('vendeur.argent'))->viewData('chiffres');
        $compte = $this->actingAs($this->vendeur->utilisateur)
            ->get(route('compte'))->viewData('commission');

        $this->assertSame($argent['commission_versee'], $compte['commission_versee']);
        $this->assertSame($argent['net_percu'], $compte['net_percu']);
    }

    /** Un acheteur qui n'est pas vendeur n'a pas de bloc de commission. */
    public function test_un_acheteur_na_pas_de_commission_a_voir(): void
    {
        $this->assertNull(
            $this->actingAs($this->acheteur->utilisateur)
                ->get(route('compte'))->viewData('commission')
        );
    }
}
