<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Offre;
use App\Models\User;
use App\Models\Vendeur;
use App\Services\ConversionUnites;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Quatre quincailleries de l'agglomération dakaroise.
 *
 * Chacune tient une partie du catalogue, à ses prix. C'est la démonstration que
 * la plateforme n'est pas un annuaire : parce que les offres pointent le même
 * article du référentiel, l'acheteur peut les comparer — au gramme, et non à
 * l'unité affichée, qui diffère d'une maison à l'autre.
 *
 * Les prix s'écartent d'un vendeur à l'autre dans des proportions réalistes :
 * quelques points de pourcentage, pas du simple au double.
 */
class VendeursSeeder extends Seeder
{
    /** raison sociale, commune, lat, lng, statut, écart de prix, part du catalogue */
    private const MAISONS = [
        ['Quincaillerie Ndiaye & Frères', 'Pikine',     14.7547, -17.3906, 'verifie',    1.00, 1.00],
        ['Établissements Sow Métaux',     'Guédiawaye', 14.7758, -17.4056, 'verifie',    1.06, 0.75],
        ['Comptoir du Fer Dakarois',      'Dakar',      14.6937, -17.4441, 'verifie',    0.94, 0.60],
        ['Fer Express Thiaroye',          'Thiaroye',   14.7614, -17.3592, 'en_attente', 0.92, 0.50],
    ];

    /** Prix de référence, en francs, pour l'unité par défaut de chaque article. */
    private const PRIX = [
        'T6-12M' => 1_700, 'T8-12M' => 2_900, 'T10-12M' => 4_200, 'T12-12M' => 5_900,
        'T14-12M' => 8_000, 'T16-12M' => 10_400, 'T20-12M' => 16_200, 'T25-12M' => 25_300,
        'T32-12M' => 41_400, 'RL6-12M' => 1_600, 'RL8-12M' => 2_700, 'RL10-12M' => 4_000,
        'TOLE-BAC-6M-30' => 9_500, 'TOLE-BAC-4M-30' => 6_400, 'TOLE-OND-3M' => 4_800,
        'TOLE-PLANE-2MM' => 22_000, 'TOLE-LARM-3MM' => 34_500,
        'CORN-40-4-6M' => 9_800, 'CORN-50-5-6M' => 15_200, 'TUBE-C40-6M' => 9_700,
        'TUBE-C50-6M' => 12_300, 'TUBE-R6040-6M' => 12_300, 'TUBE-R42-6M' => 8_100,
        'PLAT-40-5-6M' => 6_500, 'UPN100-6M' => 43_000, 'IPN120-6M' => 46_500,
        'TS25-PANNEAU' => 24_500, 'FIL-ATT-5KG' => 4_200, 'GRILL-50-25M' => 32_000,
        'POINTE-70-25KG' => 17_500, 'POINTE-100-25KG' => 17_500, 'VIS-TOLE-48-35' => 4_500,
        'BOULON-12-60' => 3_800, 'CHEVILLE-10' => 5_200, 'CADENAS-50' => 3_500,
        'CHARNIERE-100' => 1_800,
        'ELEC-25-5KG' => 8_900, 'DISQUE-230' => 2_200, 'DISQUE-125' => 1_100,
        'BROUETTE-100' => 28_000, 'PELLE-RONDE' => 6_500, 'TRUELLE-200' => 3_200,
        'MARTEAU-600' => 5_400,
        'ROUL-6204' => 3_400, 'ROUL-6205' => 4_100, 'COURROIE-A40' => 4_800,
        'CHAINE-127' => 5_600, 'POULIE-100' => 7_200, 'ROUE-BROUETTE' => 9_500,
    ];

