<?php

namespace Database\Seeders;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Le catalogue de démonstration : rayons, boutiques, produits.
 *
 * Les références viennent du catalogue fourni par le client — 689 produits,
 * 14 rayons, 57 sous-rayons — repris tels quels dans
 * « donnees/catalogue.php ». Les prix y sont indicatifs et dérivés du nom :
 * stables d'un semis à l'autre, mais à remplacer par de vrais relevés avant
 * toute mise en service.
 *
 * Chaque boutique ne tient pas tout le catalogue : c'est ce qui donne son
 * intérêt à la comparaison des prix, et ce qui rend la place de marché
 * différente d'une boutique en ligne.
 */
class CatalogueSeeder extends Seeder
{
    private ?array $images = null;

    /**
     * nom, ville, boutique officielle, écart de prix, commission en pour mille.
     *
     * Les taux diffèrent exprès : une enseigne démarchée qui apporte du volume
     * négocie mieux qu'un nouveau venu, et c'est ce qui rend intéressant le
     * « taux moyen obtenu » du tableau de bord — avec un taux unique, il ne
     * dirait rien que le contrat ne dise déjà.
     */
    private const BOUTIQUES = [
        ['Quincaillerie Ndiaye & Frères', 'Pikine', true, 1.00, 60],
        ['Établissements Sow Métaux', 'Guédiawaye', true, 0.94, 65],
        ['Comptoir du Fer Dakarois', 'Dakar', false, 1.06, 80],
        ['Fer Express Thiaroye', 'Thiaroye', false, 0.98, 80],
    ];

    /** Les marques attribuées par rayon, pour que le filtre serve à quelque chose. */
    private const MARQUES = [
        'Fer et métallerie' => ['SENIRON', 'AFRIMETAL', 'GALVA'],
        'Quincaillerie' => ['SENFIX', 'AFRIMETAL'],
        'Outillage à main' => ['BATIPRO', 'SENFIX'],
        'Outillage électrique' => ['BATIPRO', 'MECAPRO'],
        'Soudure et découpe' => ['SOUDAF', 'BATIPRO'],
        'Peinture et colles' => ['COLORSEN', 'SOUDAF'],
        'Plomberie' => ['AQUASEN', 'SENFIX'],
        'Électricité' => ['ELECSEN', 'AQUASEN'],
        'Pièces auto' => ['MECAPRO', 'AFRIMETAL'],
        'Pièces machines' => ['MECAPRO'],
        'Agriculture' => ['BATIPRO', 'AQUASEN'],
        'Portes et portails' => ['SENFIX', 'AFRIMETAL'],
        'Protection (EPI)' => ['SECURSEN', 'BATIPRO'],
        'Consommables' => ['SENFIX', 'COLORSEN'],
    ];

    public function run(): void
    {
        if (Categorie::count() > 0) {
            $this->command?->warn('Catalogue déjà semé.');

            return;
        }

        $catalogue = require database_path('seeders/donnees/catalogue.php');

        $boutiques = $this->poserLesBoutiques();
        [$rayons, $sousRayons] = $this->poserLesRayons($catalogue);

        $poses = $this->poserLesProduits($catalogue, $rayons, $sousRayons, $boutiques);

        $this->command?->info(sprintf(
            '%d rayons, %d sous-rayons, %d boutiques, %d produits en vente.',
            count($rayons), count($sousRayons), count($boutiques), $poses
        ));
    }

    /** @return array<int, array{modele: Boutique, ecart: float}> */
    private function poserLesBoutiques(): array
    {
        $boutiques = [];

        foreach (self::BOUTIQUES as $i => [$nom, $ville, $officielle, $ecart, $taux]) {
            $utilisateur = User::create([
                'name' => $nom,
                'email' => 'vendeur' . ($i + 1) . '@famfer.sn',
                'password' => 'password',
                'role' => 'vendeur',
                'telephone' => '+221 77 000 00 0' . ($i + 1),
            ]);

            $boutiques[] = [
                'modele' => Boutique::create([
                    'utilisateur_id' => $utilisateur->id,
                    'nom' => $nom,
                    'slug' => Str::slug($nom),
                    'description' => 'Fer, tôles, quincaillerie et outillage. '
                        . 'Livraison sur tout le Sénégal.',
                    'telephone' => $utilisateur->telephone,
                    'adresse' => 'Marché central, ' . $ville,
                    'ville' => $ville,
                    'officielle' => $officielle,
                    'taux_commission_pour_mille' => $taux,
                    // La dernière attend sa validation : la démonstration a
                    // besoin d'un dossier en attente à montrer.
                    'statut' => $i === 3 ? 'en_attente' : 'active',
                ]),
                'ecart' => $ecart,
            ];
        }

        return $boutiques;
    }

