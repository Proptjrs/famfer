<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Produit;
use App\Services\Catalogue;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\PhotoProduit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Parcourir et chercher le catalogue.
 *
 * Deux règles gouvernent tout le reste : rien de ce qui n'est pas achetable ne
 * doit apparaître en rayon, et un rayon doit montrer ce que contiennent ses
 * sous-rayons — sinon la page principale d'un rayon paraît vide alors que le
 * catalogue est plein.
 */
class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
    }

    private function catalogue(): Catalogue
    {
        return app(Catalogue::class);
    }

    // ── Ce qui n'a rien à faire en rayon ─────────────────────────────────────

    public function test_un_produit_retire_de_la_vente_disparait(): void
    {
        $p = Produit::where('actif', true)->orderBy('id')->firstOrFail();

        $this->assertTrue($this->trouve($p));
        $p->update(['actif' => false]);
        $this->assertFalse($this->trouve($p));
    }

    /**
     * Une boutique fermée emporte ses produits.
     *
     * C'est ce qui donne du poids à la suspension : sans cela, suspendre une
     * boutique ne changerait rien pour l'acheteur.
     */
    public function test_les_produits_dune_boutique_suspendue_disparaissent(): void
    {
        $b = Boutique::where('statut', 'active')->orderBy('id')->firstOrFail();
        $p = Produit::where('boutique_id', $b->id)->where('actif', true)->firstOrFail();

        $this->assertTrue($this->trouve($p));
        $b->update(['statut' => 'suspendue']);
        $this->assertFalse($this->trouve($p));
    }

    // ── Les rayons ───────────────────────────────────────────────────────────

    /** Un rayon montre ce que contiennent ses sous-rayons. */
    public function test_un_rayon_remonte_ses_sous_rayons(): void
    {
        $rayon = Categorie::whereNull('parente_id')->orderBy('rang')->firstOrFail();

        $this->assertNotEmpty($rayon->enfants, 'Le semis doit poser des sous-rayons.');
        // Aucun produit n'est rattaché au rayon lui-même : ils vivent tous dans
        // ses enfants. Le compte doit pourtant être positif.
        $this->assertSame(0, Produit::where('categorie_id', $rayon->id)->count());

        $liste = $this->catalogue()->chercher(['categorie' => $rayon]);
        $this->assertGreaterThan(0, $liste->total());
    }

    public function test_le_compte_par_rayon_traverse_les_sous_rayons(): void
    {
        $rayons = Categorie::rayonsAvecCompte();

        // On ne fige pas le nombre : le catalogue vient d'un fichier de
        // données qui grandit. Ce qui doit tenir, c'est qu'aucun rayon
        // n'affiche zéro alors que ses sous-rayons sont pleins.
        $this->assertSame(
            Categorie::whereNull('parente_id')->count(),
            $rayons->count()
        );
        foreach ($rayons as $r) {
            $this->assertGreaterThan(0, $r->produits_count, "Le rayon « {$r->nom} » compte zéro.");
        }

        // Et la somme retombe sur le catalogue entier.
        $this->assertSame(
            Produit::where('actif', true)->count(),
            $rayons->sum('produits_count')
        );
    }

    // ── La recherche ─────────────────────────────────────────────────────────

    public function test_la_recherche_exige_tous_les_mots(): void
    {
        $large = $this->catalogue()->chercher(['q' => 'fer'])->total();
        $etroit = $this->catalogue()->chercher(['q' => 'fer plat'])->total();

        $this->assertGreaterThan(0, $etroit);
        $this->assertLessThan($large, $etroit);
    }

    public function test_la_recherche_trouve_par_la_marque(): void
    {
        $marque = Produit::whereNotNull('marque')->value('marque');

        $this->assertGreaterThan(0, $this->catalogue()->chercher(['q' => $marque])->total());
    }

    // ── Les filtres et les tris ──────────────────────────────────────────────

    public function test_le_filtre_de_prix_ecarte_le_reste(): void
    {
        $plafond = 10_000;
        $liste = $this->catalogue()->chercher(['max' => $plafond], 100);

        $this->assertNotEmpty($liste->items());
        foreach ($liste as $p) {
            $this->assertLessThanOrEqual($plafond, $p->prix);
        }
        $this->assertLessThan(
            $this->catalogue()->chercher([], 100)->total(), $liste->total()
        );
    }

    public function test_le_filtre_en_stock_ecarte_les_ruptures(): void
    {
        Produit::where('actif', true)->orderBy('id')->limit(3)->update(['stock' => 0]);

        foreach ($this->catalogue()->chercher(['stock' => true], 100) as $p) {
            $this->assertGreaterThan(0, $p->stock);
        }
    }

    public function test_le_tri_par_prix_ordonne_vraiment(): void
    {
        $prix = collect($this->catalogue()->chercher(['tri' => 'prix'], 30)->items())
            ->pluck('prix')->all();

        $this->assertSame($prix, collect($prix)->sort()->values()->all());
    }

    /**
     * Le tri par défaut met devant ce qui est en stock.
     *
     * Proposer d'abord ce qu'on ne peut pas acheter est le meilleur moyen de
     * perdre un client dès la première page.
     */
    public function test_le_tri_par_defaut_met_le_stock_devant(): void
    {
        Produit::where('actif', true)->orderBy('id')->limit(5)->update(['stock' => 0]);

        $premiers = collect($this->catalogue()->chercher([], 20)->items());

        $this->assertTrue(
            $premiers->take(10)->every(fn (Produit $p) => $p->stock > 0),
            'Des produits en rupture remontent en tête de liste.'
        );
    }

    // ── La remise ────────────────────────────────────────────────────────────

    /** La remise se calcule des deux prix, elle ne se saisit pas. */
    public function test_la_remise_se_deduit_des_prix(): void
    {
        $p = new Produit(['prix' => 6_000, 'prix_barre' => 10_000]);
        $this->assertSame(40, $p->remise());

        $this->assertNull((new Produit(['prix' => 6_000]))->remise());
        // Un prix barré inférieur ou égal n'est pas une remise.
        $this->assertNull((new Produit(['prix' => 6_000, 'prix_barre' => 5_000]))->remise());
        $this->assertNull((new Produit(['prix' => 6_000, 'prix_barre' => 6_000]))->remise());
    }

    public function test_les_promotions_ne_montrent_que_de_vraies_remises(): void
    {
        foreach ($this->catalogue()->enPromotion(20) as $p) {
            $this->assertNotNull($p->remise());
            $this->assertGreaterThan(0, $p->remise());
            $this->assertGreaterThan(0, $p->stock);
        }
    }

    // ── Les pages ────────────────────────────────────────────────────────────

    public function test_les_pages_publiques_repondent(): void
    {
        $p = Produit::where('actif', true)
            ->whereHas('boutique', fn ($q) => $q->where('statut', 'active'))->firstOrFail();

        $this->get(route('accueil'))->assertOk();
        $this->get(route('recherche', ['q' => 'fer']))->assertOk();
        $this->get(route('rayon', Categorie::whereNull('parente_id')->first()))->assertOk();
        $this->get(route('produit', $p))->assertOk()->assertSee($p->nom);
        $this->get(route('boutique', $p->boutique))->assertOk();
        $this->get(route('conditions'))->assertOk();
    }

    /** La fiche d'un produit retiré n'est plus atteignable, même par son adresse. */
    public function test_la_fiche_dun_produit_retire_renvoie_404(): void
    {
        $p = Produit::where('actif', true)->firstOrFail();
        $p->update(['actif' => false]);

        $this->get(route('produit', $p))->assertNotFound();
    }

    public function test_la_vitrine_dune_boutique_non_validee_renvoie_404(): void
    {
        $b = Boutique::where('statut', 'en_attente')->firstOrFail();

        $this->get(route('boutique', $b))->assertNotFound();
    }

    private function trouve(Produit $p): bool
    {
        return collect($this->catalogue()->chercher(['q' => $p->nom], 100)->items())
            ->contains('id', $p->id);
    }

    // ── Les visuels de produit ───────────────────────────────────────────────

    /**
     * Les visuels fournis sont bien attachés aux produits qu'ils illustrent.
     *
     * Le rapprochement se fait par le nom du fichier, normalisé comme le nom du
     * produit : il n'y a pas de table de correspondance à maintenir, mais rien
     * n'avertit non plus quand elle se rompt. Un fichier renommé, un produit
     * rebaptisé, et l'image disparaît sans erreur — le repli à trois étages
     * masque la panne au lieu de la signaler.
     */
    public function test_les_visuels_fournis_sont_attaches(): void
    {
        $fichiers = glob(database_path('seeders/donnees/photos/*.webp'));

        if (! $fichiers) {
            $this->markTestSkipped('Aucun visuel livré avec le catalogue.');
        }

        $this->assertGreaterThan(0, PhotoProduit::count(),
            'Des visuels sont livrés mais aucun n\'est attaché : le rapprochement '
            . 'par nom de fichier est rompu.');

        // Chaque fichier livré doit trouver au moins un produit.
        $orphelins = [];
        foreach ($fichiers as $f) {
            $cle = basename($f, '.webp');
            if (! Produit::where('nom', 'ilike', '%')->get()
                    ->contains(fn ($p) => Str::slug($p->nom) === $cle)) {
                $orphelins[] = $cle;
            }
        }

        $this->assertEmpty($orphelins,
            'Ces visuels ne correspondent à aucun produit : ' . implode(', ', $orphelins));
    }

    /** Le fichier existe réellement là où l'enregistrement le désigne. */
    public function test_chaque_photo_pointe_sur_un_fichier_present(): void
    {
        if (PhotoProduit::count() === 0) {
            $this->markTestSkipped('Aucune photo posée.');
        }

        foreach (PhotoProduit::limit(20)->get() as $photo) {
            $this->assertTrue(
                Storage::disk('public')->exists($photo->chemin),
                "La photo « {$photo->chemin} » est enregistrée mais absente du disque : "
                . 'la fiche afficherait un cadre vide.'
            );
        }
    }

    /**
     * Le même article vendu par deux boutiques a sa propre copie du fichier.
     *
     * Un fichier partagé entre deux fiches se supprimerait avec la première, et
     * la seconde afficherait un cadre vide — « PhotoProduit » efface le fichier
     * avec l'enregistrement.
     */
    public function test_deux_boutiques_ne_partagent_pas_le_meme_fichier(): void
    {
        $doublons = PhotoProduit::select('chemin')
            ->groupBy('chemin')->havingRaw('count(*) > 1')->pluck('chemin');

        $this->assertEmpty($doublons->all(),
            'Ces fichiers sont partagés par plusieurs fiches : ' . $doublons->implode(', '));
    }
}