    public function run(): void
    {
        if (Vendeur::exists()) {
            $this->command?->info('Vendeurs déjà en place : semis ignoré.');

            return;
        }

        // Un administrateur, sans quoi l'espace d'arbitrage est inatteignable :
        // depuis qu'il est fermé au rôle, plus personne ne pouvait y entrer.
        User::firstOrCreate(
            ['email' => 'admin@famfer.sn'],
            ['name' => 'Administration FamFer', 'password' => 'password', 'est_admin' => true]
        );

        $conversion = app(ConversionUnites::class);
        $stock = app(StockService::class);
        $articles = Article::with('unitesVente')->orderBy('id')->get();

        foreach (self::MAISONS as $i => [$nom, $commune, $lat, $lng, $statut, $ecart, $part]) {
            $utilisateur = User::create([
                'name' => $nom,
                'email' => 'vendeur' . ($i + 1) . '@famfer.sn',
                'password' => 'password',
            ]);

            $vendeur = Vendeur::create([
                'utilisateur_id' => $utilisateur->id,
                'raison_sociale' => $nom,
                'ninea' => '00' . str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT),
                'telephone' => '+221 77 000 00 0' . ($i + 1),
                'adresse' => 'Marché central, ' . $commune,
                'commune' => $commune,
                'latitude' => $lat,
                'longitude' => $lng,
                'statut' => $statut,
                // Sans compte de versement, aucun virement ne peut être
                // préparé : la démonstration s'arrêterait au premier reversement.
                'versement_operateur' => $i % 2 === 0 ? 'wave' : 'om',
                'versement_numero' => '77 000 00 0' . ($i + 1),
                'versement_titulaire' => $nom,
                'versement_modifie_le' => now(),
                'verifie_le' => $statut === 'verifie' ? now() : null,
                // Aucune note semée : elle se gagne. HistoriqueSeeder mène de
                // vraies commandes jusqu'à l'avis, et le service recalcule la
                // moyenne. Un champ écrit à la main finit toujours par mentir.
            ]);

            // Chaque maison tient une partie du catalogue, pas la totalité :
            // c'est ce qui donne son intérêt à la comparaison.
            $tenus = $articles->filter(fn ($a, $rang) => ($rang % 100) / 100 < $part);

            foreach ($tenus as $article) {
                $reference = self::PRIX[$article->reference] ?? null;
                if ($reference === null) {
                    continue;
                }

                // Chaque maison affiche dans SON unité de comptoir : l'une à la
                // barre, l'autre au kilo, la troisième à la tonne. C'est la
                // situation réelle, et c'est elle qui donne son sens à la
                // conversion — sans quoi comparer serait trivial.
                $unites = $article->unitesVente;
                $unite = $unites->count() > 1 ? $unites[$i % $unites->count()] : $unites->first();
                if (! $unite) {
                    continue;
                }

                // Le prix de référence vaut pour l'unité par défaut ; on le
                // ramène au pivot, puis on l'exprime dans l'unité de la maison.
                $defaut = $article->uniteParDefaut();
                $parPivot = $reference / $defaut->facteur_vers_pivot;
                $brut = $parPivot * $unite->facteur_vers_pivot * $ecart;

                // Arrondi à la centaine près pour les petits montants, au
                // millier pour les gros : personne n'affiche 546 187 F la tonne.
                $pas = $brut >= 100_000 ? 1_000 : 100;
                $prix = max($pas, (int) round($brut / $pas) * $pas);

                $offre = Offre::create([
                    'vendeur_id' => $vendeur->id,
                    'article_id' => $article->id,
                    'prix_par_unite' => $prix,
                    'unite_affichee' => $unite->unite,
                    'delai_preparation_h' => 2 + $i,
                ]);

                // Le stock entre par un mouvement : chaque gramme présent doit
                // pouvoir s'expliquer par une ligne du journal.
                $quantite = $article->unite_pivot === 'gramme' ? 40 + $i * 30 : 25 + $i * 15;
                $stock->approvisionner(
                    $offre,
                    $conversion->versPivot($article, $unite->unite, $quantite),
                    $utilisateur->id,
                    'Stock initial'
                );
            }
        }

        $this->command?->info(sprintf(
            '  %d vendeurs dont %d vérifiés · %d offres',
            Vendeur::count(), Vendeur::where('statut', 'verifie')->count(), Offre::count()
        ));
    }
}
