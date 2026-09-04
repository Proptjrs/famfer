<?php

namespace Tests\Feature;

use App\Models\Adresse;
use App\Models\Boutique;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use App\Notifications\EtapeCommande;
use App\Services\Panier;
use App\Services\PasseCommande;
use App\Services\Sms;
use App\Services\Veille;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Le temps et le téléphone : deux autres manières de savoir.
 *
 * Le code de remise fait parler le client au moment de la livraison, sa
 * confirmation le fait parler après. Restait le cas où personne ne parle — et
 * c'est de loin le plus fréquent, bien avant la fraude.
 *
 * Deux sources s'ajoutent ici, et toutes deux sont gratuites.
 *
 * Le **téléphone**, parce que le code de remise s'affichait sur un écran : un
 * client sans connexion au moment où le livreur sonne ne pouvait pas produire
 * la preuve qu'on lui demandait.
 *
 * Le **temps**, parce que le silence n'est pas neutre. Celui du vendeur est
 * suspect et déclenche une question au client ; celui du client vaut acceptation
 * et ferme la fenêtre de contestation.
 */
class VeilleTest extends TestCase
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
            'telephone' => '77 123 45 67', 'region' => 'Dakar',
            'ville' => 'Dakar', 'quartier' => 'Grand Yoff', 'par_defaut' => true,
        ]);

        $this->boutique = Boutique::where('statut', 'active')->orderBy('id')->firstOrFail();
        $this->produit = Produit::where('boutique_id', $this->boutique->id)
            ->where('stock', '>', 3)->where('actif', true)->orderBy('id')->firstOrFail();
    }

    private function expedier(): Commande
    {
        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit->fresh(), 1);

        return app(PasseCommande::class)->expedier(
            app(PasseCommande::class)->creer($this->client, $this->adresse)
        );
    }

    /** Vieillit une commande sans passer par le service, pour l'essai seul. */
    private function vieillir(Commande $c, string $colonne, int $jours): void
    {
        $c->forceFill([$colonne => now()->subDays($jours)])->save();
    }

    // ── Le téléphone ─────────────────────────────────────────────────────────

    /**
     * Le code de remise part aussi par SMS.
     *
     * C'est le défaut que cette voie corrige : le code s'affichait sur un écran
     * et dans un courriel. Un client sans connexion au moment où le livreur
     * sonne ne pouvait pas produire la preuve qu'on lui demandait.
     */
    public function test_le_code_part_par_telephone(): void
    {
        Log::spy();

        $c = $this->expedier();

        Log::shouldHaveReceived('info')->withArgs(
            fn ($message, $contexte = []) => $message === 'SMS'
                && str_contains($contexte['texte'] ?? '', $c->code_livraison)
        );
    }

    /** Le journal ne garde que la fin du numéro. */
    public function test_le_journal_ne_retient_pas_le_numero_entier(): void
    {
        Log::spy();

        app(Sms::class)->envoyer('+221 77 123 45 67', 'Essai.');

        Log::shouldHaveReceived('info')->withArgs(
            fn ($message, $contexte = []) => $message === 'SMS'
                && str_ends_with($contexte['vers'] ?? '', '4567')
                && ! str_contains($contexte['vers'] ?? '', '77123')
        );
    }

    /**
     * Les trois écritures d'un même numéro sénégalais aboutissent.
     *
     * Les carnets d'adresses contiennent « 77 123 45 67 », « 221771234567 » et
     * « +221 77 123 45 67 » pour le même abonné. Sans normalisation, deux
     * clients sur trois ne recevraient rien.
     */
    public function test_les_formats_de_numero_sont_tous_acceptes(): void
    {
        foreach (['77 123 45 67', '221771234567', '+221 77 123 45 67', '771234567'] as $forme) {
            $this->assertTrue(app(Sms::class)->envoyer($forme, 'Essai.'),
                "Le format « {$forme} » aurait dû passer.");
        }
    }

    public function test_un_numero_inexploitable_ne_fait_pas_echouer_lenvoi(): void
    {
        foreach ([null, '', 'appelez-moi', '12'] as $forme) {
            $this->assertFalse(app(Sms::class)->envoyer($forme, 'Essai.'));
        }
    }

    /** Une passerelle muette n'arrête pas une vente. */
    public function test_un_canal_inconnu_ne_leve_pas(): void
    {
        config(['services.sms.canal' => 'null']);

        $this->assertTrue(app(Sms::class)->envoyer('+221 77 123 45 67', 'Essai.'));
    }

    // ── Le silence du vendeur ────────────────────────────────────────────────

    /**
     * Un colis expédié que personne ne clôt fait poser la question au client.
     *
     * Le silence du vendeur est suspect : soit le colis dort dans son magasin,
     * soit il a été remis sans être déclaré — et la seconde hypothèse est
     * exactement celle qui l'arrange.
     */
    public function test_une_commande_dormante_declenche_une_question_au_client(): void
    {
        $c = $this->expedier();
        $this->vieillir($c, 'expediee_le', Veille::JOURS_AVANT_RELANCE + 1);

        Notification::fake();

        $fait = app(Veille::class)->passer();

        $this->assertSame(1, $fait['relancees']);
        Notification::assertSentTo($this->client, EtapeCommande::class,
            fn (EtapeCommande $n) => str_contains($n->titre, 'Avez-vous reçu'));
        $this->assertNotNull($c->fresh()->relance_le);
    }

    public function test_une_commande_fraiche_nest_pas_relancee(): void
    {
        $this->expedier();

        Notification::fake();

        $this->assertSame(0, app(Veille::class)->passer()['relancees']);
        Notification::assertNothingSent();
    }

    /** On ne relance qu'une fois : au-delà, on harcèle quelqu'un qui a payé. */
    public function test_on_ne_relance_pas_deux_fois(): void
    {
        $c = $this->expedier();
        $this->vieillir($c, 'expediee_le', Veille::JOURS_AVANT_RELANCE + 1);

        $this->assertSame(1, app(Veille::class)->relancerLesDormantes());
        $this->assertSame(0, app(Veille::class)->relancerLesDormantes());
    }

    public function test_une_commande_close_nest_pas_relancee(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->livrer($c->fresh(), $c->code_livraison);
        $this->vieillir($c->fresh(), 'expediee_le', Veille::JOURS_AVANT_RELANCE + 1);

        $this->assertSame(0, app(Veille::class)->relancerLesDormantes());
    }

    /** La relance part aussi par téléphone : les deux canaux n'atteignent pas les mêmes gens. */
    public function test_la_relance_passe_par_les_deux_canaux(): void
    {
        $c = $this->expedier();
        $this->vieillir($c, 'expediee_le', Veille::JOURS_AVANT_RELANCE + 1);

        Log::spy();
        app(Veille::class)->relancerLesDormantes();

        Log::shouldHaveReceived('info')->withArgs(
            fn ($message, $contexte = []) => $message === 'SMS'
                && str_contains($contexte['texte'] ?? '', $c->reference)
                && str_contains($contexte['texte'] ?? '', 'bien arrivee')
        );
    }

    // ── Le silence du client ─────────────────────────────────────────────────

    /**
     * Passé le délai, ce qui est déclaré ne se conteste plus.
     *
     * Sans cette fermeture, un vendeur honnête n'aurait jamais de certitude : un
     * refus enregistré en janvier pourrait lui être contesté en juin.
     */
    public function test_la_fenetre_de_contestation_se_ferme(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->refuser($c->fresh(), 'Client absent.');

        $this->assertTrue($c->fresh()->contestableParLeClient());

        $this->vieillir($c->fresh(), 'cloturee_le', Veille::JOURS_DE_CONTESTATION + 1);
        $this->assertSame(1, app(Veille::class)->fermerLesFenetres());

        $c = $c->fresh();
        $this->assertNotNull($c->close_le);
        $this->assertFalse($c->contestableParLeClient());
    }

    public function test_une_contestation_hors_delai_est_refusee(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->refuser($c->fresh(), 'Client absent.');
        $this->vieillir($c->fresh(), 'cloturee_le', Veille::JOURS_DE_CONTESTATION + 1);
        app(Veille::class)->fermerLesFenetres();

        $this->actingAs($this->client)
            ->post(route('commande.contester', $c->fresh()), [
                'motif' => 'Je conteste ce refus, six mois plus tard.',
            ])->assertSessionHas('erreur');

        $this->assertSame('refusee', $c->fresh()->etat);
    }

    public function test_une_commande_recente_reste_contestable(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->refuser($c->fresh(), 'Client absent.');

        $this->assertSame(0, app(Veille::class)->fermerLesFenetres());
        $this->assertTrue($c->fresh()->contestableParLeClient());
    }

    /**
     * Un litige ouvert ne se referme pas tout seul.
     *
     * Un dossier reste ouvert tant que l'administration n'a pas tranché, quel
     * que soit son âge : le temps ne peut pas arbitrer à sa place.
     */
    public function test_le_temps_ne_ferme_pas_un_litige(): void
    {
        $c = $this->expedier();
        app(PasseCommande::class)->refuser($c->fresh(), 'Client absent.');
        app(PasseCommande::class)->contester($c->fresh(), 'client', 'J\'ai recu et paye.');

        $this->vieillir($c->fresh(), 'cloturee_le', Veille::JOURS_DE_CONTESTATION + 30);

        $this->assertSame(0, app(Veille::class)->fermerLesFenetres());
        $this->assertSame('litige', $c->fresh()->etat);
        $this->assertNull($c->fresh()->close_le);
    }

    // ── Le tour complet ──────────────────────────────────────────────────────

    public function test_la_commande_artisan_passe_le_tour(): void
    {
        $dormante = $this->expedier();
        $this->vieillir($dormante, 'expediee_le', Veille::JOURS_AVANT_RELANCE + 1);

        $close = $this->expedier();
        app(PasseCommande::class)->refuser($close->fresh(), 'Client absent.');
        $this->vieillir($close->fresh(), 'cloturee_le', Veille::JOURS_DE_CONTESTATION + 1);

        $this->artisan('famfer:veiller')->assertSuccessful();

        $this->assertNotNull($dormante->fresh()->relance_le);
        $this->assertNotNull($close->fresh()->close_le);
    }

    /** La tâche est réellement planifiée : sans cela, rien ne se passe jamais. */
    public function test_la_veille_est_planifiee(): void
    {
        $planifiees = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($e) => $e->command)
            ->filter(fn ($c) => str_contains((string) $c, 'famfer:veiller'));

        $this->assertCount(1, $planifiees,
            'La commande existe mais rien ne la déclenche : elle ne servirait à rien.');
    }
}
