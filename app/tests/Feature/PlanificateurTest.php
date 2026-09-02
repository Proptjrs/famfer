<?php

namespace Tests\Feature;

use App\Models\Acheteur;
use App\Models\Commande;
use App\Models\Offre;
use App\Models\User;
use App\Models\Vendeur;
use App\Services\CommandeService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le balayage des délais, et le fait qu'il soit bien planifié.
 *
 * La commande « famfer:delais » existait et fonctionnait, mais rien ne
 * l'appelait : le planificateur était vide, et le cron de production lançait
 * « schedule:run » sur du néant. Les trois délais du cycle de commande ne se
 * seraient donc jamais déclenchés en ligne.
 *
 * Une commande juste qu'on n'appelle pas ne vaut rien. Le premier test ci-après
 * ne vérifie pas ce qu'elle fait, mais qu'elle est bien inscrite au
 * planificateur — c'est le contrôle qui manquait.
 */
class PlanificateurTest extends TestCase
{
    use RefreshDatabase;

    /** Les commandes planifiées, telles que Laravel les voit. */
    private function planifiees(): array
    {
        return array_map(
            fn ($evenement) => $evenement->command,
            app(Schedule::class)->events()
        );
    }

    public function test_le_balayage_des_delais_est_planifie(): void
    {
        $inscrites = $this->planifiees();

        $trouvee = collect($inscrites)->contains(
            fn ($commande) => str_contains((string) $commande, 'famfer:delais')
        );

        $this->assertTrue($trouvee, sprintf(
            'famfer:delais doit être inscrite au planificateur, sinon les délais '
            . 'ne se déclenchent jamais en production. Inscrites : %s',
            $inscrites === [] ? 'aucune' : implode(', ', $inscrites)
        ));
    }

    public function test_le_balayage_passe_toutes_les_cinq_minutes(): void
    {
        $evenement = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'famfer:delais'));

        $this->assertNotNull($evenement);

        // Assez fin pour un délai de paiement de quinze minutes.
        $this->assertSame('*/5 * * * *', $evenement->expression);

        // Deux balayages simultanés annuleraient deux fois la même commande.
        $this->assertTrue($evenement->withoutOverlapping);
    }

    // ── Ce que le balayage fait réellement ───────────────────────────────────

    private function commande(): Commande
    {
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

        return app(CommandeService::class)->creer($acheteur, [[
            'offre' => $offre, 'quantite' => '1', 'unite' => $offre->unite_affichee,
        ]]);
    }

    /**
     * Un panier non réglé libère le stock qu'il retenait.
     *
     * C'est la conséquence concrète du bug : sans planification, cette
     * marchandise serait restée réservée pour toujours.
     */
    public function test_une_commande_non_reglee_expire_et_libere_le_stock(): void
    {
        $commande = $this->commande();
        $offre = $commande->lignes->first()->offre;

        $reserveAvant = $offre->fresh()->disponiblePivot();

        // Le délai de paiement est passé.
        $commande->update(['expire_le' => now()->subMinute()]);

        $this->artisan('famfer:delais')->assertSuccessful();

        $this->assertSame('expiree', $commande->fresh()->etat);
        $this->assertGreaterThan($reserveAvant, $offre->fresh()->disponiblePivot());
    }

    /** Une commande encore dans les temps n'est pas touchée. */
    public function test_une_commande_dans_les_temps_est_epargnee(): void
    {
        $commande = $this->commande();

        $this->artisan('famfer:delais')->assertSuccessful();

        $this->assertSame('en_attente_paiement', $commande->fresh()->etat);
    }

    /** Deux passages de suite ne cassent rien : le second ne trouve plus rien. */
    public function test_le_balayage_se_rejoue_sans_dommage(): void
    {
        $commande = $this->commande();
        $commande->update(['expire_le' => now()->subMinute()]);

        $this->artisan('famfer:delais')->assertSuccessful();
        $this->artisan('famfer:delais')->assertSuccessful();

        $this->assertSame('expiree', $commande->fresh()->etat);
    }
}
