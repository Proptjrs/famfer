<?php

namespace Tests\Feature;

use App\Models\Adresse;
use App\Models\Boutique;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use App\Notifications\DecisionBoutique;
use App\Notifications\EtapeCommande;
use App\Services\Panier;
use App\Services\PasseCommande;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Les courriels d'étape, et les états qui n'étaient atteignables par personne.
 *
 * Rien ne partait : un commerçant qui ne consultait pas son tableau de bord ne
 * savait pas qu'on lui avait acheté quelque chose. Et deux états sur sept —
 * « refusée » et « retournée » — existaient dans la machine sans qu'aucun écran
 * ne puisse les atteindre : le taux de refus des tableaux de bord affichait donc
 * zéro quoi qu'il arrive.
 */
class AvertirTest extends TestCase
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

    // ── Les courriels ────────────────────────────────────────────────────────

    /** Une commande prévient le client ET le vendeur, mais pas du même message. */
    public function test_une_commande_previent_les_deux_parties(): void
    {
        Notification::fake();

        $this->commander();

        Notification::assertSentTo($this->client, EtapeCommande::class,
            fn (EtapeCommande $n) => str_contains($n->titre, 'enregistrée'));

        Notification::assertSentTo($this->vendeur, EtapeCommande::class,
            fn (EtapeCommande $n) => str_contains($n->titre, 'à préparer'));
    }

    /** Une boutique n'est prévenue qu'une fois, même pour plusieurs articles. */
    public function test_un_vendeur_nest_prevenu_quune_fois_par_commande(): void
    {
        $second = Produit::where('boutique_id', $this->boutique->id)
            ->where('id', '!=', $this->produit->id)
            ->where('stock', '>', 2)->where('actif', true)->orderBy('id')->firstOrFail();

        Notification::fake();

        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit->fresh(), 1);
        app(Panier::class)->ajouter($second, 1);
        app(PasseCommande::class)->creer($this->client, $this->adresse);

        Notification::assertSentToTimes($this->vendeur, EtapeCommande::class, 1);
    }

    public function test_lexpedition_previent_le_client_seul(): void
    {
        $c = $this->commander();
        Notification::fake();

        app(PasseCommande::class)->expedier($c);

        Notification::assertSentTo($this->client, EtapeCommande::class,
            fn (EtapeCommande $n) => str_contains($n->titre, 'partie'));
        Notification::assertNotSentTo($this->vendeur, EtapeCommande::class);
    }

    public function test_la_livraison_invite_a_noter(): void
    {
        $c = $this->commander();
        $passe = app(PasseCommande::class);
        $passe->expedier($c);
        $passe->mettreEnLivraison($c->fresh());

        Notification::fake();
        $passe->livrer($c->fresh());

        Notification::assertSentTo($this->client, EtapeCommande::class,
            fn (EtapeCommande $n) => str_contains($n->bouton, 'avis'));
    }

    public function test_ladministration_previent_de_sa_decision(): void
    {
        $enAttente = Boutique::where('statut', 'en_attente')->orderBy('id')->firstOrFail();
        Notification::fake();

        $this->actingAs($this->admin)->post(route('admin.activer', $enAttente));

        Notification::assertSentTo($enAttente->utilisateur, DecisionBoutique::class,
            fn (DecisionBoutique $n) => $n->acceptee === true);
    }

    public function test_la_suspension_previent_aussi(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->post(route('admin.suspendre', $this->boutique), [
            'motif' => 'Marchandise non conforme.',
        ]);

        Notification::assertSentTo($this->vendeur, DecisionBoutique::class,
            fn (DecisionBoutique $n) => $n->acceptee === false);
    }

    /**
     * Rien ne part si la transaction échoue.
     *
     * C'est la propriété qui justifie « DB::afterCommit ». Sans lui, un envoi
     * fait à l'intérieur d'une transaction annulée annoncerait un fait qui n'a
     * pas eu lieu — et l'on ne rattrape pas un courriel parti.
     */
    public function test_aucun_courriel_ne_part_si_la_transaction_echoue(): void
    {
        $c = $this->commander();
        Notification::fake();

        try {
            DB::transaction(function () use ($c) {
                app(PasseCommande::class)->expedier($c);
                throw new RuntimeException('Panne après l\'expédition.');
            });
            $this->fail('La transaction aurait dû échouer.');
        } catch (RuntimeException $e) {
            // attendu
        }

        Notification::assertNothingSent();
        $this->assertSame('en_preparation', $c->fresh()->etat);
    }

    /** Une messagerie en panne ne fait pas échouer une commande. */
    public function test_une_messagerie_en_panne_narrete_pas_la_vente(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'serveur.qui.nexiste.pas.invalid',
            'mail.mailers.smtp.port' => 2525,
            'mail.mailers.smtp.timeout' => 1,
        ]);

        $stockAvant = $this->produit->stock;
        $c = $this->commander();

        // La commande est passée malgré l'échec d'envoi : c'est l'arbitrage
        // retenu — mieux vaut un client non prévenu qu'une vente perdue.
        $this->assertSame('en_preparation', $c->etat);
        $this->assertSame($stockAvant - 1, $this->produit->fresh()->stock);
    }

    // ── Les états qui n'étaient atteignables par personne ────────────────────

    /**
     * Le refus à la porte s'enregistre.
     *
     * L'état existait, aucun écran ne pouvait l'atteindre. Le taux de refus des
     * tableaux de bord affichait donc zéro quoi qu'il arrive.
     */
    public function test_le_vendeur_enregistre_un_refus(): void
    {
        $c = $this->commander();
        $stockApresCommande = $this->produit->fresh()->stock;
        app(PasseCommande::class)->expedier($c);

        $this->actingAs($this->vendeur)
            ->post(route('vendeur.refuser', $c->fresh()), [
                'motif' => 'Client absent, deux passages.',
            ])->assertRedirect();

        $c = $c->fresh();
        $this->assertSame('refusee', $c->etat);
        $this->assertStringContainsString('absent', $c->motif);
        $this->assertFalse($c->paye);

        // La marchandise rentre : le stock remonte.
        $this->assertSame($stockApresCommande + 1, $this->produit->fresh()->stock);
    }

    public function test_le_refus_exige_un_motif(): void
    {
        $c = $this->commander();
        app(PasseCommande::class)->expedier($c);

        $this->actingAs($this->vendeur)
            ->post(route('vendeur.refuser', $c->fresh()), ['motif' => ''])
            ->assertSessionHasErrors('motif');

        $this->assertSame('expediee', $c->fresh()->etat);
    }

    /** Le taux de refus des tableaux de bord n'est plus figé à zéro. */
    public function test_le_taux_de_refus_bouge(): void
    {
        $avant = $this->actingAs($this->admin)->get(route('admin.tableau'))
            ->viewData('chiffres')['refusees'];

        $c = $this->commander();
        app(PasseCommande::class)->expedier($c);
        $this->actingAs($this->vendeur)->post(route('vendeur.refuser', $c->fresh()), [
            'motif' => 'Refusé à la porte.',
        ]);

        $chiffres = $this->actingAs($this->admin)->get(route('admin.tableau'))
            ->viewData('chiffres');

        $this->assertSame($avant + 1, $chiffres['refusees']);
        $this->assertGreaterThan(0, $chiffres['taux_refus']);
    }

    public function test_le_vendeur_enregistre_un_retour(): void
    {
        $c = $this->commander();
        $stockApresCommande = $this->produit->fresh()->stock;

        $passe = app(PasseCommande::class);
        $passe->expedier($c);
        $passe->mettreEnLivraison($c->fresh());
        $passe->livrer($c->fresh());

        $this->actingAs($this->vendeur)
            ->post(route('vendeur.retourner', $c->fresh()), [
                'motif' => 'Diamètre erroné.',
            ])->assertRedirect();

        $this->assertSame('retournee', $c->fresh()->etat);
        $this->assertSame($stockApresCommande + 1, $this->produit->fresh()->stock);
    }

    /** On ne refuse pas une commande qui n'est pas partie. */
    public function test_on_ne_refuse_pas_une_commande_en_preparation(): void
    {
        $c = $this->commander();

        $this->actingAs($this->vendeur)
            ->post(route('vendeur.refuser', $c), ['motif' => 'Trop tôt.'])
            ->assertSessionHas('erreur');

        $this->assertSame('en_preparation', $c->fresh()->etat);
    }

    public function test_un_vendeur_ne_refuse_pas_la_commande_dun_autre(): void
    {
        $c = $this->commander();
        app(PasseCommande::class)->expedier($c);

        $autre = Boutique::where('id', '!=', $this->boutique->id)
            ->where('statut', 'active')->orderBy('id')->firstOrFail();

        $this->actingAs($autre->utilisateur)
            ->post(route('vendeur.refuser', $c->fresh()), ['motif' => 'Pas la mienne.'])
            ->assertForbidden();

        $this->assertSame('expediee', $c->fresh()->etat);
    }

    /** Tous les états de la machine sont désormais atteignables. */
    public function test_aucun_etat_nest_mort(): void
    {
        $atteignables = ['en_preparation', 'expediee', 'en_livraison',
                         'livree', 'refusee', 'annulee', 'retournee'];

        $this->assertSame(
            $atteignables,
            array_keys(Commande::SUITES),
            'La machine à états a changé : vérifiez que chaque état reste atteignable.'
        );
    }
}
