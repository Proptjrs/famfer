<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Famille;
use App\Models\UniteVente;
use Illuminate\Database\Seeder;

/**
 * Le catalogue des articles réellement demandés sur un chantier sénégalais.
 *
 * Les masses ne sont pas inventées : pour un rond à béton, la formule des
 * ferrailleurs donne d² / 162,2 kg par mètre — 0,617 kg/m pour un T10. Pour les
 * profilés et les tôles, ce sont les masses des barèmes commerciaux courants.
 *
 * Deux pivots seulement. Le gramme pour tout ce qui se pèse — l'acier se vend
 * au poids dès qu'on dépasse la barre. L'unité pour ce qui se compte : un
 * disque, une brouette, un sac. Mélanger les deux dans une même colonne
 * obligerait à retenir, à chaque calcul, de quoi on parle.
 */
class CatalogueSeeder extends Seeder
{
    private const BARRE_MM = 12000;

    public function run(): void
    {
        if (Famille::exists()) {
            $this->command?->info('Catalogue déjà en place : semis ignoré.');

            return;
        }

        $f = [];
        foreach ([
            ['Fer à béton', 'FER', 1],
            ['Tôles', 'TOLE', 2],
            ['Tubes et profilés', 'TUBE', 3],
            ['Treillis et fils', 'TREILLIS', 4],
            ['Quincaillerie', 'QUINC', 5],
            ['Outillage et soudure', 'OUTIL', 6],
            ['Pièces détachées', 'PIECE', 7],
        ] as [$nom, $code, $rang]) {
            $f[$code] = Famille::create(['nom' => $nom, 'code' => $code, 'rang' => $rang]);
        }

        $this->ferABeton($f['FER']);
        $this->toles($f['TOLE']);
        $this->profiles($f['TUBE']);
        $this->treillisEtFils($f['TREILLIS']);
        $this->quincaillerie($f['QUINC']);
        $this->outillage($f['OUTIL']);
        $this->piecesDetachees($f['PIECE']);

        $this->command?->info(sprintf(
            '  %d familles · %d articles · %d unités de vente',
            Famille::count(), Article::count(), UniteVente::count()
        ));
    }

    // ── Fer à béton ──────────────────────────────────────────────────────────

    private function ferABeton(Famille $famille): void
    {
        foreach ([6, 8, 10, 12, 14, 16, 20, 25, 32] as $d) {
            $gParMetre = intdiv($d * $d * 10_000 + 811, 1_622);
            $gParBarre = intdiv($gParMetre * self::BARRE_MM, 1000);

            $a = $this->article($famille, [
                'designation' => "Fer à béton haute adhérence T{$d} — barre de 12 m",
                'reference' => "T{$d}-12M",
                'synonymes' => "fer {$d}, fer a beton {$d}, HA{$d}, T{$d}, rond {$d}",
                'unite_pivot' => 'gramme',
                'caracteristiques' => [
                    'diametre_mm' => $d, 'longueur_mm' => self::BARRE_MM,
                    'masse_lineique_g_m' => $gParMetre, 'nuance' => 'FeE400',
                    'dessin' => 'rond-strie',
                ],
            ]);
            $this->unites($a, [['barre', $gParBarre, true], ['kilogramme', 1_000, false], ['tonne', 1_000_000, false]]);
        }

        // Le rond lisse, pour les cadres et les étriers.
        foreach ([6, 8, 10] as $d) {
            $gParMetre = intdiv($d * $d * 10_000 + 811, 1_622);
            $a = $this->article($famille, [
                'designation' => "Rond lisse Ø{$d} — barre de 12 m",
                'reference' => "RL{$d}-12M",
                'synonymes' => "rond lisse {$d}, fer lisse {$d}, RL{$d}",
                'unite_pivot' => 'gramme',
                'caracteristiques' => [
                    'diametre_mm' => $d, 'longueur_mm' => self::BARRE_MM,
                    'masse_lineique_g_m' => $gParMetre, 'dessin' => 'rond-lisse',
                ],
            ]);
            $this->unites($a, [['barre', intdiv($gParMetre * self::BARRE_MM, 1000), true], ['kilogramme', 1_000, false]]);
        }
    }

    // ── Tôles ────────────────────────────────────────────────────────────────

