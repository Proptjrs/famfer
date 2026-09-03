<?php

namespace Database\Seeders;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Le catalogue de démonstration : rayons, boutiques, produits.
 *
 * Les prix sont ceux du marché dakarois à la mi-2026, relevés en boutique.
 * Ils comptent : une démonstration où un fer à béton vaut 300 francs ne
 * convainc personne dans ce pays.
 */
class CatalogueSeeder extends Seeder
{
    /** Rayon => [sous-rayons], avec l'icône du rayon. */
    private const RAYONS = [
        'Fer à béton' => ['icone' => 'beton', 'sous' => ['Fer haute adhérence', 'Ronds lisses']],
        'Tôles' => ['icone' => 'tole', 'sous' => ['Tôles bac', 'Tôles ondulées', 'Tôles planes']],
        'Tubes et profilés' => ['icone' => 'tube', 'sous' => ['Cornières', 'Tubes', 'Profilés']],
        'Treillis et fils' => ['icone' => 'treillis', 'sous' => ['Treillis soudés', 'Fils et grillages']],
        'Quincaillerie' => ['icone' => 'quincaillerie', 'sous' => ['Visserie', 'Serrurerie']],
        'Outillage et soudure' => ['icone' => 'outillage', 'sous' => ['Outils à main', 'Soudure']],
        'Pièces détachées' => ['icone' => 'piece', 'sous' => ['Roulements', 'Transmission']],
    ];

    /** nom, ville, officielle, remise appliquée sur le prix de référence. */
    private const BOUTIQUES = [
        ['Quincaillerie Ndiaye & Frères', 'Pikine', true, 1.00],
        ['Établissements Sow Métaux', 'Guédiawaye', true, 0.94],
        ['Comptoir du Fer Dakarois', 'Dakar', false, 1.06],
        ['Fer Express Thiaroye', 'Thiaroye', false, 0.98],
    ];

