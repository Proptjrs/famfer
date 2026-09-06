<?php

namespace Database\Seeders;

use App\Models\Produit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Attache aux produits les visuels fournis avec le catalogue.
 *
 * Séparé du semeur principal pour pouvoir tourner seul :
 * « php artisan db:seed --class=PhotosSeeder » pose les photos sur une base
 * déjà remplie, sans rien effacer. Repasser le catalogue entier pour ajouter
 * des images obligerait à détruire les commandes en cours.
 *
 * Les fichiers sont nommés d'après le nom normalisé du produit — « Tube rond
 * galvanisé » devient « tube-rond-galvanise.webp ». Il n'y a donc aucune table
 * de correspondance à maintenir : le nom du fichier **est** la correspondance,
 * et un visuel ajouté demain se pose sans toucher au code.
 *
 * Le même article se vend chez plusieurs boutiques, et chaque exemplaire reçoit
 * sa propre copie du fichier. C'est un peu de disque contre beaucoup de
 * simplicité : une photo partagée entre deux fiches disparaîtrait avec la
 * première supprimée, et la seconde afficherait un cadre vide — le modèle
 * « PhotoProduit » efface le fichier avec l'enregistrement.
 *
 * La couverture est partielle et le restera : les visuels disponibles ne
 * couvrent qu'une fraction du catalogue. Les autres produits retombent sur
 * l'illustration de leur sous-rayon, puis sur le dessin au trait — c'est
 * précisément à cela que sert le repli à trois étages.
 */
class PhotosSeeder extends Seeder
{
    public function run(): void
    {
        $source = database_path('seeders/donnees/photos');

        if (! is_dir($source)) {
            $this->command?->warn('Aucun dossier de photos : rien à poser.');

            return;
        }

        $disponibles = [];
        foreach (glob($source . '/*.webp') as $fichier) {
            $disponibles[basename($fichier, '.webp')] = $fichier;
        }

        if (! $disponibles) {
            return;
        }

        // Les produits déjà illustrés sont laissés tels quels : le semeur doit
        // pouvoir se relancer sans empiler les doublons ni écraser la photo
        // qu'un vendeur aurait téléversée lui-même.
        $dejaVus = DB::table('photos_produit')->pluck('produit_id')->flip();

        $rangees = [];
        $maintenant = now();

        Produit::query()->select('id', 'nom')->orderBy('id')
            ->chunk(500, function ($lot) use ($disponibles, $dejaVus, &$rangees, $maintenant) {
                foreach ($lot as $produit) {
                    $cle = Str::slug($produit->nom);

                    if (isset($dejaVus[$produit->id]) || ! isset($disponibles[$cle])) {
                        continue;
                    }

                    $chemin = sprintf('produits/%d/%s.webp', $produit->id, $cle);
                    Storage::disk('public')->put(
                        $chemin, file_get_contents($disponibles[$cle])
                    );

                    $rangees[] = [
                        'produit_id' => $produit->id,
                        'chemin' => $chemin,
                        // L'alternative textuelle : le nom du produit la donne
                        // mieux qu'une formule générique.
                        'description' => $produit->nom,
                        'rang' => 0,
                        'created_at' => $maintenant,
                        'updated_at' => $maintenant,
                    ];
                }
            });

        foreach (array_chunk($rangees, 500) as $paquet) {
            DB::table('photos_produit')->insert($paquet);
        }

        $this->command?->info(sprintf(
            '%d photo(s) posée(s) depuis %d visuel(s) disponibles.',
            count($rangees), count($disponibles)
        ));
    }
}