    /** @return array{0: array<string, Categorie>, 1: array<string, Categorie>} */
    private function poserLesRayons(array $catalogue): array
    {
        $rayons = [];
        $sousRayons = [];
        $rang = 0;

        foreach ($catalogue as $nom => $bloc) {
            $rayon = Categorie::create([
                'nom' => $nom,
                'slug' => Str::slug($nom),
                'icone' => $bloc['icone'],
                'rang' => $rang++,
            ]);
            $rayons[$nom] = $rayon;

            $sousRang = 0;
            foreach (array_keys($bloc['sous']) as $sous) {
                // Deux rayons peuvent porter un sous-rayon du même nom —
                // « Accessoires », « Protection », « Quincaillerie ». La clé
                // les distingue, et le slug porte celui du rayon.
                $sousRayons[$nom . '|' . $sous] = Categorie::create([
                    'parente_id' => $rayon->id,
                    'nom' => $sous,
                    'slug' => Str::slug($nom . '-' . $sous),
                    'rang' => $sousRang++,
                ] + $this->illustration($sous));
            }
        }

        return [$rayons, $sousRayons];
    }

    private function poserLesProduits(
        array $catalogue, array $rayons, array $sousRayons, array $boutiques
    ): int {
        $lignes = [];
        $maintenant = now();
        $poses = 0;

        foreach ($catalogue as $nomRayon => $bloc) {
            $marques = self::MARQUES[$nomRayon] ?? ['SENFIX'];

            foreach ($bloc['sous'] as $nomSous => $produits) {
                $categorie = $sousRayons[$nomRayon . '|' . $nomSous];

                foreach ($produits as $rang => [$nom, $prixBase, $dessin]) {
                    foreach ($boutiques as $b => $boutique) {
                        // Deux boutiques sur trois tiennent chaque article :
                        // assez pour comparer, pas assez pour que tout le monde
                        // ait tout.
                        if (($rang + $b) % 3 === 2) {
                            continue;
                        }

                        $prix = $this->arrondir($prixBase * $boutique['ecart']);

                        // Une remise sur un article sur cinq, et seulement là :
                        // un prix barré partout ne veut plus rien dire.
                        $barre = ($rang + $b) % 5 === 0
                            ? $this->arrondir($prix * 1.22)
                            : null;

                        $lignes[] = [
                            'boutique_id' => $boutique['modele']->id,
                            'categorie_id' => $categorie->id,
                            'nom' => $nom,
                            // La catégorie entre dans la clé : « Porte-embout »
                            // existe dans deux sous-rayons, et le seul nom ne
                            // suffit donc pas à distinguer les deux fiches.
                            'slug' => Str::slug($nom) . '-' . $boutique['modele']->id
                                . '-' . substr(md5($nom . $categorie->id . $b), 0, 6),
                            'description' => $nom . ' — qualité contrôlée, disponible '
                                . 'en magasin et en livraison.',
                            'marque' => $marques[($rang + $b) % count($marques)],
                            'prix' => $prix,
                            'prix_barre' => $barre,
                            // Une rupture de temps en temps : un catalogue où
                            // tout est en stock ne ressemble à aucun magasin.
                            'stock' => ($rang * 7 + $b * 13) % 17 === 0
                                ? 0
                                : 4 + ($rang * 11 + $b * 23) % 80,
                            'dessin' => $dessin,
                            'actif' => true,
                            'nombre_ventes' => ($rang * 11 + $b * 17) % 40,
                            'created_at' => $maintenant,
                            'updated_at' => $maintenant,
                        ];
                        $poses++;
                    }
                }
            }
        }

        // Insertion par paquets : deux mille appels à « create » prendraient
        // plusieurs minutes, et le semis doit rester relançable sans y penser.
        foreach (array_chunk($lignes, 500) as $paquet) {
            DB::table('produits')->insert($paquet);
        }

        return $poses;
    }

    /**
     * L'illustration d'un sous-rayon, avec son attribution.
     *
     * Les images viennent de Wikimedia Commons, sous licence CC BY, CC BY-SA,
     * CC0 ou domaine public. L'auteur, la licence et la page d'origine sont
     * enregistrés avec elles : les citer est une obligation de ces licences,
     * pas une politesse. Deux sous-rayons n'ont rien trouvé de libre et
     * restent sans image — le dessin au trait y tient la place.
     */
    private function illustration(string $sousRayon): array
    {
        $index = $this->images ??= json_decode(
            file_get_contents(database_path('seeders/donnees/images.json')), true
        );

        foreach ($index as $entree) {
            if ($entree['sous_rayon'] === $sousRayon) {
                return [
                    'image' => $entree['fichier'],
                    'image_auteur' => Str::limit($entree['auteur'], 155, ''),
                    'image_licence' => $entree['licence'],
                    'image_source' => $entree['source'],
                ];
            }
        }

        return [];
    }

    /** Personne n'affiche 14 387 F : on arrondit comme au comptoir. */
    private function arrondir(float $montant): int
    {
        $pas = $montant >= 50_000 ? 1_000 : ($montant >= 10_000 ? 500 : 100);

        return max($pas, (int) round($montant / $pas) * $pas);
    }
}
