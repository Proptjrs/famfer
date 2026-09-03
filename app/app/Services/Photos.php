<?php

namespace App\Services;

use App\Models\PhotoProduit;
use App\Models\Produit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Recevoir les photos des vendeurs.
 *
 * C'est la seule porte du site par laquelle un fichier entre, et c'est donc la
 * plus dangereuse. Trois précautions la gardent, et aucune n'est superflue.
 *
 * **Le type est déduit du contenu, pas du nom.** Un fichier appelé
 * « photo.jpg » peut être n'importe quoi. On lit les dimensions de l'image : ce
 * qui n'en est pas une échoue à ce moment-là, avant d'atteindre le disque.
 *
 * **Le nom est réécrit.** Le vendeur ne choisit ni le dossier ni le nom du
 * fichier : un nom fourni par l'extérieur permet de remonter dans
 * l'arborescence, et une extension fournie par l'extérieur permet de déposer un
 * « .php » dans un dossier servi par le serveur web.
 *
 * **Le dossier n'exécute rien.** Les fichiers vont dans le disque public de
 * Laravel, qui sert des fichiers statiques et ne passe rien à PHP.
 */
class Photos
{
    /** Trois mégaoctets : au-delà, c'est une photo non redimensionnée. */
    public const TAILLE_MAX = 3 * 1024 * 1024;

    /** En pixels : en deçà, la vignette serait floue. */
    public const COTE_MIN = 200;

    /** Huit photos par produit : au-delà, personne ne les regarde. */
    public const NOMBRE_MAX = 8;

    private const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Range une photo et l'attache au produit.
     *
     * @throws RuntimeException fichier refusé, ou produit déjà plein
     */
    public function ajouter(Produit $produit, UploadedFile $fichier, ?string $description = null): PhotoProduit
    {
        if ($produit->photos()->count() >= self::NOMBRE_MAX) {
            throw new RuntimeException(sprintf(
                'Ce produit porte déjà %d photos. Supprimez-en une avant d\'en ajouter.',
                self::NOMBRE_MAX
            ));
        }

        $this->verifier($fichier);

        // Le nom est fabriqué ici : ni le dossier ni l'extension ne viennent du
        // fichier reçu.
        $extension = match ($fichier->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $chemin = sprintf('produits/%d/%s.%s', $produit->id, Str::random(24), $extension);

        Storage::disk('public')->putFileAs(
            dirname($chemin), $fichier, basename($chemin)
        );

        return PhotoProduit::create([
            'produit_id' => $produit->id,
            'chemin' => $chemin,
            'description' => $description,
            // La première arrivée est la vignette ; les suivantes se rangent
            // derrière elle.
            'rang' => (int) $produit->photos()->max('rang') + 1,
        ]);
    }

    /**
     * Contrôle le fichier avant de le poser sur le disque.
     *
     * @throws RuntimeException
     */
    private function verifier(UploadedFile $fichier): void
    {
        if (! $fichier->isValid()) {
            throw new RuntimeException('Le fichier n\'est pas arrivé entier. Réessayez.');
        }

        if ($fichier->getSize() > self::TAILLE_MAX) {
            throw new RuntimeException(sprintf(
                'Image trop lourde : %s. Le maximum est de %d Mo.',
                $this->enMo($fichier->getSize()), self::TAILLE_MAX / 1024 / 1024
            ));
        }

        if (! in_array($fichier->getMimeType(), self::TYPES, true)) {
            throw new RuntimeException('Formats acceptés : JPEG, PNG et WebP.');
        }

        // La lecture des dimensions est le vrai contrôle : un fichier qui n'est
        // pas une image échoue ici, quel que soit son nom ou son type déclaré.
        $mesures = @getimagesize($fichier->getRealPath());

        if ($mesures === false) {
            throw new RuntimeException('Ce fichier n\'est pas une image lisible.');
        }

        [$largeur, $hauteur] = $mesures;

        if ($largeur < self::COTE_MIN || $hauteur < self::COTE_MIN) {
            throw new RuntimeException(sprintf(
                'Image trop petite : %d × %d pixels. Il en faut au moins %d de côté.',
                $largeur, $hauteur, self::COTE_MIN
            ));
        }
    }

    private function enMo(int $octets): string
    {
        return number_format($octets / 1024 / 1024, 1, ',', ' ') . ' Mo';
    }
}
