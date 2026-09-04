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
use RuntimeException;
use Tests\TestCase;

/**
 * La preuve qu'une livraison a eu lieu, et qu'elle a été payée.
 *
 * Le défaut que ces essais couvrent était le plus grave du modèle. « Livrée » et
 * « refusée » étaient toutes deux déclarées par le vendeur, et par lui seul. Il
 * était donc l'unique témoin d'un fait sur lequel il avait intérêt à mentir :
 * livrer, encaisser les espèces à la porte, puis déclarer « refusée ». Il gardait
 * l'argent, le stock lui revenait, et la règle « un refus ne coûte rien »
 * — écrite en faveur du commerçant honnête — lui offrait la commission
 * par-dessus le marché.
 *
 * Le paiement à la livraison n'a pas de tiers de confiance : c'est ce qui le
 * rend accessible, et ce qui le rend fragile. À défaut de séquestre, on fait
 * témoigner les deux parties, et on garde une trace de leur désaccord.
 */
class PreuveDeLivraisonTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $vendeur;
    private User $admin;
    private Boutique $boutique;
    private Produit $produit;
    private Adresse $adresse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);

        $this->admin = User::create([
            'name' => 'Administration', 'email' => 'admin@essai.sn',
            'password' => 'motdepasse', 'role' => 'admin', 'telephone' => '+221 33 800 00 00',
        ]);
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
        $this->vendeur = $this->boutique->utilisateur;
        $this->produit = Produit::where('boutique_id', $this->boutique->id)
            ->where('stock', '>', 3)->where('actif', true)->orderBy('id')->firstOrFail();
    }

    private function commander(): Commande
    {
        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit->fresh(), 1);

        return app(PasseCommande::class)->creer($this->client, $this->adresse);
    }

    private function expedier(): Commande
    {
        return app(PasseCommande::class)->expedier($this->commander());
    }

    private function du(): int
    {
        return app(Commissions::class)->pourBoutique($this->boutique)['commission'];
    }

    // ── Le code de remise ────────────────────────────────────────────────────

    public function test_lexpedition_tire_un_code_a_six_chiffres(): void
    {
        $c = $this->expedier();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $c->code_livraison);
        $this->assertNull($c->code_remis_le);
    }

    /** Deux commandes ne partagent pas le même code. */
    public function test_les_codes_ne_se_repetent_pas(): void
    {
        $codes = [];

        for ($i = 0; $i < 8; $i++) {
            $codes[] = $this->expedier()->code_livraison;
        }

        $this->assertCount(8, array_unique($codes),
            'Des codes identiques rendraient la preuve devinable.');
    }

    /**
     * Le vendeur ne clôt rien sans le code.
     *
     * C'est ce qui transforme sa déclaration en preuve : le code n'existe que
     * sur l'écran du client, qui ne le dicte qu'en recevant le colis.
     */
    public function test_le_vendeur_ne_livre_pas_sans_le_code(): void
    {
        $c = $this->expedier();

        $this->expectException(RuntimeException::class);
        app(PasseCommande::class)->livrer($c->fresh());
    }

    public function test_un_code_faux_ne_passe_pas(): void
    {
        $c = $this->expedier();
        $faux = str_pad((string) ((((int) $c->code_livraison) + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->actingAs($this->vendeur)
            ->post(route('vendeur.livrer', $c), ['code' => $faux])
            ->assertSessionHas('erreur');

        $this->assertSame('expediee', $c->fresh()->etat);
        $this->assertSame(0, $this->du());
    }

    public function test_le_bon_code_cloture_la_vente(): void
    {
        $c = $this->expedier();

        $this->actingAs($this->vendeur)
            ->post(route('vendeur.livrer', $c), ['code' => $c->code_livraison])
            ->assertRedirect();

        $c = $c->fresh();
        $this->assertSame('livree', $c->etat);
        $this->assertTrue($c->paye);
        $this->assertNotNull($c->code_remis_le);
        $this->assertSame($c->commission, $this->du());
    }

    /** Un code entouré d'espaces reste valable : il est dicté au téléphone. */
    public function test_le_code_tolere_les_espaces(): void
    {
        $c = $this->expedier();

        app(PasseCommande::class)->livrer($c->fresh(), '  ' . $c->code_livraison . ' ');

        $this->assertSame('livree', $c->fresh()->etat);
    }

    // ── La fraude que tout ceci existe pour empêcher ─────────────────────────

    /**
     * Le vendeur encaisse, puis déclare un refus. Le client le contredit.
     *
     * C'est le scénario exact : le colis a été remis, les espèces empochées, et
     * le commerçant annonce « refusée » pour garder l'argent sans commission.
     * Sans recours du client, la fraude était parfaite et invisible.
     */
    public function test_un_faux_refus_est_rattrape_par_le_client(): void
    {
        $c = $this->expedier();

        // Le vendeur ment : il a livré et encaissé, il déclare un refus.
        $this->actingAs($this->vendeur)->post(route('vendeur.refuser', $c), [
            'motif' => 'Client absent.',
        ]);
        $this->assertSame('refusee', $c->fresh()->etat);
        $this->assertSame(0, $this->du(), 'La fraude a bien supprimé la commission.');

        // Le client conteste : il a le colis et il a payé.
        $this->actingAs($this->client)->post(route('commande.contester', $c->fresh()), [
            'motif' => 'J\'ai recu le colis mardi et paye 41 500 F en especes au livreur.',
        ])->assertRedirect();

        $c = $c->fresh();
        $this->assertSame('litige', $c->etat);
        $this->assertSame('refusee', $c->etat_conteste);
        $this->assertSame('client', $c->litige_par);

        // L'administration tranche en faveur du client.
        $this->actingAs($this->admin)->post(route('admin.trancher', $c), [
            'vers' => 'livree',
            'motif' => 'Le client produit le recu du livreur.',
        ])->assertRedirect();

        $c = $c->fresh();
        $this->assertSame('livree', $c->etat);
        $this->assertTrue($c->paye);
        $this->assertSame($c->commission, $this->du(),
            'La commission redevient due : la vente a bien eu lieu.');
    }

    /**
     * L'arbitrage ne laisse pas le stock faussé.
     *
     * Le faux refus avait rendu la marchandise au stock. Puisque la vente a bien
     * eu lieu, elle doit en ressortir — sinon le vendeur récupère un article
     * qu'il a vendu, et le catalogue ment sur ce qu'il reste.
     */
    public function test_larbitrage_remet_le_stock_droit(): void
    {
        $avant = $this->produit->stock;
        $c = $this->expedier();
        $this->assertSame($avant - 1, $this->produit->fresh()->stock);

        app(PasseCommande::class)->refuser($c->fresh(), 'Client absent.');
        $this->assertSame($avant, $this->produit->fresh()->stock, 'Le refus a rendu le stock.');

        app(PasseCommande::class)->contester($c->fresh(), 'client', 'J\'ai recu et paye.');
        app(PasseCommande::class)->trancher($c->fresh(), 'livree', 'Recu produit.');

        $this->assertSame($avant - 1, $this->produit->fresh()->stock,
            'La vente ayant eu lieu, la marchandise ressort du stock.');
    }

    /** Trancher vers « refusée » laisse le stock rendu, sans le rendre deux fois. */
    public function test_un_litige_tranche_en_faveur_du_vendeur_ne_rend_pas_deux_fois(): void
    {
        $avant = $this->produit->stock;
        $c = $this->expedier();

        app(PasseCommande::class)->refuser($c->fresh(), 'Client absent.');
        app(PasseCommande::class)->contester($c->fresh(), 'client', 'Je conteste ce refus.');
        app(PasseCommande::class)->trancher($c->fresh(), 'refusee', 'Le livreur atteste l\'absence.');

        $this->assertSame($avant, $this->produit->fresh()->stock);
        $this->assertSame(0, $this->du());
    }

    // ── Le contrepoids : la confirmation du client ───────────────────────────

    /**
     * Le client clôt la vente tout seul.
     *
     * Utile aussi sans fraude : un commerçant débordé qui ne clôture jamais ses
     * commandes laisserait le client sans possibilité de noter.
     */
    public function test_le_client_confirme_seul_la_reception(): void
    {
        $c = $this->expedier();

        $this->actingAs($this->client)
            ->post(route('commande.confirmer', $c))->assertRedirect();

        $c = $c->fresh();
        $this->assertSame('livree', $c->etat);
        $this->assertNotNull($c->confirmee_le);
        $this->assertTrue($c->paye);
        $this->assertSame($c->commission, $this->du(),
            'La confirmation du client rend la commission due.');
    }

    public function test_on_ne_confirme_pas_deux_fois(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->confirmerParLeClient($c->fresh());

        $this->assertFalse($c->fresh()->confirmableParLeClient());

        $this->actingAs($this->client)
            ->post(route('commande.confirmer', $c->fresh()))
            ->assertSessionHas('erreur');
    }

    public function test_on_ne_confirme_pas_une_commande_non_partie(): void
    {
        $c = $this->commander();

        $this->actingAs($this->client)
            ->post(route('commande.confirmer', $c))->assertSessionHas('erreur');

        $this->assertSame('en_preparation', $c->fresh()->etat);
    }

    /** Le client peut aussi contester une livraison qui n'a pas eu lieu. */
    public function test_le_client_conteste_une_fausse_livraison(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->livrer($c->fresh(), $c->code_livraison);

        $this->actingAs($this->client)->post(route('commande.contester', $c->fresh()), [
            'motif' => 'Je n\'ai jamais rien recu, personne n\'est passe chez moi.',
        ])->assertRedirect();

        $this->assertSame('litige', $c->fresh()->etat);
        $this->assertSame('livree', $c->fresh()->etat_conteste);
        $this->assertSame(0, $this->du(), 'Un litige suspend la commission.');
    }

    // ── L'équilibre : le vendeur a le même recours ───────────────────────────

    /**
     * Le vendeur peut contester aussi.
     *
     * Sans cela le dispositif serait déséquilibré : un client de mauvaise foi
     * garderait la marchandise, refuserait de dicter le code, et nierait avoir
     * reçu. Le commerçant doit pouvoir saisir l'administration.
     */
    public function test_le_vendeur_ouvre_aussi_un_litige(): void
    {
        $c = $this->expedier();

        $this->actingAs($this->vendeur)->post(route('vendeur.contester', $c), [
            'motif' => 'Colis remis et paye, le client refuse de donner son code.',
        ])->assertRedirect();

        $c = $c->fresh();
        $this->assertSame('litige', $c->etat);
        $this->assertSame('vendeur', $c->litige_par);
    }

    public function test_un_litige_exige_un_motif_serieux(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->refuser($c->fresh(), 'Absent.');

        $this->actingAs($this->client)
            ->post(route('commande.contester', $c->fresh()), ['motif' => 'non'])
            ->assertSessionHasErrors('motif');

        $this->assertSame('refusee', $c->fresh()->etat);
    }

    public function test_un_client_ne_conteste_pas_la_commande_dun_autre(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->refuser($c->fresh(), 'Absent.');

        $intrus = User::create([
            'name' => 'Moussa', 'email' => 'moussa@essai.sn',
            'password' => 'motdepasse', 'role' => 'client', 'telephone' => '+221 77 111 11 11',
        ]);

        $this->actingAs($intrus)
            ->post(route('commande.contester', $c->fresh()), ['motif' => 'Elle est a moi voyons.'])
            ->assertForbidden();

        $this->assertSame('refusee', $c->fresh()->etat);
    }

    // ── L'arbitrage est réservé ──────────────────────────────────────────────

    public function test_seule_ladministration_tranche(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->contester($c->fresh(), 'vendeur', 'Code non communique.');

        foreach ([$this->client, $this->vendeur] as $intrus) {
            $this->actingAs($intrus)->post(route('admin.trancher', $c->fresh()), [
                'vers' => 'livree', 'motif' => 'Je tranche en ma faveur.',
            ])->assertForbidden();
        }

        $this->assertSame('litige', $c->fresh()->etat);
    }

    public function test_un_litige_ne_se_tranche_pas_en_un_etat_intermediaire(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->contester($c->fresh(), 'client', 'Rien recu du tout.');

        $this->actingAs($this->admin)->post(route('admin.trancher', $c->fresh()), [
            'vers' => 'expediee', 'motif' => 'On repart en arriere.',
        ])->assertSessionHasErrors('vers');

        $this->assertSame('litige', $c->fresh()->etat);
    }

    public function test_la_decision_est_motivee(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->contester($c->fresh(), 'client', 'Rien recu du tout.');

        $this->actingAs($this->admin)->post(route('admin.trancher', $c->fresh()), [
            'vers' => 'livree', 'motif' => '',
        ])->assertSessionHasErrors('motif');
    }

    // ── Le détecteur ─────────────────────────────────────────────────────────

    /**
     * Le taux de refus par boutique.
     *
     * Le code de remise couvre une commande ; ce taux couvre le commerçant. Un
     * vendeur qui déclare des refus fictifs le fait monter, seul, pendant que
     * ses concurrents restent bas.
     */
    public function test_le_taux_de_refus_designe_la_boutique_suspecte(): void
    {
        // Cinq commandes closes, dont trois refus.
        foreach ([true, true, true, false, false] as $refus) {
            $c = $this->expedier();

            if ($refus) {
                app(PasseCommande::class)->refuser($c->fresh(), 'Client absent.');
            } else {
                app(PasseCommande::class)->livrer($c->fresh(), $c->code_livraison);
            }
        }

        $suspects = app(Commissions::class)->tauxDeRefusParBoutique();
        $mienne = $suspects->firstWhere('id', $this->boutique->id);

        $this->assertNotNull($mienne, 'Cinq commandes closes suffisent à figurer au tableau.');
        $this->assertSame(60.0, $mienne->taux_refus);
        $this->assertSame(3, (int) $mienne->nb_refusees);
    }

    /** Sous cinq commandes, le chiffre ne veut rien dire et ne s'affiche pas. */
    public function test_une_boutique_debutante_nest_pas_accusee(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->refuser($c->fresh(), 'Client absent.');

        $suspects = app(Commissions::class)->tauxDeRefusParBoutique();

        $this->assertNull($suspects->firstWhere('id', $this->boutique->id),
            'Un refus sur une vente est le lot d\'un debutant, pas un indice.');
    }

    public function test_ladministration_voit_les_litiges(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->contester($c->fresh(), 'client', 'Colis jamais recu chez moi.');

        $this->actingAs($this->admin)->get(route('admin.litiges'))
            ->assertOk()
            ->assertSee($c->reference)
            ->assertSee('jamais recu chez moi', false);
    }

    public function test_un_vendeur_nentre_pas_dans_les_litiges(): void
    {
        $this->actingAs($this->vendeur)->get(route('admin.litiges'))->assertForbidden();
    }

    // ── Le client voit son code, le vendeur ne le voit pas ───────────────────

    public function test_le_client_voit_son_code_de_remise(): void
    {
        $c = $this->expedier();

        $this->actingAs($this->client)->get(route('mes-commandes.detail', $c))
            ->assertOk()->assertSee($c->code_livraison);
    }

    /**
     * Le vendeur ne voit pas le code sur son écran.
     *
     * Un code affiché chez le vendeur ne prouverait plus rien : il le
     * recopierait sans jamais avoir vu le client.
     */
    public function test_le_vendeur_ne_voit_pas_le_code(): void
    {
        $c = $this->expedier();

        $this->actingAs($this->vendeur)->get(route('vendeur.commandes'))
            ->assertOk()->assertDontSee($c->code_livraison);
    }
}
