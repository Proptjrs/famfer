<?php

namespace Tests\Feature;

use App\Models\Adresse;
use App\Models\Boutique;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use App\Services\Commissions;
use App\Services\Panier;
use App\Services\PasseCommande;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le revenu de la plateforme.
 *
 * La place de marché ne gagnait rien : aucune commission nulle part, ni sur la
 * boutique, ni sur la commande. Une place de marché sans revenu n'est pas une
 * place de marché, c'est un annuaire.
 *
 * Le paiement à la livraison inverse le sens du flux par rapport à un séquestre.
 * C'est le vendeur qui encaisse les espèces ; la plateforme ne voit jamais
 * l'argent et ne peut donc rien retenir — elle facture. Ces essais vérifient les
 * trois propriétés qui font tenir ce modèle : le taux est figé à la commande, la
 * commission n'est due qu'à la livraison, et elle ne porte pas sur le port.
 */
class CommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private Boutique $boutique;
    private Produit $produit;
    private Adresse $adresse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);

        $this->client = User::create([
            'name' => 'Awa BA', 'email' => 'awa@essai.sn',
            'password' => 'motdepasse', 'role' => 'client', 'telephone' => '+221 77 000 00 00',
        ]);
        $this->adresse = Adresse::create([
            'utilisateur_id' => $this->client->id, 'destinataire' => 'Awa BA',
            'telephone' => '+221 77 000 00 00', 'region' => 'Dakar',
            'ville' => 'Dakar', 'quartier' => 'Grand Yoff', 'par_defaut' => true,
        ]);

        $this->boutique = Boutique::where('statut', 'active')->orderBy('id')->firstOrFail();
        $this->produit = Produit::where('boutique_id', $this->boutique->id)
            ->where('stock', '>', 3)->where('actif', true)->orderBy('id')->firstOrFail();
    }

    private function commander(int $quantite = 1): Commande
    {
        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit->fresh(), $quantite);

        return app(PasseCommande::class)->creer($this->client, $this->adresse);
    }

    private function livrer(Commande $c): Commande
    {
        $passe = app(PasseCommande::class);
        $passe->expedier($c);
        $passe->mettreEnLivraison($c->fresh());

        return $passe->livrer($c->fresh(), $c->fresh()->code_livraison);
    }

    // ── Le calcul ────────────────────────────────────────────────────────────

    /** La commission existe, et elle vaut le taux de la boutique. */
    public function test_la_commande_porte_une_commission(): void
    {
        $c = $this->commander(2);

        $attendu = intdiv(
            $c->sous_total * $this->boutique->taux_commission_pour_mille, 1000
        );

        $this->assertGreaterThan(0, $c->commission);
        $this->assertSame($attendu, $c->commission);
    }

    /**
     * La commission ne porte pas sur les frais de livraison.
     *
     * Le port couvre une tournée que le vendeur paie de sa poche : en prélever
     * une part reviendrait à taxer son carburant.
     */
    public function test_la_commission_ignore_les_frais_de_livraison(): void
    {
        $c = $this->commander();

        $this->assertGreaterThan(0, $c->frais_livraison,
            'Il faut des frais de port pour que cet essai prouve quelque chose.');

        $surTotal = intdiv($c->total * $c->taux_commission_pour_mille, 1000);

        $this->assertLessThan($surTotal, $c->commission);
        $this->assertSame(
            intdiv($c->sous_total * $this->boutique->taux_commission_pour_mille, 1000),
            $c->commission
        );
    }

    /** La somme des lignes fait la commission de la commande. */
    public function test_les_lignes_portent_leur_part(): void
    {
        $c = $this->commander(3);

        $this->assertSame($c->commission, (int) $c->lignes->sum('commission'));
    }

    /**
     * Le taux est figé : le renégocier ne refacture pas le passé.
     *
     * C'est la même règle que pour le prix et l'adresse. Une place de marché qui
     * réécrit ses factures d'hier quand elle change son tarif d'aujourd'hui
     * n'est pas défendable devant un commerçant.
     */
    public function test_renegocier_le_taux_ne_touche_pas_le_passe(): void
    {
        $c = $this->commander();
        $avant = $c->commission;

        $this->boutique->update(['taux_commission_pour_mille' => 200]);

        $this->assertSame($avant, $c->fresh()->commission);

        // Mais la commande suivante paie le nouveau taux.
        $suivante = $this->commander();
        $this->assertGreaterThan($avant, $suivante->commission);
    }

    /** Chaque boutique applique son propre taux sur un panier partagé. */
    public function test_un_panier_multi_boutiques_applique_chaque_taux(): void
    {
        $autre = Boutique::where('statut', 'active')
            ->where('id', '!=', $this->boutique->id)->orderBy('id')->firstOrFail();

        $this->boutique->update(['taux_commission_pour_mille' => 50]);
        $autre->update(['taux_commission_pour_mille' => 150]);

        $second = Produit::where('boutique_id', $autre->id)
            ->where('stock', '>', 2)->where('actif', true)->orderBy('id')->firstOrFail();

        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit->fresh(), 1);
        app(Panier::class)->ajouter($second, 1);
        $c = app(PasseCommande::class)->creer($this->client, $this->adresse);

        $parBoutique = $c->lignes->keyBy('boutique_id');

        $this->assertSame(
            intdiv($parBoutique[$this->boutique->id]->montant * 50, 1000),
            $parBoutique[$this->boutique->id]->commission
        );
        $this->assertSame(
            intdiv($parBoutique[$autre->id]->montant * 150, 1000),
            $parBoutique[$autre->id]->commission
        );

        // Le taux de la commande est la moyenne réellement appliquée, pas un
        // des deux taux choisi arbitrairement.
        $this->assertGreaterThan(50, $c->taux_commission_pour_mille);
        $this->assertLessThan(150, $c->taux_commission_pour_mille);
    }

    // ── L'exigibilité ────────────────────────────────────────────────────────

    public function test_rien_nest_du_avant_la_livraison(): void
    {
        $this->commander();

        $this->assertSame(0, app(Commissions::class)
            ->pourBoutique($this->boutique)['commission']);
    }

    public function test_la_livraison_rend_la_commission_exigible(): void
    {
        $c = $this->livrer($this->commander());

        $this->assertSame($c->commission, app(Commissions::class)
            ->pourBoutique($this->boutique)['commission']);
    }

    /**
     * Un refus à la porte ne coûte rien au vendeur.
     *
     * Il a déjà perdu la tournée ; lui facturer en plus la commission d'une
     * vente qui n'a pas eu lieu serait le punir deux fois.
     */
    public function test_un_refus_neffface_pas_seulement_la_vente_mais_la_commission(): void
    {
        $c = $this->commander();
        app(PasseCommande::class)->expedier($c);
        app(PasseCommande::class)->refuser($c->fresh(), 'Client absent.');

        $this->assertSame('refusee', $c->fresh()->etat);
        $this->assertSame(0, app(Commissions::class)
            ->pourBoutique($this->boutique)['commission']);
    }

    public function test_un_retour_efface_la_commission(): void
    {
        $c = $this->livrer($this->commander());
        app(PasseCommande::class)->retourner($c->fresh(), 'Diamètre erroné.');

        $this->assertSame(0, app(Commissions::class)
            ->pourBoutique($this->boutique)['commission']);
    }

    public function test_une_annulation_ne_coute_rien(): void
    {
        $c = $this->commander();
        app(PasseCommande::class)->annuler($c, 'Rupture en magasin.');

        $this->assertSame(0, app(Commissions::class)
            ->pourBoutique($this->boutique)['commission']);
    }

    // ── Le décompte ──────────────────────────────────────────────────────────

    /** Le net du vendeur : tout l'encaissé, moins la seule commission. */
    public function test_le_net_du_vendeur_est_coherent(): void
    {
        $c = $this->livrer($this->commander(2));
        $compte = app(Commissions::class)->pourBoutique($this->boutique);

        $this->assertSame($c->total - $c->commission, $c->netVendeur());
        $this->assertSame($compte['encaisse'] - $compte['commission'], $compte['net']);
        $this->assertSame($c->total, $compte['encaisse']);
    }

    /**
     * Le port n'est pas compté deux fois sur une commande partagée.
     *
     * Une commande ne porte qu'un seul frais de livraison. L'attribuer en entier
     * à chacune des boutiques qu'elle traverse inventerait de l'argent.
     */
    public function test_le_port_dune_commande_partagee_nest_credite_a_personne(): void
    {
        $autre = Boutique::where('statut', 'active')
            ->where('id', '!=', $this->boutique->id)->orderBy('id')->firstOrFail();
        $second = Produit::where('boutique_id', $autre->id)
            ->where('stock', '>', 2)->where('actif', true)->orderBy('id')->firstOrFail();

        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit->fresh(), 1);
        app(Panier::class)->ajouter($second, 1);
        $c = app(PasseCommande::class)->creer($this->client, $this->adresse);
        $this->livrer($c);

        $service = app(Commissions::class);

        $this->assertSame(0, $service->pourBoutique($this->boutique)['port']);
        $this->assertSame(0, $service->pourBoutique($autre)['port']);
    }

    public function test_le_releve_mensuel_recapitule_le_mois(): void
    {
        $c = $this->livrer($this->commander());
        $releve = app(Commissions::class)->releveMensuel($this->boutique);

        $this->assertCount(1, $releve);
        $this->assertSame(now()->format('Y-m'), $releve->first()->periode);
        $this->assertSame($c->commission, (int) $releve->first()->commission);
    }

    /** Le taux moyen obtenu diverge du taux affiché dès qu'une enseigne négocie. */
    public function test_le_taux_moyen_est_celui_reellement_obtenu(): void
    {
        $this->livrer($this->commander());
        $chiffres = app(Commissions::class)->pourLaPlateforme();

        $this->assertGreaterThan(0, $chiffres['commission']);
        $this->assertEqualsWithDelta(
            $this->boutique->tauxPourCent(), $chiffres['taux_moyen'], 0.05
        );
    }

    public function test_la_commission_perdue_sur_refus_est_chiffree(): void
    {
        $c = $this->commander();
        $perdue = $c->commission;
        app(PasseCommande::class)->expedier($c);
        app(PasseCommande::class)->refuser($c->fresh(), 'Client absent.');

        $this->assertSame($perdue,
            app(Commissions::class)->pourLaPlateforme()['perdue_sur_refus']);
    }

    // ── Les écrans ───────────────────────────────────────────────────────────

    public function test_le_vendeur_voit_son_releve(): void
    {
        $c = $this->livrer($this->commander());

        $this->actingAs($this->boutique->utilisateur)
            ->get(route('vendeur.commissions'))
            ->assertOk()
            ->assertSee(number_format($c->commission, 0, ',', ' '));
    }

    /** Un compte sans boutique n'a pas de relevé — et se voit refuser, pas rediriger. */
    public function test_un_client_na_pas_de_releve(): void
    {
        $this->actingAs($this->client)
            ->get(route('vendeur.commissions'))
            ->assertForbidden();
    }

    public function test_ladministration_voit_le_revenu(): void
    {
        $admin = User::create([
            'name' => 'Administration', 'email' => 'admin@essai.sn',
            'password' => 'motdepasse', 'role' => 'admin', 'telephone' => '+221 33 800 00 00',
        ]);
        $c = $this->livrer($this->commander());

        $this->actingAs($admin)->get(route('admin.revenus'))->assertOk()
            ->assertSee(number_format($c->commission, 0, ',', ' '));

        $this->assertSame($c->commission, $this->actingAs($admin)
            ->get(route('admin.tableau'))->viewData('chiffres')['commission']);
    }

    public function test_un_vendeur_nentre_pas_dans_les_revenus(): void
    {
        $this->actingAs($this->boutique->utilisateur)
            ->get(route('admin.revenus'))->assertForbidden();
    }

    public function test_ladministration_renegocie_un_taux(): void
    {
        $admin = User::create([
            'name' => 'Administration', 'email' => 'admin2@essai.sn',
            'password' => 'motdepasse', 'role' => 'admin', 'telephone' => '+221 33 800 00 01',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.taux', $this->boutique), ['taux' => 4.5])
            ->assertRedirect();

        $this->assertSame(45, $this->boutique->fresh()->taux_commission_pour_mille);
        $this->assertSame(4.5, $this->boutique->fresh()->tauxPourCent());
    }

    public function test_un_vendeur_ne_fixe_pas_son_propre_taux(): void
    {
        $avant = $this->boutique->taux_commission_pour_mille;

        $this->actingAs($this->boutique->utilisateur)
            ->post(route('admin.taux', $this->boutique), ['taux' => 0])
            ->assertForbidden();

        $this->assertSame($avant, $this->boutique->fresh()->taux_commission_pour_mille);
    }

    public function test_le_taux_reste_dans_des_bornes_tenables(): void
    {
        $admin = User::create([
            'name' => 'Administration', 'email' => 'admin3@essai.sn',
            'password' => 'motdepasse', 'role' => 'admin', 'telephone' => '+221 33 800 00 02',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.taux', $this->boutique), ['taux' => 90])
            ->assertSessionHasErrors('taux');
    }
}
