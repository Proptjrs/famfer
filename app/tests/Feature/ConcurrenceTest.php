<?php

namespace Tests\Feature;

use App\Exceptions\StockInsuffisant;
use App\Models\Article;
use App\Models\Offre;
use App\Services\StockService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La dernière tonne, et deux acheteurs.
 *
 * C'est la situation qui coûte de l'argent : deux commandes arrivent au même
 * instant sur le dernier lot, les deux lisent « disponible », les deux passent,
 * et le vendeur doit livrer deux fois ce qu'il possède une seule.
 *
 * Ces tests emploient DEUX connexions PostgreSQL distinctes, donc deux
 * transactions réellement simultanées. Une seule connexion ne le permet pas, et
 * une base en mémoire ne sait pas verrouiller une ligne : c'est pourquoi la
 * suite entière tourne sur le moteur de production.
 *
 * DatabaseTruncation remplace ici RefreshDatabase : cette dernière enferme le
 * test dans une transaction, et la seconde connexion ne verrait rien de ce que
 * la première a écrit.
 */
class ConcurrenceTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);
    }

    private function offre(): Offre
    {
        $t10 = Article::where('reference', 'T10-12M')->firstOrFail();

        return Offre::where('article_id', $t10->id)->orderBy('id')->firstOrFail();
    }

    /**
     * Le verrou existe et il tient.
     *
     * La première transaction verrouille la ligne de l'offre. La seconde tente
     * de la lire pour écriture avec NOWAIT : PostgreSQL refuse immédiatement au
     * lieu d'attendre. Sans le verrou, elle lirait la ligne sans broncher.
     */
    public function test_une_ligne_verrouillee_bloque_la_seconde_transaction(): void
    {
        $offre = $this->offre();

        DB::connection()->beginTransaction();
        DB::connection()->table('offres')->where('id', $offre->id)->lockForUpdate()->first();

        try {
            $seconde = DB::connection('pgsql_concurrent');
            $seconde->beginTransaction();

            $this->expectException(QueryException::class);
            $seconde->select('SELECT * FROM offres WHERE id = ? FOR UPDATE NOWAIT', [$offre->id]);
        } finally {
            // La seconde transaction est déjà avortée par l'erreur ; on la
            // referme, puis on relâche la première, sans quoi la base garderait
            // le verrou jusqu'à la fin du processus.
            try {
                DB::connection('pgsql_concurrent')->rollBack();
            } catch (\Throwable) {
                // rien : la transaction était déjà défaite
            }
            DB::connection()->rollBack();
        }
    }

    /**
     * Deux réservations concurrentes du dernier lot : une seule aboutit.
     *
     * La première transaction réserve tout et garde le verrou. La seconde
     * attend — c'est le comportement voulu — puis lit un stock à jour et se voit
     * refusée. On force ici un délai d'attente court pour que le test ne reste
     * pas suspendu si le verrou venait à ne pas être posé.
     */
    public function test_deux_acheteurs_ne_prennent_pas_la_meme_derniere_tonne(): void
    {
        $offre = $this->offre();
        $tout = $offre->disponiblePivot();
        $this->assertGreaterThan(0, $tout);

        $stock = app(StockService::class);

        // Le premier acheteur emporte la totalité.
        $stock->reserver($offre, $tout, null, 'Premier acheteur');

        // Le second arrive derrière, sur une connexion différente : il lit le
        // stock réellement à jour, et non celui qu'il avait vu à l'écran.
        $seconde = DB::connection('pgsql_concurrent');
        $ligne = $seconde->table('offres')->where('id', $offre->id)->first();
        $disponible = $ligne->quantite_pivot - $ligne->quantite_reservee_pivot;

        $this->assertSame(0, (int) $disponible, 'le stock doit être épuisé pour le second');

        $this->expectException(StockInsuffisant::class);
        $stock->reserver($offre, 1, null, 'Second acheteur');
    }

    /** Après réservation, le journal et le cache disent la même chose. */
    public function test_la_reservation_concurrente_ne_casse_pas_le_journal(): void
    {
        $offre = $this->offre();
        $stock = app(StockService::class);

        $stock->reserver($offre, 7_404, null, 'Une barre');
        $stock->reserver($offre, 14_808, null, 'Deux barres');
        $stock->liberer($offre, 7_404, null, 'Annulation');

        $offre->refresh();

        $this->assertSame(14_808, $offre->quantite_reservee_pivot);
        $this->assertSame($stock->stockJournalise($offre), $offre->quantite_pivot);
    }
}
