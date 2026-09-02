<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Commande;
use App\Models\Offre;
use App\Models\Paiement;
use App\Models\User;
use App\Models\Vendeur;
use App\Services\CommandeService;
use App\Services\GrandLivre;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'adresse publique par laquelle l'argent entre.
 *
 * C'est la seule porte de la plateforme qui s'ouvre sans session, et la seule
 * dont l'ouverture déplace de l'argent. Ce qui est éprouvé ici n'est donc pas
 * le chemin heureux — il l'est déjà ailleurs — mais ce qui arrive quand la
 * requête est hostile : signature absente, fausse, périmée, corps modifié après
 * signature, rappel rejoué.
 */
class RappelPaiementTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-partage-avec-loperateur';

    private Commande $commande;

    protected function setUp(): void
    {
        parent::setUp();
        config(['paiement.secret_rappel' => self::SECRET]);

        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);

        $u = User::create([
            'name' => 'Awa BA', 'email' => 'awa@chantier.sn', 'password' => 'password',
        ]);
        $acheteur = Acheteur::create([
            'utilisateur_id' => $u->id, 'genre' => 'chantier', 'telephone' => '+221 77 000 11 22',
        ]);

        $vendeur = Vendeur::where('statut', 'verifie')->orderBy('id')->firstOrFail();
        $offre = Offre::with('article.unitesVente')
            ->where('vendeur_id', $vendeur->id)->orderBy('id')
            ->get()->first(fn (Offre $o) => $o->disponiblePivot() > 0);

        $this->commande = app(CommandeService::class)->creer($acheteur, [[
            'offre' => $offre, 'quantite' => '1', 'unite' => $offre->unite_affichee,
        ]]);
    }

    /** Forge un rappel comme le ferait l'opérateur. */
    private function rappeler(array $charge, ?string $secret = null, ?int $horodatage = null)
    {
        $corps = json_encode($charge);
        $horodatage ??= now()->timestamp;
        $signature = hash_hmac('sha256', $horodatage . '.' . $corps, $secret ?? self::SECRET);

        return $this->call(
            'POST', '/rappel-paiement/wave', [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_FAMFER_SIGNATURE' => $signature,
                'HTTP_X_FAMFER_HORODATAGE' => (string) $horodatage,
            ],
            $corps
        );
    }

    private function charge(array $ecrase = []): array
    {
        return array_merge([
            'reference' => $this->commande->reference,
            'cle_idempotence' => 'WAVE-' . $this->commande->reference,
            'montant' => $this->commande->montant_total,
            'frais_operateur' => 150,
        ], $ecrase);
    }

    public function test_un_rappel_signe_encaisse_la_commande(): void
    {
        $this->rappeler($this->charge())
            ->assertOk()
            ->assertJson(['recu' => true, 'deja_traite' => false]);

        $this->assertSame('payee', $this->commande->fresh()->etat);

        // L'argent est au séquestre, pas chez le vendeur : la commande n'est
        // pas encore reçue.
        $livre = app(GrandLivre::class);
        $this->assertSame($this->commande->montant_total, $livre->solde('sequestre'));
        $this->assertSame(0, $livre->solde('vendeur:' . $this->commande->vendeur_id));
        $this->assertTrue($livre->estEquilibre());
    }

    /**
     * Sans signature valable, rien n'entre.
     *
     * C'est le test qui compte : l'adresse est publique, et si elle acceptait
     * une requête non signée, n'importe qui pourrait déclarer payées toutes les
     * commandes de la place de marché.
     */
    public function test_une_signature_fausse_est_refusee(): void
    {
        $this->rappeler($this->charge(), secret: 'ce-nest-pas-le-bon-secret')
            ->assertUnauthorized();

        $this->assertSame('en_attente_paiement', $this->commande->fresh()->etat);
        $this->assertSame(0, Paiement::count());
        $this->assertSame(0, app(GrandLivre::class)->solde('sequestre'));
    }

    public function test_un_rappel_sans_signature_est_refuse(): void
    {
        $this->postJson('/rappel-paiement/wave', $this->charge())
            ->assertUnauthorized();

        $this->assertSame(0, Paiement::count());
    }

    /** Un enregistrement capturé puis renvoyé plus tard ne vaut plus rien. */
    public function test_un_rappel_perime_est_refuse(): void
    {
        $vieux = now()->timestamp - config('paiement.tolerance_horodatage') - 60;

        $this->rappeler($this->charge(), horodatage: $vieux)->assertUnauthorized();

        $this->assertSame(0, Paiement::count());
    }

    /**
     * Le corps est signé, donc il ne se modifie pas en route.
     *
     * Un intermédiaire qui gonflerait le montant sans refaire la signature —
     * ce qu'il ne peut pas, faute du secret — est arrêté ici.
     */
    public function test_un_corps_modifie_apres_signature_est_refuse(): void
    {
        $corps = json_encode($this->charge());
        $horodatage = now()->timestamp;
        $signature = hash_hmac('sha256', $horodatage . '.' . $corps, self::SECRET);

        $altere = json_encode($this->charge(['montant' => 1]));

        $this->call(
            'POST', '/rappel-paiement/wave', [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_FAMFER_SIGNATURE' => $signature,
                'HTTP_X_FAMFER_HORODATAGE' => (string) $horodatage,
            ],
            $altere
        )->assertUnauthorized();

        $this->assertSame(0, Paiement::count());
    }

    /** Deux rappels identiques : deux accusés, un seul crédit. */
    public function test_un_rappel_rejoue_ne_credite_quune_fois(): void
    {
        $this->rappeler($this->charge())->assertOk()->assertJson(['deja_traite' => false]);
        $this->rappeler($this->charge())->assertOk()->assertJson(['deja_traite' => true]);

        $this->assertSame(1, Paiement::where('commande_id', $this->commande->id)->count());
        $this->assertSame(
            $this->commande->montant_total,
            app(GrandLivre::class)->solde('sequestre')
        );
    }

    /**
     * Un montant qui ne correspond pas est enregistré mais ne solde rien.
     *
     * On ne rejette pas : l'argent est réellement parti de chez l'acheteur, et
     * le nier ferait disparaître la trace. On le consigne, et un humain tranche.
     */
    public function test_un_montant_different_narrange_rien_tout_seul(): void
    {
        $this->rappeler($this->charge(['montant' => 12_345]))->assertOk();

        $paiement = Paiement::where('commande_id', $this->commande->id)->firstOrFail();
        $this->assertSame('echoue', $paiement->etat);
        $this->assertSame('en_attente_paiement', $this->commande->fresh()->etat);
        $this->assertSame(0, app(GrandLivre::class)->solde('sequestre'));
    }

    /** Une commande inconnue : 404, et surtout aucune écriture. */
    public function test_une_commande_inconnue_est_signalee(): void
    {
        $this->rappeler($this->charge(['reference' => 'FF-2026-999999']))
            ->assertNotFound();

        $this->assertSame(0, Paiement::count());
    }

    /**
     * Sans secret configuré, la porte reste fermée.
     *
     * Le piège serait de dégrader en « pas de secret, pas de vérification » :
     * un déploiement où l'on aurait oublié la variable d'environnement
     * accepterait alors tous les rappels du monde.
     */
    public function test_sans_secret_configure_tout_rappel_est_refuse(): void
    {
        config(['paiement.secret_rappel' => null]);

        $this->rappeler($this->charge(), secret: '')->assertUnauthorized();

        $this->assertSame(0, Paiement::count());
    }

    /** Un opérateur qui n'est pas des nôtres n'a pas d'adresse. */
    public function test_un_operateur_inconnu_na_pas_dadresse(): void
    {
        $this->postJson('/rappel-paiement/operateur-invente', $this->charge())
            ->assertNotFound();
    }
}