    /**
     * sous-rayon => [nom, marque, prix de référence, dessin, prix barré ?]
     *
     * Le prix barré n'est posé que là où une remise se justifie : l'afficher
     * partout le viderait de son sens.
     */
    private const PRODUITS = [
        'Fer haute adhérence' => [
            ['Fer à béton HA T6 — barre de 12 m', 'SENIRON', 2_100, 'rond-strie', 2_600],
            ['Fer à béton HA T8 — barre de 12 m', 'SENIRON', 3_600, 'rond-strie', null],
            ['Fer à béton HA T10 — barre de 12 m', 'SENIRON', 5_600, 'rond-strie', 6_900],
            ['Fer à béton HA T12 — barre de 12 m', 'SENIRON', 8_100, 'rond-strie', null],
            ['Fer à béton HA T14 — barre de 12 m', 'AFRIMETAL', 11_000, 'rond-strie', null],
            ['Fer à béton HA T16 — barre de 12 m', 'AFRIMETAL', 14_400, 'rond-strie', 17_500],
            ['Fer à béton HA T20 — barre de 12 m', 'AFRIMETAL', 22_500, 'rond-strie', null],
            ['Fer à béton HA T25 — barre de 12 m', 'AFRIMETAL', 35_200, 'rond-strie', null],
        ],
        'Ronds lisses' => [
            ['Rond lisse Ø6 — barre de 12 m', 'SENIRON', 1_900, 'rond-lisse', null],
            ['Rond lisse Ø8 — barre de 12 m', 'SENIRON', 3_300, 'rond-lisse', 3_900],
            ['Rond lisse Ø10 — barre de 12 m', 'SENIRON', 5_100, 'rond-lisse', null],
        ],
        'Tôles bac' => [
            ['Tôle bac alu-zinc 6 m — 30/100', 'ALUZINC', 14_500, 'tole-bac', 18_000],
            ['Tôle bac alu-zinc 4 m — 30/100', 'ALUZINC', 9_800, 'tole-bac', null],
            ['Tôle bac alu-zinc 6 m — 35/100', 'ALUZINC', 17_200, 'tole-bac', null],
        ],
        'Tôles ondulées' => [
            ['Tôle ondulée galvanisée 3 m', 'GALVA', 6_400, 'tole-ondulee', 7_800],
            ['Tôle ondulée galvanisée 4 m', 'GALVA', 8_500, 'tole-ondulee', null],
        ],
        'Tôles planes' => [
            ['Tôle plane noire 2 × 1 m — 2 mm', 'AFRIMETAL', 19_500, 'tole-plane', null],
            ['Tôle larmée 2 × 1 m — 3 mm', 'AFRIMETAL', 32_000, 'tole-larmee', 38_000],
        ],
        'Cornières' => [
            ['Cornière 30 × 30 × 3 — 6 m', 'AFRIMETAL', 6_800, 'corniere', null],
            ['Cornière 40 × 40 × 4 — 6 m', 'AFRIMETAL', 9_200, 'corniere', 11_000],
            ['Cornière 50 × 50 × 5 — 6 m', 'AFRIMETAL', 14_300, 'corniere', null],
        ],
        'Tubes' => [
            ['Tube carré 40 × 40 × 2 — 6 m', 'SENIRON', 9_100, 'tube-carre', null],
            ['Tube carré 50 × 50 × 2 — 6 m', 'SENIRON', 11_600, 'tube-carre', 13_900],
            ['Tube rectangulaire 60 × 40 × 2 — 6 m', 'SENIRON', 12_400, 'tube-rect', null],
            ['Tube rond Ø42 × 2 — 6 m', 'SENIRON', 8_900, 'tube-rond', null],
        ],
        'Profilés' => [
            ['Fer plat 40 × 5 — 6 m', 'AFRIMETAL', 7_300, 'fer-plat', null],
            ['UPN 80 — 6 m', 'AFRIMETAL', 28_500, 'upn', null],
            ['IPN 100 — 6 m', 'AFRIMETAL', 34_000, 'ipn', 41_000],
        ],
        'Treillis soudés' => [
            ['Treillis soudé ST25 — 2,4 × 6 m', 'GALVA', 18_900, 'treillis', 23_000],
            ['Treillis soudé ST15 — 2,4 × 6 m', 'GALVA', 13_600, 'treillis', null],
        ],
        'Fils et grillages' => [
            ['Fil recuit — rouleau de 25 kg', 'GALVA', 16_500, 'fil', null],
            ['Grillage galvanisé 1,5 m — rouleau 25 m', 'GALVA', 24_000, 'grillage', 29_000],
        ],
        'Visserie' => [
            ['Pointes 70 mm — boîte de 5 kg', 'SENFIX', 4_200, 'clou', null],
            ['Vis à bois 5 × 60 — boîte de 200', 'SENFIX', 3_800, 'vis', 4_600],
            ['Boulons M10 × 80 — boîte de 100', 'SENFIX', 9_500, 'boulon', null],
            ['Chevilles à frapper 8 × 60 — boîte de 100', 'SENFIX', 5_400, 'cheville', null],
        ],
        'Serrurerie' => [
            ['Cadenas laiton 50 mm', 'SENFIX', 3_500, 'cadenas', 4_500],
            ['Charnière acier 100 mm — la paire', 'SENFIX', 2_800, 'charniere', null],
        ],
        'Outils à main' => [
            ['Brouette de chantier 100 L', 'BATIPRO', 27_500, 'brouette', 34_000],
            ['Pelle ronde manche bois', 'BATIPRO', 6_200, 'pelle', null],
            ['Truelle langue de chat 200 mm', 'BATIPRO', 3_400, 'truelle', null],
            ['Marteau de coffreur 600 g', 'BATIPRO', 7_800, 'marteau', null],
        ],
        'Soudure' => [
            ['Électrodes rutiles 2,5 mm — paquet de 5 kg', 'SOUDAF', 8_900, 'electrode', 10_500],
            ['Disque à tronçonner 230 mm — lot de 10', 'SOUDAF', 11_200, 'disque', null],
        ],
        'Roulements' => [
            ['Roulement à billes 6204 2RS', 'MECAPRO', 4_600, 'roulement', null],
            ['Roulement à billes 6206 2RS', 'MECAPRO', 7_300, 'roulement', 8_900],
        ],
        'Transmission' => [
            ['Courroie trapézoïdale A-40', 'MECAPRO', 5_200, 'courroie', null],
            ['Chaîne à rouleaux 08B — 1 m', 'MECAPRO', 9_800, 'chaine', null],
            ['Poulie fonte Ø120 — 1 gorge', 'MECAPRO', 12_400, 'poulie', null],
        ],
    ];