    private function toles(Famille $famille): void
    {
        $modeles = [
            ['Tôle bac alu-zinc 6 m — 30/100', 'TOLE-BAC-6M-30', 'tole bac, bac alu, tole 6m', 12_500, ['longueur_mm' => 6000, 'largeur_mm' => 1000, 'epaisseur_centieme' => 30], 'tole-bac'],
            ['Tôle bac alu-zinc 4 m — 30/100', 'TOLE-BAC-4M-30', 'tole bac 4m, bac alu 4', 8_300, ['longueur_mm' => 4000, 'largeur_mm' => 1000, 'epaisseur_centieme' => 30], 'tole-bac'],
            ['Tôle ondulée galvanisée 3 m', 'TOLE-OND-3M', 'tole ondulee, tole zinc, tole 3m', 6_200, ['longueur_mm' => 3000, 'largeur_mm' => 900], 'tole-ondulee'],
            ['Tôle plane noire 2 × 1 m — 2 mm', 'TOLE-PLANE-2MM', 'tole plane, tole noire, tole lisse', 31_400, ['longueur_mm' => 2000, 'largeur_mm' => 1000, 'epaisseur_mm' => 2], 'tole-plane'],
            ['Tôle larmée 2 × 1 m — 3 mm', 'TOLE-LARM-3MM', 'tole larmee, tole antiderapante', 49_000, ['longueur_mm' => 2000, 'largeur_mm' => 1000, 'epaisseur_mm' => 3], 'tole-larmee'],
        ];

        foreach ($modeles as [$nom, $ref, $syn, $masse, $carac, $dessin]) {
            $a = $this->article($famille, [
                'designation' => $nom, 'reference' => $ref, 'synonymes' => $syn,
                'unite_pivot' => 'gramme', 'caracteristiques' => $carac + ['dessin' => $dessin],
            ]);
            $this->unites($a, [['feuille', $masse, true], ['kilogramme', 1_000, false]]);
        }
    }

    // ── Tubes et profilés ────────────────────────────────────────────────────

    private function profiles(Famille $famille): void
    {
        $modeles = [
            ['Cornière 40 × 40 × 4 — 6 m', 'CORN-40-4-6M', 'corniere 40, L40', 14_400, 'corniere'],
            ['Cornière 50 × 50 × 5 — 6 m', 'CORN-50-5-6M', 'corniere 50, L50', 22_500, 'corniere'],
            ['Tube carré 40 × 40 × 2 — 6 m', 'TUBE-C40-6M', 'tube carre 40, carre 40', 14_300, 'tube-carre'],
            ['Tube carré 50 × 50 × 2 — 6 m', 'TUBE-C50-6M', 'tube carre 50, carre 50', 18_100, 'tube-carre'],
            ['Tube rectangulaire 60 × 40 × 2 — 6 m', 'TUBE-R6040-6M', 'tube rectangulaire, rectangle 60', 18_100, 'tube-rect'],
            ['Tube rond Ø 42 × 2 — 6 m', 'TUBE-R42-6M', 'tube rond 42, tuyau 42', 11_800, 'tube-rond'],
            ['Fer plat 40 × 5 — 6 m', 'PLAT-40-5-6M', 'fer plat 40, plat 40', 9_400, 'fer-plat'],
            ['UPN 100 — 6 m', 'UPN100-6M', 'upn 100, u 100', 63_600, 'upn'],
            ['IPN 120 — 6 m', 'IPN120-6M', 'ipn 120, poutrelle 120', 68_400, 'ipn'],
        ];

        foreach ($modeles as [$nom, $ref, $syn, $masse, $dessin]) {
            $a = $this->article($famille, [
                'designation' => $nom, 'reference' => $ref, 'synonymes' => $syn,
                'unite_pivot' => 'gramme',
                'caracteristiques' => ['longueur_mm' => 6000, 'dessin' => $dessin],
            ]);
            $this->unites($a, [['barre', $masse, true], ['kilogramme', 1_000, false]]);
        }
    }

    // ── Treillis et fils ─────────────────────────────────────────────────────

    private function treillisEtFils(Famille $famille): void
    {
        $a = $this->article($famille, [
            'designation' => 'Treillis soudé ST25 — panneau 6 × 2,4 m',
            'reference' => 'TS25-PANNEAU', 'synonymes' => 'treillis soude, ST25, panneau treillis',
            'unite_pivot' => 'gramme',
            'caracteristiques' => ['longueur_mm' => 6000, 'largeur_mm' => 2400, 'dessin' => 'treillis'],
        ]);
        $this->unites($a, [['panneau', 36_000, true], ['kilogramme', 1_000, false]]);

        $a = $this->article($famille, [
            'designation' => 'Fil d\'attache recuit — rouleau de 5 kg',
            'reference' => 'FIL-ATT-5KG', 'synonymes' => 'fil attache, fil recuit, fil de fer',
            'unite_pivot' => 'gramme', 'caracteristiques' => ['dessin' => 'fil'],
        ]);
        $this->unites($a, [['rouleau', 5_000_000 / 1000, true], ['kilogramme', 1_000, false]]);

        $a = $this->article($famille, [
            'designation' => 'Grillage galvanisé maille 50 — rouleau 25 m',
            'reference' => 'GRILL-50-25M', 'synonymes' => 'grillage, cloture, maille 50',
            'unite_pivot' => 'unite', 'caracteristiques' => ['dessin' => 'grillage'],
        ]);
        $this->unites($a, [['rouleau', 1, true]]);
    }

    // ── Quincaillerie ────────────────────────────────────────────────────────

