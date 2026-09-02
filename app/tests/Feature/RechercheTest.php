<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Famille;
use App\Models\Offre;
use App\Models\Vendeur;
use App\Services\RechercheService;
use App\Services\StockService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\VendeursSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RechercheTest extends TestCase
{
    use RefreshDatabase;

    private RechercheService $recherche;

    /** Un carrefour de Pikine, pris comme position de l'acheteur. */
    private const ACHETEUR_LAT = 14.7547;
    private const ACHETEUR_LNG = -17.3906;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(VendeursSeeder::class);
        $this->recherche = app(RechercheService::class);
    }

    private function t10(): Article
    {
        return Article::where('reference', 'T10-12M')->firstOrFail();
    }

    /**
     * Personne ne tape « fer à béton haute adhérence T10 ».
     *
     * Les acheteurs disent « fer 10 ». Si la recherche ne le comprend pas, la
     * plateforme est inutilisable pour ceux à qui elle s'adresse.
     */
    public function test_le_vocabulaire_du_chantier_trouve_larticle(): void
    {
        foreach (['fer 10', 'FER 10', 'T10', 'ha10', 'fer a beton 10', 'fer à béton 10'] as $frappe) {
            $trouves = $this->recherche->articles($frappe);

            $this->assertTrue(
                $trouves->contains('reference', 'T10-12M'),
                "« {$frappe} » ne trouve pas le T10"
            );
        }
    }

    /** « fer 10 » ne doit pas remonter tout le catalogue des fers. */
    public function test_la_recherche_exige_tous_les_mots(): void
    {
        $trouves = $this->recherche->articles('fer 10');

        $this->assertTrue($trouves->contains('reference', 'T10-12M'));
        $this->assertFalse($trouves->contains('reference', 'T25-12M'));
    }

    public function test_une_recherche_vide_ne_renvoie_rien(): void
    {
        $this->assertCount(0, $this->recherche->articles('   '));
    }

    public function test_les_offres_sont_classees_par_prix_au_gramme(): void
    {
        $offres = $this->recherche->offres($this->t10());

        $this->assertCount(3, $offres);
        $this->assertSame('Comptoir du Fer Dakarois', $offres[0]->vendeur->raison_sociale);

        $prix = $offres->map(fn (Offre $o) => $o->prixParPivot())->all();
        $this->assertSame($prix, collect($prix)->sort()->values()->all(), 'le classement par prix est faux');
    }

    public function test_le_classement_par_distance_place_le_plus_proche_en_tete(): void
    {
        $offres = $this->recherche->offres(
            $this->t10(), self::ACHETEUR_LAT, self::ACHETEUR_LNG, 'distance'
        );

        // L'acheteur est au pied de la quincaillerie Ndiaye.
        $this->assertSame('Quincaillerie Ndiaye & Frères', $offres[0]->vendeur->raison_sociale);
        $this->assertLessThan(0.5, $offres[0]->distance_km);

        $distances = $offres->map(fn (Offre $o) => $o->distance_km)->all();
        $this->assertSame($distances, collect($distances)->sort()->values()->all());
    }

    public function test_un_vendeur_non_verifie_napparait_pas_dans_les_offres(): void
    {
        $attente = Vendeur::where('statut', 'en_attente')->firstOrFail();

        $this->assertFalse(
            $this->recherche->offres($this->t10())->contains(
                fn (Offre $o) => $o->vendeur_id === $attente->id
            )
        );
    }

    /** Une offre en rupture n'a rien à faire dans une liste de prix. */
    public function test_une_offre_en_rupture_disparait(): void
    {
        $offre = $this->recherche->offres($this->t10())->first();
        app(StockService::class)->reserver($offre, $offre->disponiblePivot());

        $this->assertFalse(
            $this->recherche->offres($this->t10())->contains('id', $offre->id)
        );
    }

    /**
     * Les filtres et la pagination de la page d'accueil.
     *
     * Sans eux, le catalogue entier tombait d'un bloc et « le moins cher »
     * n'était trouvable qu'à l'œil. C'est ce que fait n'importe quelle place de
     * marché, et ce qui manquait ici.
     */
    public function test_la_liste_se_pagine(): void
    {
        $page = app(RechercheService::class)->paginer('', parPage: 5);

        $this->assertSame(5, $page->count());
        $this->assertGreaterThan(5, $page->total());
        $this->assertTrue($page->hasMorePages());

        // Deuxième page : d'autres articles, jamais les mêmes.
        $this->app['request']->merge(['page' => 2]);
        \Illuminate\Pagination\Paginator::currentPageResolver(fn () => 2);
        $suite = app(RechercheService::class)->paginer('', parPage: 5);

        $this->assertEmpty(
            $page->pluck('id')->intersect($suite->pluck('id')),
            'Une même référence ne doit pas figurer sur deux pages.'
        );
        \Illuminate\Pagination\Paginator::currentPageResolver(fn () => 1);
    }

    public function test_le_filtre_de_famille_restreint_la_liste(): void
    {
        $famille = Famille::where('nom', 'like', '%béton%')->orderBy('id')->first()
            ?? Famille::orderBy('id')->firstOrFail();

        $page = app(RechercheService::class)->paginer('', $famille->id, parPage: 50);

        $this->assertNotEmpty($page->items());
        foreach ($page as $article) {
            $this->assertSame($famille->id, $article->famille_id);
        }
    }

    /** Le prix maximum porte sur la meilleure offre visible, pas sur un tarif théorique. */
    public function test_le_filtre_de_prix_ecarte_le_trop_cher(): void
    {
        $plafond = 3_000;
        $page = app(RechercheService::class)->paginer('', prixMax: $plafond, parPage: 50);

        foreach ($page as $article) {
            $meilleure = $article->offresVisibles()->sortBy(fn ($o) => $o->prix_par_unite)->first();
            $this->assertNotNull($meilleure, 'Un article sans offre ne peut pas passer le filtre.');
            $this->assertLessThanOrEqual($plafond, $meilleure->prix_par_unite);
        }

        // Et le filtre écarte réellement quelque chose : sans quoi le test
        // passerait sur une liste vide comme sur une liste complète.
        $sansFiltre = app(RechercheService::class)->paginer('', parPage: 50);
        $this->assertLessThan($sansFiltre->count(), $page->count());
    }

    public function test_laccueil_repond_aux_filtres(): void
    {
        $famille = Famille::orderBy('id')->firstOrFail();

        $this->get(route('accueil', ['famille' => $famille->id]))
            ->assertOk()
            ->assertSee('article');
    }
}
