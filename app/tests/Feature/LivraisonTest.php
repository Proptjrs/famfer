<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Offre;
use App\Models\User;
use App\Models\Vendeur;
use App\Services\CommandeService;
use App\Services\LivraisonService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Les frais de livraison.
 *
 * La colonne existait depuis le début et valait zéro : la plateforme faisait
 * livrer du fer gratuitement. Ce qui est éprouvé ici, ce n'est pas le barème —
 * il se négocie — mais les trois propriétés qui doivent tenir quel qu'il soit :
 * le poids pèse sur le prix, la commission ne mord pas sur le transport, et le
 * total reste entier.
 */
class LivraisonTest extends TestCase
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

        $u = User::create(['name' => 'Chantier Yoff', 'email' => 'yoff@chantier.sn', 'password' => 'password']);
        $this->acheteur = Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 909 09 09',
            'latitude' => 14.7500, 'longitude' => -17.4700,
        ]);

        $this->vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();
        $this->offre = Offre::with('article.unitesVente')
            ->where('vendeur_id', $this->vendeur->id)->orderBy('id')
            ->get()->first(fn (Offre $o) => $o->disponiblePivot() > 0);
    }

    /** À distance égale, plus lourd coûte plus cher. C'est tout l'objet du barème. */
    public function test_le_poids_pese_sur_le_prix(): void
    {
        $l = app(LivraisonService::class);

        $leger = $l->frais(10.0, 50_000);        // 50 kg
        $lourd = $l->frais(10.0, 2_000_000);     // 2 tonnes

        $this->assertGreaterThan($leger, $lourd);

        // Et à poids égal, plus loin coûte plus cher.
        $this->assertGreaterThan($l->frais(2.0, 500_000), $l->frais(20.0, 500_000));
    }

    public function test_les_frais_sont_des_francs_entiers_arrondis_a_la_centaine(): void
    {
        $l = app(LivraisonService::class);

        foreach ([[3.7, 137_000], [11.3, 843_000], [42.1, 2_999_000]] as [$km, $g]) {
            $frais = $l->frais($km, $g);
            $this->assertIsInt($frais);
            $this->assertSame(0, $frais % 100, "«{$frais} F» n'est pas arrondi à la centaine.");
        }
    }

    /** Trois tonnes de fer ne montent pas dans une camionnette : on le dit avant. */
    public function test_au_dela_de_la_charge_utile_la_livraison_est_refusee(): void
    {
        $this->expectException(RuntimeException::class);
        app(LivraisonService::class)->frais(5.0, LivraisonService::CHARGE_MAX_G + 1);
    }

    public function test_hors_rayon_la_livraison_est_refusee(): void
    {
        $this->expectExceptionMessageMatches('/gré à gré/');
        app(LivraisonService::class)->frais(LivraisonService::RAYON_MAX_KM + 1, 100_000);
    }

    /**
     * La commission porte sur la marchandise, jamais sur le transport.
     *
     * C'est la propriété qui compte : sinon la plateforme prélèverait 8 % du
     * carburant que le vendeur avance, et le vendeur perdrait à livrer loin.
     */
    public function test_la_commission_ignore_les_frais_de_livraison(): void
    {
        $commande = app(CommandeService::class)->creer(
            $this->acheteur,
            [['offre' => $this->offre, 'quantite' => '2', 'unite' => $this->offre->unite_affichee]],
            'livraison', 'Cité Yoff, villa 44'
        );

        $this->assertGreaterThan(0, $commande->frais_livraison);
        $this->assertSame(
            $commande->montant_articles + $commande->frais_livraison,
            $commande->montant_total
        );

        $attendue = intdiv($commande->montant_articles * $commande->taux_commission_pour_mille, 1000);
        $this->assertSame($attendue, $commande->montant_commission);

        // Et le vendeur touche bien le transport en entier.
        $this->assertSame(
            $commande->montant_articles - $attendue + $commande->frais_livraison,
            $commande->montantVendeur()
        );
    }

    /** Un retrait au comptoir ne se facture pas. */
    public function test_le_retrait_ne_coute_rien(): void
    {
        $commande = app(CommandeService::class)->creer(
            $this->acheteur,
            [['offre' => $this->offre, 'quantite' => '1', 'unite' => $this->offre->unite_affichee]]
        );

        $this->assertSame(0, $commande->frais_livraison);
        $this->assertSame($commande->montant_articles, $commande->montant_total);
    }

    /** Sans point de livraison, on refuse plutôt que de facturer un forfait au hasard. */
    public function test_une_livraison_sans_adresse_reperee_est_refusee(): void
    {
        $sansPoint = Acheteur::create([
            'utilisateur_id' => User::create([
                'name' => 'Sans repère', 'email' => 'sans@chantier.sn', 'password' => 'password',
            ])->id,
            'genre' => 'particulier', 'telephone' => '+221 70 111 11 11',
        ]);

        $this->expectExceptionMessageMatches('/Indiquez où livrer/');
        app(CommandeService::class)->creer(
            $sansPoint,
            [['offre' => $this->offre, 'quantite' => '1', 'unite' => $this->offre->unite_affichee]],
            'livraison', 'Quelque part'
        );
    }

    /** Le panier annonce le montant que la commande retiendra — au franc près. */
    public function test_le_devis_du_panier_est_celui_qui_sera_facture(): void
    {
        $this->actingAs($this->acheteur->utilisateur)
            ->post(route('panier.ajouter', $this->offre), [
                'quantite' => '2', 'unite' => $this->offre->unite_affichee,
            ]);

        $reponse = $this->actingAs($this->acheteur->utilisateur)
            ->get(route('panier.voir', ['lat' => 14.75, 'lng' => -17.47]))
            ->assertOk();

        $devis = $reponse->viewData('devis')[$this->vendeur->id]['total'];

        $this->actingAs($this->acheteur->utilisateur)
            ->post(route('panier.valider'), [
                'mode_remise' => 'livraison', 'adresse' => 'Cité Yoff',
                'lat' => 14.75, 'lng' => -17.47,
            ])->assertRedirect();

        $this->assertSame($devis, \App\Models\Commande::orderByDesc('id')->first()->frais_livraison);
    }
}