    private function quincaillerie(Famille $famille): void
    {
        $modeles = [
            ['Pointes 70 mm — carton de 25 kg', 'POINTE-70-25KG', 'pointe 70, clou 70, clous', 'carton', 'clou'],
            ['Pointes 100 mm — carton de 25 kg', 'POINTE-100-25KG', 'pointe 100, clou 100', 'carton', 'clou'],
            ['Vis à tôle 4,8 × 35 — boîte de 500', 'VIS-TOLE-48-35', 'vis tole, vis toiture', 'boite', 'vis'],
            ['Boulon HM 12 × 60 — sachet de 50', 'BOULON-12-60', 'boulon 12, tire-fond', 'sachet', 'boulon'],
            ['Cheville métallique 10 mm — boîte de 100', 'CHEVILLE-10', 'cheville, cheville metal', 'boite', 'cheville'],
            ['Cadenas laiton 50 mm', 'CADENAS-50', 'cadenas, serrure', 'unite', 'cadenas'],
            ['Charnière acier 100 mm', 'CHARNIERE-100', 'charniere, paumelle', 'unite', 'charniere'],
        ];

        foreach ($modeles as [$nom, $ref, $syn, $unite, $dessin]) {
            $a = $this->article($famille, [
                'designation' => $nom, 'reference' => $ref, 'synonymes' => $syn,
                'unite_pivot' => 'unite', 'caracteristiques' => ['dessin' => $dessin],
            ]);
            $this->unites($a, [[$unite, 1, true]]);
        }
    }

    // ── Outillage et soudure ─────────────────────────────────────────────────

    private function outillage(Famille $famille): void
    {
        $modeles = [
            ['Électrodes de soudure 2,5 mm — paquet de 5 kg', 'ELEC-25-5KG', 'electrode, baguette soudure', 'paquet', 'electrode'],
            ['Disque à tronçonner 230 mm', 'DISQUE-230', 'disque tronconner, disque meuleuse', 'unite', 'disque'],
            ['Disque à ébarber 125 mm', 'DISQUE-125', 'disque ebarber, disque 125', 'unite', 'disque'],
            ['Brouette de chantier 100 L', 'BROUETTE-100', 'brouette, charrette', 'unite', 'brouette'],
            ['Pelle ronde manche bois', 'PELLE-RONDE', 'pelle, pelle ronde', 'unite', 'pelle'],
            ['Truelle langue de chat 200 mm', 'TRUELLE-200', 'truelle, taloche', 'unite', 'truelle'],
            ['Marteau de coffreur 600 g', 'MARTEAU-600', 'marteau, masse', 'unite', 'marteau'],
        ];

        foreach ($modeles as [$nom, $ref, $syn, $unite, $dessin]) {
            $a = $this->article($famille, [
                'designation' => $nom, 'reference' => $ref, 'synonymes' => $syn,
                'unite_pivot' => 'unite', 'caracteristiques' => ['dessin' => $dessin],
            ]);
            $this->unites($a, [[$unite, 1, true]]);
        }
    }

    // ── Pièces détachées ─────────────────────────────────────────────────────

    private function piecesDetachees(Famille $famille): void
    {
        $modeles = [
            ['Roulement à billes 6204', 'ROUL-6204', 'roulement 6204, roulement', 'roulement'],
            ['Roulement à billes 6205', 'ROUL-6205', 'roulement 6205', 'roulement'],
            ['Courroie trapézoïdale A-40', 'COURROIE-A40', 'courroie, courroie A40', 'courroie'],
            ['Chaîne à rouleaux pas 12,7 — 1 m', 'CHAINE-127', 'chaine, chaine rouleaux', 'chaine'],
            ['Poulie fonte Ø 100 — 1 gorge', 'POULIE-100', 'poulie, poulie fonte', 'poulie'],
            ['Roue de brouette pneumatique', 'ROUE-BROUETTE', 'roue brouette, pneu brouette', 'roue'],
        ];

        foreach ($modeles as [$nom, $ref, $syn, $dessin]) {
            $a = $this->article($famille, [
                'designation' => $nom, 'reference' => $ref, 'synonymes' => $syn,
                'unite_pivot' => 'unite', 'caracteristiques' => ['dessin' => $dessin],
            ]);
            $this->unites($a, [['unite', 1, true]]);
        }
    }

    // ── Fabriques ────────────────────────────────────────────────────────────

    private function article(Famille $famille, array $donnees): Article
    {
        return Article::create(['famille_id' => $famille->id] + $donnees);
    }

    /** @param array<int, array{0: string, 1: int, 2: bool}> $lignes */
    private function unites(Article $article, array $lignes): void
    {
        foreach ($lignes as [$unite, $facteur, $defaut]) {
            UniteVente::create([
                'article_id' => $article->id,
                'unite' => $unite,
                'facteur_vers_pivot' => (int) $facteur,
                'par_defaut' => $defaut,
            ]);
        }
    }
}
