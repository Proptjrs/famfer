<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\PhotoProduit;
use App\Models\Produit;
use App\Models\User;
use App\Services\Photos;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Les photos des produits.
 *
 * C'est la seule porte du site par laquelle un fichier entre, donc la plus
 * dangereuse. Ce qui est éprouvé ici n'est pas l'affichage mais le refus : un
 * fichier qui n'est pas une image, un nom qui remonte l'arborescence, une
 * extension qui pourrait s'exécuter, un vendeur qui touche au produit d'un
 * autre.
 */
class PhotosTest extends TestCase
{
    use RefreshDatabase;

    private Produit $produit;
    private User $vendeur;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->seed(CatalogueSeeder::class);

        $boutique = Boutique::where('statut', 'active')->orderBy('id')->firstOrFail();
        $this->vendeur = $boutique->utilisateur;
        $this->produit = Produit::where('boutique_id', $boutique->id)->orderBy('id')->firstOrFail();
    }

    private function image(int $largeur = 800, int $hauteur = 800, string $nom = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nom, $largeur, $hauteur);
    }

    // ── Ce qui passe ─────────────────────────────────────────────────────────

    public function test_une_photo_se_televerse_et_devient_la_vignette(): void
    {
        $photo = app(Photos::class)->ajouter($this->produit, $this->image());

        Storage::disk('public')->assertExists($photo->chemin);
        $this->assertSame($photo->id, $this->produit->fresh()->vignette()->id);
    }

    /** La première reste la vignette, les suivantes se rangent derrière. */
    public function test_lordre_des_photos_suit_larrivee(): void
    {
        $service = app(Photos::class);
        $premiere = $service->ajouter($this->produit, $this->image(nom: 'a.jpg'));
        $seconde = $service->ajouter($this->produit, $this->image(nom: 'b.jpg'));

        $this->assertLessThan($seconde->rang, $premiere->rang);
        $this->assertSame($premiere->id, $this->produit->fresh()->vignette()->id);
    }

    /** Supprimer l'enregistrement efface le fichier : un disque se paie. */
    public function test_supprimer_une_photo_efface_le_fichier(): void
    {
        $photo = app(Photos::class)->ajouter($this->produit, $this->image());
        $chemin = $photo->chemin;

        $photo->delete();

        Storage::disk('public')->assertMissing($chemin);
    }

    public function test_supprimer_le_produit_emporte_ses_photos(): void
    {
        app(Photos::class)->ajouter($this->produit, $this->image());
        $this->assertSame(1, PhotoProduit::count());

        $this->produit->delete();

        $this->assertSame(0, PhotoProduit::count());
    }

    // ── Ce qui est refusé ────────────────────────────────────────────────────

    /**
     * Un fichier qui n'est pas une image ne passe pas, quel que soit son nom.
     *
     * C'est le contrôle qui compte : le type déclaré et l'extension viennent de
     * l'extérieur et ne prouvent rien. Seule la lecture des dimensions le fait.
     */
    public function test_un_fichier_qui_nest_pas_une_image_est_refuse(): void
    {
        $faux = UploadedFile::fake()->createWithContent(
            'photo.jpg', '<?php echo "rien à faire ici"; ?>'
        );

        $this->expectException(RuntimeException::class);
        app(Photos::class)->ajouter($this->produit, $faux);
    }

    public function test_une_image_trop_petite_est_refusee(): void
    {
        $this->expectExceptionMessageMatches('/trop petite/');
        app(Photos::class)->ajouter($this->produit, $this->image(100, 100));
    }

    public function test_une_image_trop_lourde_est_refusee(): void
    {
        $lourde = UploadedFile::fake()->image('grande.jpg', 4000, 4000)
            ->size((int) (Photos::TAILLE_MAX / 1024) + 500);

        $this->expectExceptionMessageMatches('/trop lourde/');
        app(Photos::class)->ajouter($this->produit, $lourde);
    }

    public function test_on_ne_depasse_pas_huit_photos(): void
    {
        $service = app(Photos::class);

        for ($i = 0; $i < Photos::NOMBRE_MAX; $i++) {
            $service->ajouter($this->produit, $this->image(nom: "p{$i}.jpg"));
        }

        $this->expectExceptionMessageMatches('/déjà 8 photos/');
        $service->ajouter($this->produit->fresh(), $this->image(nom: 'neuvieme.jpg'));
    }

    // ── Le nom du fichier ────────────────────────────────────────────────────

    /**
     * Le nom est réécrit, jamais repris.
     *
     * Un nom fourni par l'extérieur permet de remonter l'arborescence, et une
     * extension fournie par l'extérieur permet de déposer un « .php » dans un
     * dossier servi par le serveur web.
     */
    public function test_le_nom_du_fichier_est_reecrit(): void
    {
        $piege = UploadedFile::fake()->image('../../evasion.php.jpg', 600, 600);

        $photo = app(Photos::class)->ajouter($this->produit, $piege);

        $this->assertStringStartsWith("produits/{$this->produit->id}/", $photo->chemin);
        $this->assertStringNotContainsString('..', $photo->chemin);
        $this->assertStringNotContainsString('evasion', $photo->chemin);
        $this->assertStringEndsWith('.jpg', $photo->chemin);
        Storage::disk('public')->assertExists($photo->chemin);
    }

    /** Le dossier suit le produit : deux vendeurs ne se mélangent pas. */
    public function test_chaque_produit_a_son_dossier(): void
    {
        $autre = Produit::where('boutique_id', $this->produit->boutique_id)
            ->where('id', '!=', $this->produit->id)->firstOrFail();

        $a = app(Photos::class)->ajouter($this->produit, $this->image());
        $b = app(Photos::class)->ajouter($autre, $this->image());

        $this->assertStringStartsWith("produits/{$this->produit->id}/", $a->chemin);
        $this->assertStringStartsWith("produits/{$autre->id}/", $b->chemin);
    }

    // ── Par le web ───────────────────────────────────────────────────────────

    public function test_le_vendeur_televerse_depuis_sa_fiche(): void
    {
        $this->actingAs($this->vendeur)
            ->post(route('vendeur.produit.photos', $this->produit), [
                'photos' => [$this->image(nom: 'a.jpg'), $this->image(nom: 'b.jpg')],
            ])->assertRedirect();

        $this->assertSame(2, $this->produit->fresh()->photos->count());
    }

    /** Une image refusée n'empêche pas les autres de passer. */
    public function test_les_images_valables_passent_malgre_une_refusee(): void
    {
        $this->actingAs($this->vendeur)
            ->post(route('vendeur.produit.photos', $this->produit), [
                'photos' => [$this->image(nom: 'bonne.jpg'), $this->image(50, 50, 'minuscule.jpg')],
            ])->assertSessionHas('erreur');

        $this->assertSame(1, $this->produit->fresh()->photos->count());
    }

    public function test_un_vendeur_ne_televerse_pas_chez_un_autre(): void
    {
        $autre = Boutique::where('id', '!=', $this->produit->boutique_id)
            ->orderBy('id')->firstOrFail();
        $sonProduit = Produit::where('boutique_id', $autre->id)->firstOrFail();

        $this->actingAs($this->vendeur)
            ->post(route('vendeur.produit.photos', $sonProduit), ['photos' => [$this->image()]])
            ->assertForbidden();

        $this->assertSame(0, $sonProduit->fresh()->photos->count());
    }

    public function test_un_vendeur_ne_supprime_pas_la_photo_dun_autre(): void
    {
        $autre = Boutique::where('id', '!=', $this->produit->boutique_id)
            ->orderBy('id')->firstOrFail();
        $sonProduit = Produit::where('boutique_id', $autre->id)->firstOrFail();
        $saPhoto = app(Photos::class)->ajouter($sonProduit, $this->image());

        $this->actingAs($this->vendeur)
            ->delete(route('vendeur.photo.supprimer', $saPhoto))->assertForbidden();

        $this->assertNotNull($saPhoto->fresh());
        Storage::disk('public')->assertExists($saPhoto->chemin);
    }

    // ── L'affichage ──────────────────────────────────────────────────────────

    /** La photo remplace le dessin partout où le produit s'affiche. */
    public function test_la_photo_saffiche_a_la_place_du_dessin(): void
    {
        $photo = app(Photos::class)->ajouter($this->produit, $this->image());

        $this->get(route('produit', $this->produit))->assertOk()
            ->assertSee($photo->chemin, false);
    }

    /** Sans photo, le dessin tient la place : pas de cadre vide. */
    public function test_sans_photo_le_dessin_tient_la_place(): void
    {
        $this->assertNull($this->produit->vignette());

        $this->get(route('produit', $this->produit))->assertOk()
            ->assertSee('<svg', false);
    }
}