    public function run(): void
    {
        if (Categorie::count() > 0) {
            $this->command?->warn('Catalogue déjà semé.');

            return;
        }

        // ── Les rayons ───────────────────────────────────────────────────────
        $sousRayons = [];
        foreach (array_values(array_keys(self::RAYONS)) as $rang => $nom) {
            $rayon = Categorie::create([
                'nom' => $nom, 'slug' => Str::slug($nom),
                'icone' => self::RAYONS[$nom]['icone'], 'rang' => $rang,
            ]);

            foreach (self::RAYONS[$nom]['sous'] as $r => $sous) {
                $sousRayons[$sous] = Categorie::create([
                    'parente_id' => $rayon->id, 'nom' => $sous,
                    'slug' => Str::slug($sous), 'rang' => $r,
                ]);
            }
        }

        // ── Les boutiques ────────────────────────────────────────────────────
        $boutiques = [];
        foreach (self::BOUTIQUES as $i => [$nom, $ville, $officielle, $ecart]) {
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
                    'nom' => $nom, 'slug' => Str::slug($nom),
                    'description' => 'Fer, tôles et quincaillerie. Livraison sur tout le Sénégal.',
                    'telephone' => $utilisateur->telephone,
                    'adresse' => 'Marché central, ' . $ville,
                    'ville' => $ville,
                    'officielle' => $officielle,
                    // La dernière n'est pas encore validée : la démonstration a
                    // besoin d'un dossier en attente à montrer.
                    'statut' => $i === 3 ? 'en_attente' : 'active',
                ]),
                'ecart' => $ecart,
            ];
        }

        // ── Les produits ─────────────────────────────────────────────────────
        $poses = 0;
        foreach (self::PRODUITS as $sousRayon => $articles) {
            foreach ($articles as $rang => [$nom, $marque, $prix, $dessin, $barre]) {
                // Chaque boutique ne tient pas tout : c'est ce qui donne son
                // intérêt à comparer.
                foreach ($boutiques as $b => $boutique) {
                    if (($rang + $b) % 3 === 2) {
                        continue;
                    }

                    $ecart = $boutique['ecart'];
                    $arrondi = fn ($v) => max(100, (int) round($v / 100) * 100);

                    Produit::create([
                        'boutique_id' => $boutique['modele']->id,
                        'categorie_id' => $sousRayons[$sousRayon]->id,
                        'nom' => $nom,
                        'slug' => Str::slug($nom) . '-' . $boutique['modele']->id,
                        'description' => $nom . ' — qualité contrôlée, disponible en magasin '
                            . 'et en livraison.',
                        'marque' => $marque,
                        'prix' => $arrondi($prix * $ecart),
                        'prix_barre' => $barre ? $arrondi($barre * $ecart) : null,
                        'stock' => 12 + ($rang * 7 + $b * 13) % 90,
                        'dessin' => $dessin,
                        'nombre_ventes' => ($rang * 11 + $b * 17) % 40,
                    ]);
                    $poses++;
                }
            }
        }

        $this->command?->info(sprintf(
            '%d rayons, %d sous-rayons, %d boutiques, %d produits.',
            count(self::RAYONS), count($sousRayons), count($boutiques), $poses
        ));
    }
}
