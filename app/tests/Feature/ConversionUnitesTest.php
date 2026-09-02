<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Services\ConversionUnites;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Les conversions d'unités, sur lesquelles tout le reste s'appuie.
 *
 * Une erreur ici ne se voit pas : elle se dépose, mouvement après mouvement,
 * jusqu'à ce que le stock théorique et le stock réel divergent d'une barre, puis
 * d'une tonne. C'est pourquoi ces tests vérifient l'exactitude au gramme, et non
 * une approximation.
 */
class ConversionUnitesTest extends TestCase
{
    use RefreshDatabase;

    private ConversionUnites $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->c = app(ConversionUnites::class);
    }

    private function article(string $reference): Article
    {
        return Article::where('reference', $reference)->firstOrFail();
    }

    /**
     * Les masses linéiques doivent correspondre au barème que tout ferrailleur
     * connaît par cœur. Si ces valeurs bougent, les prix au kilo deviennent faux.
     */
    public function test_les_masses_lineiques_suivent_le_bareme(): void
    {
        $bareme = [
            'T6-12M' => 222, 'T8-12M' => 395, 'T10-12M' => 617, 'T12-12M' => 888,
            'T14-12M' => 1208, 'T16-12M' => 1578, 'T20-12M' => 2466,
            'T25-12M' => 3853, 'T32-12M' => 6313,
        ];

        foreach ($bareme as $reference => $grammesParMetre) {
            $this->assertSame(
                $grammesParMetre,
                $this->article($reference)->caracteristiques['masse_lineique_g_m'],
                "masse linéique erronée pour {$reference}"
            );
        }
    }

    public function test_une_barre_de_t10_pese_sept_kilos_quatre(): void
    {
        // 617 g/m × 12 m = 7 404 g
        $this->assertSame(7_404, $this->c->versPivot($this->article('T10-12M'), 'barre', 1));
    }

    public function test_une_tonne_de_t10_fait_cent_trente_cinq_barres(): void
    {
        $t10 = $this->article('T10-12M');
        $pivot = $this->c->versPivot($t10, 'tonne', 1);

        $this->assertSame(1_000_000, $pivot);
        $this->assertSame(135, $this->c->nombreEntier($t10, 'barre', $pivot));
    }

    /** Un aller-retour ne doit rien perdre : c'est la garantie du pivot entier. */
    public function test_aller_retour_sans_perte(): void
    {
        $t16 = $this->article('T16-12M');

        foreach (['1', '2.5', '17', '340'] as $quantite) {
            $pivot = $this->c->versPivot($t16, 'barre', $quantite);
            $this->assertSame(
                $quantite,
                $this->c->depuisPivot($t16, 'barre', $pivot),
                "aller-retour faux pour {$quantite} barres"
            );
        }
    }

    /**
     * Refuser plutôt qu'arrondir en silence.
     *
     * Un tiers de barre ne correspond à aucun nombre entier de grammes. Arrondir
     * ferait disparaître de la marchandise sans que personne ne s'en aperçoive.
     */
    public function test_une_quantite_qui_ne_tombe_pas_juste_est_refusee(): void
    {
        $this->expectException(RuntimeException::class);
        $this->c->versPivot($this->article('T10-12M'), 'barre', '0.3333');
    }

    public function test_une_unite_non_declaree_est_refusee(): void
    {
        $this->expectException(RuntimeException::class);
        // On ne vend pas le fer à béton au mètre carré.
        $this->c->versPivot($this->article('T10-12M'), 'metre_carre', 1);
    }

    /** Les tôles se comptent à la feuille : un autre article, un autre pivot. */
    public function test_les_toles_se_comptent_a_la_feuille(): void
    {
        $tole = $this->article('TOLE-BAC-6M-30');

        $this->assertSame(125_000, $this->c->versPivot($tole, 'feuille', 10));
        $this->assertSame('125', $this->c->depuisPivot($tole, 'kilogramme', 125_000));
    }
}
