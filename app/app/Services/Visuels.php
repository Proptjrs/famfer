<?php

namespace App\Services;

use App\Models\PhotoProduit;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * La pose des visuels livrés avec le catalogue.
 *
 * Les fichiers sont nommés d'après le nom normalisé du produit — « tube rond
 * galvanisé » devient « tube-rond-galvanise.webp ». Aucune table de
 * correspondance à maintenir : le nom du fichier *est* la correspondance.
 *
 * Ce service est appelé de deux endroits, et c'est la raison d'être de sa
 * séparation.
 *
 * Le semeur l'appelle au premier remplissage. Mais il s'arrête net si le
 * catalogue existe déjà, et le disque d'un conteneur ne survit pas à un
 * redéploiement : la base garderait ses lignes « photos_produit » pendant que
 * les fichiers, eux, auraient disparu. Chaque fiche afficherait alors un cadre
 * vide — sans la moindre erreur, puisqu'une image manquante ne lève rien.
 *
 * La commande « famfer:visuels » l'appelle donc aussi, à chaque démarrage. Le
 * service est écrit pour ça : il repose ce qui manque et ne touche pas à ce qui
 * est déjà en place, y compris les photos téléversées par les vendeurs, qui ne
 * portent pas de nom de fichier reconnaissable.
 */
class Visuels
{
    /** Le dossier des visuels livrés avec le dépôt. */
    public function dossier(): string
    {
        return database_path('seeders/donnees/photos');
    }

    /**
     * Repose les visuels manquants.
     *
     * @return array{poses: int, refaits: int, ignores: int}
     */
    public function poser(): array
    {
        $disponibles = $this->disponibles();

        if (! $disponibles) {
            return ['poses' => 0, 'refaits' => 0, 'ignores' => 0];
        }

        $poses = $refaits = $ignores = 0;
        $rangees = [];
        $maintenant = now();

        Produit::query()->select('id', 'nom')->chunkById(500, function ($lot) use (
            $disponibles, &$poses, &$refaits, &$ignores, &$rangees, $maintenant
        ) {
            foreach ($lot as $produit) {
                $cle = Str::slug($produit->nom);

                if (! isset($disponibles[$cle])) {
                    continue;
                }

                $chemin = sprintf('produits/%d/%s.webp', $produit->id, $cle);
                $connu = PhotoProduit::where('chemin', $chemin)->exists();

                // Le fichier d'abord : c'est lui qui manque après un
                // redéploiement, la ligne en base ayant survécu.
                if (! Storage::disk('public')->exists($chemin)) {
                    Storage::disk('public')->put(
                        $chemin, file_get_contents($disponibles[$cle])
                    );
                    $connu ? $refaits++ : $poses++;
                } elseif ($connu) {
                    $ignores++;
                    continue;
                }

                if (! $connu) {
                    $rangees[] = [
                        'produit_id' => $produit->id,
                        'chemin' => $chemin,
                        'description' => $produit->nom,
                        'rang' => 0,
                        'created_at' => $maintenant,
                        'updated_at' => $maintenant,
                    ];
                }
            }
        });

        foreach (array_chunk($rangees, 500) as $paquet) {
            DB::table('photos_produit')->insert($paquet);
        }

        return ['poses' => $poses, 'refaits' => $refaits, 'ignores' => $ignores];
    }

    /** @return array<string, string> clé du produit => chemin du fichier livré */
    private function disponibles(): array
    {
        $dossier = $this->dossier();

        if (! is_dir($dossier)) {
            return [];
        }

        $trouves = [];
        foreach (glob($dossier . '/*.webp') ?: [] as $fichier) {
            $trouves[basename($fichier, '.webp')] = $fichier;
        }

        return $trouves;
    }
}
