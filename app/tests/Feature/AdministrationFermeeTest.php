<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Litige;
use App\Models\Offre;
use App\Models\User;
use App\Models\Vendeur;
use App\Services\CommandeService;
use App\Services\GrandLivre;
use App\Services\LitigeService;
use App\Services\PaiementService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'administration n'est pas ouverte à qui est simplement connecté.
 *
 * Le groupe « administration/ » n'était gardé que par « auth ». La colonne
 * « est_admin » existait depuis le début et rien ne la lisait jamais : tout
 * compte connecté — un acheteur, un vendeur concurrent — pouvait vérifier sa
 * propre inscription, suspendre une autre quincaillerie, fixer sa commission à
 * zéro, et trancher un litige en sa faveur. Ce dernier point déplace de
 * l'argent réel du séquestre vers un compte.
 *
 * Ce fichier existe pour que cela ne puisse pas revenir. Chaque porte est
 * poussée séparément : une seule oubliée suffirait.
 */
class AdministrationFermeeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $intrus;
    private Vendeur $vendeur;
    private Acheteur $acheteur;
    private Offre $offre;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        // L'administrateur vient du semis : le recréer heurterait la
        // contrainte d'unicité sur l'adresse.
        $this->admin = User::firstWhere('est_admin', true);

        // Un vendeur vérifié : le profil le plus tentant, puisqu'il a un
        // intérêt direct à trancher les litiges qui le concernent.
        $this->vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();
        $this->intrus = $this->vendeur->utilisateur;

        $u = User::create([
            'name' => 'Awa BA', 'email' => 'awa@chantier.sn', 'password' => 'password',
        ]);
        $this->acheteur = Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 000 11 22',
        ]);

        $this->offre = Offre::with('article.unitesVente')
            ->where('vendeur_id', $this->vendeur->id)->orderBy('id')
            ->get()->first(fn (Offre $o) => $o->disponiblePivot() > 0);
    }

    /** Toutes les portes de l'administration, une par une. */
    public static function portes(): array
    {
        return [
            'tableau de bord' => ['get', 'admin.tableau', false],
            'vérifier une inscription' => ['post', 'admin.verifier', true],
            'refuser une inscription' => ['post', 'admin.refuser.vendeur', true],
            'fixer une commission' => ['put', 'admin.commission', true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('portes')]
    public function test_une_porte_de_ladministration_refuse_un_compte_ordinaire(
        string $verbe, string $route, bool $avecVendeur
    ): void {
        $url = $avecVendeur ? route($route, $this->vendeur) : route($route);

        $this->actingAs($this->intrus)->{$verbe}($url, [
            'motif' => 'Un motif quelconque, assez long.',
            'taux_pour_cent' => 0,
        ])->assertForbidden();
    }

    public function test_un_visiteur_non_connecte_est_renvoye_a_la_connexion(): void
    {
        $this->get(route('admin.tableau'))->assertRedirect(route('connexion'));
    }

    public function test_ladministration_elle_meme_passe(): void
    {
        $this->actingAs($this->admin)->get(route('admin.tableau'))->assertOk();
    }

    // ── Les conséquences, et non seulement le code de retour ─────────────────

    /**
     * Un vendeur ne se vérifie pas lui-même.
     *
     * C'est la porte la plus tentante : elle transforme une demande en attente
     * en commerce visible, sans que personne n'ait regardé le dossier.
     */
    public function test_un_vendeur_en_attente_ne_se_verifie_pas_lui_meme(): void
    {
        $enAttente = Vendeur::where('statut', 'en_attente')->orderBy('id')->firstOrFail();

        $this->actingAs($enAttente->utilisateur)
            ->post(route('admin.verifier', $enAttente))->assertForbidden();

        $this->assertSame('en_attente', $enAttente->fresh()->statut);
        $this->assertFalse($enAttente->fresh()->estVisible());
    }

    public function test_un_vendeur_ne_suspend_pas_un_concurrent(): void
    {
        $concurrent = Vendeur::where('statut', 'verifie')
            ->where('id', '!=', $this->vendeur->id)->orderBy('id')->firstOrFail();

        $this->actingAs($this->intrus)
            ->post(route('admin.refuser.vendeur', $concurrent), [
                'motif' => 'Concurrence déloyale, allègue-t-il.',
            ])->assertForbidden();

        $this->assertSame('verifie', $concurrent->fresh()->statut);
    }

    /**
     * Un litige ne se tranche pas par la partie intéressée.
     *
     * C'est la porte qui coûte le plus cher : trancher déplace l'argent du
     * séquestre. On vérifie donc le grand livre, pas seulement le statut.
     */
    public function test_un_vendeur_ne_tranche_pas_son_propre_litige(): void
    {
        $c = app(CommandeService::class)->creer($this->acheteur, [[
            'offre' => $this->offre, 'quantite' => '1', 'unite' => $this->offre->unite_affichee,
        ]]);
        app(PaiementService::class)->traiterRappel($c, 'wave', 'L-' . $c->reference, $c->montant_total);

        $u = $this->vendeur->utilisateur_id;
        app(CommandeService::class)->accepter($c->fresh(), $u);
        app(CommandeService::class)->marquerPrete($c->fresh(), $u);
        app(CommandeService::class)->remettre($c->fresh(), $u);

        app(LitigeService::class)->ouvrir(
            $c->fresh(), $this->acheteur->utilisateur,
            'article_non_conforme', 'Le fer livré n\'est pas celui commandé.'
        );

        $litige = Litige::where('commande_id', $c->id)->firstOrFail();
        $livre = app(GrandLivre::class);
        $sequestreAvant = $livre->solde('sequestre');
        $duAvant = $livre->solde('vendeur:' . $this->vendeur->id);

        $this->actingAs($this->intrus)
            ->post(route('admin.trancher', $litige), [
                'sens' => 'vendeur',
                'decision' => 'Je me donne raison à moi-même.',
            ])->assertForbidden();

        // Rien n'a bougé : ni le litige, ni un seul franc.
        $this->assertSame('ouvert', $litige->fresh()->etat);
        $this->assertSame($sequestreAvant, $livre->solde('sequestre'));
        $this->assertSame($duAvant, $livre->solde('vendeur:' . $this->vendeur->id));
        $this->assertTrue($livre->estEquilibre());
    }

    /** Un acheteur ordinaire n'entre pas davantage. */
    public function test_un_acheteur_nentre_pas(): void
    {
        $this->actingAs($this->acheteur->utilisateur)
            ->get(route('admin.tableau'))->assertForbidden();
    }
}
