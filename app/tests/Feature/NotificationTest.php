<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Commande;
use App\Models\Offre;
use App\Models\User;
use App\Models\Vendeur;
use App\Notifications\EtapeCommande;
use App\Services\CommandeService;
use App\Services\PaiementService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Les courriels d'étape.
 *
 * Le vendeur a deux heures pour accepter une commande payée. S'il n'est pas
 * prévenu, ce délai ne veut rien dire et la commande expire toute seule : le
 * courriel fait partie de la mécanique, pas de la décoration.
 *
 * Deux propriétés sont éprouvées ici plus que le contenu des textes : le bon
 * destinataire — annoncer à un acheteur que sa propre commande vient de lui
 * être payée n'aurait aucun sens — et le fait que rien ne parte si la
 * transaction échoue.
 */
class NotificationTest extends TestCase
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

        $u = User::create(['name' => 'Awa BA', 'email' => 'awa@chantier.sn', 'password' => 'password']);
        $this->acheteur = Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 222 11 00',
        ]);

        $this->vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();
        $this->offre = Offre::with('article.unitesVente')
            ->where('vendeur_id', $this->vendeur->id)->orderBy('id')
            ->get()->first(fn (Offre $o) => $o->disponiblePivot() > 0);
    }

    private function commande(): Commande
    {
        return app(CommandeService::class)->creer($this->acheteur, [[
            'offre' => $this->offre, 'quantite' => '1', 'unite' => $this->offre->unite_affichee,
        ]]);
    }

    /** Le paiement prévient le vendeur, et lui seul. */
    public function test_le_paiement_previent_le_vendeur(): void
    {
        $c = $this->commande();
        Notification::fake();

        app(PaiementService::class)->traiterRappel($c, 'wave', 'N-' . $c->reference, $c->montant_total);

        Notification::assertSentTo($this->vendeur->utilisateur, EtapeCommande::class,
            fn (EtapeCommande $n) => str_contains($n->titre, 'commande payée'));

        // L'acheteur vient de payer : il n'a rien à apprendre ici.
        Notification::assertNotSentTo($this->acheteur->utilisateur, EtapeCommande::class);
    }

    /** L'acceptation prévient l'acheteur, et lui seul. */
    public function test_lacceptation_previent_lacheteur(): void
    {
        $c = $this->commande();
        app(PaiementService::class)->traiterRappel($c, 'wave', 'N-' . $c->reference, $c->montant_total);

        Notification::fake();
        app(CommandeService::class)->accepter($c->fresh(), $this->vendeur->utilisateur_id);

        Notification::assertSentTo($this->acheteur->utilisateur, EtapeCommande::class);
        Notification::assertNotSentTo($this->vendeur->utilisateur, EtapeCommande::class);
    }

    /**
     * Rien ne part si la transaction échoue.
     *
     * C'est la propriété qui justifie « DB::afterCommit ». Sans lui, un envoi
     * fait à l'intérieur d'une transaction annulée annoncerait un fait qui n'a
     * pas eu lieu — et l'on ne rattrape pas un courriel parti.
     */
    public function test_aucun_courriel_ne_part_si_la_transaction_est_annulee(): void
    {
        $c = $this->commande();
        app(PaiementService::class)->traiterRappel($c, 'wave', 'N-' . $c->reference, $c->montant_total);

        Notification::fake();

        try {
            DB::transaction(function () use ($c) {
                app(CommandeService::class)->accepter($c->fresh(), $this->vendeur->utilisateur_id);

                // Quelque chose échoue après la transition : tout est annulé.
                throw new \RuntimeException('Panne après l\'acceptation.');
            });
            $this->fail('La transaction aurait dû échouer.');
        } catch (\RuntimeException $e) {
            // attendu
        }

        Notification::assertNothingSent();
        $this->assertSame('payee', $c->fresh()->etat, 'La transition doit avoir été annulée elle aussi.');
    }

    /** Une messagerie en panne ne fait pas échouer une vente. */
    public function test_une_messagerie_en_panne_narrete_pas_la_commande(): void
    {
        $c = $this->commande();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'serveur.qui.nexiste.pas.invalid',
            'mail.mailers.smtp.port' => 2525, 'mail.mailers.smtp.timeout' => 1]);

        app(PaiementService::class)->traiterRappel($c, 'wave', 'N-' . $c->reference, $c->montant_total);

        // Le paiement est passé malgré l'échec d'envoi : c'est l'arbitrage
        // retenu — mieux vaut un vendeur non prévenu qu'un paiement perdu.
        $this->assertSame('payee', $c->fresh()->etat);
    }

    /** Une expiration ne prévient personne : l'acheteur a abandonné, le vendeur n'a rien vu. */
    public function test_une_expiration_ne_previent_personne(): void
    {
        $c = $this->commande();
        Notification::fake();

        app(CommandeService::class)->expirer($c->fresh());

        Notification::assertNothingSent();
    }
}
