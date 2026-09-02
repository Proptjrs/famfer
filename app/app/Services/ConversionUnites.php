<?php

namespace App\Services;

use App\Models\Article;
use App\Models\UniteVente;
use RuntimeException;

/**
 * Conversion entre les unités de vente et l'unité pivot d'un article.
 *
 * Le fer s'achète à la tonne, se stocke à la barre et se vend au kilo. Une même
 * marchandise change donc d'unité entre l'entrée et la sortie du stock. Tout est
 * ramené à une unité pivot — le gramme pour l'acier — et stocké en entier.
 *
 * Pourquoi des entiers : un flottant perd de la précision, et l'erreur se répète
 * à chaque mouvement. Sur des tonnes de fer, quelques millièmes accumulés
 * finissent par valoir une barre. Le grand livre du stock doit tomber juste au
 * gramme près, comme celui de l'argent tombe juste au franc près.
 */
class ConversionUnites
{
    /**
     * Convertit une quantité exprimée dans une unité de vente vers le pivot.
     *
     * @throws RuntimeException si l'unité n'est pas déclarée pour cet article,
     *                          ou si la quantité ne tombe pas juste.
     */
    public function versPivot(Article $article, string $unite, string|int $quantite): int
    {
        $facteur = $this->facteur($article, $unite);

        // La quantité peut être décimale côté client — « 2,5 tonnes » — mais le
        // résultat doit être un entier de grammes. On refuse ce qui ne tombe pas
        // juste plutôt que d'arrondir en silence : arrondir ici, c'est perdre de
        // la marchandise sans que personne ne s'en aperçoive.
        $exact = $this->multiplierExact((string) $quantite, $facteur);

        if ($exact === null) {
            throw new RuntimeException(sprintf(
                'La quantité %s %s ne tombe pas juste en %s (facteur %d).',
                $quantite, $unite, $article->unite_pivot, $facteur
            ));
        }

        return $exact;
    }

    /**
     * Convertit une quantité pivot vers une unité de vente.
     *
     * Le résultat est une chaîne décimale, et non un flottant : c'est un
     * affichage, il ne doit jamais servir de base à un nouveau calcul.
     */
    public function depuisPivot(Article $article, string $unite, int $quantitePivot, int $decimales = 3): string
    {
        $facteur = $this->facteur($article, $unite);
        $entier = intdiv($quantitePivot, $facteur);
        $reste = $quantitePivot % $facteur;

        if ($reste === 0 || $decimales === 0) {
            return (string) $entier;
        }

        $fraction = str_pad((string) intdiv($reste * 10 ** $decimales, $facteur), $decimales, '0', STR_PAD_LEFT);

        return rtrim(rtrim($entier . '.' . $fraction, '0'), '.');
    }

    /** Combien d'unités entières tiennent dans une quantité pivot. */
    public function nombreEntier(Article $article, string $unite, int $quantitePivot): int
    {
        return intdiv($quantitePivot, $this->facteur($article, $unite));
    }

    private function facteur(Article $article, string $unite): int
    {
        $ligne = UniteVente::where('article_id', $article->id)
            ->where('unite', $unite)
            ->first();

        if (! $ligne) {
            throw new RuntimeException(
                sprintf('L\'unité « %s » n\'est pas déclarée pour %s.', $unite, $article->reference)
            );
        }

        return (int) $ligne->facteur_vers_pivot;
    }

    /**
     * Multiplie une quantité décimale par un facteur entier, sans flottant.
     *
     * Renvoie null si le résultat n'est pas entier — c'est-à-dire si la quantité
     * demandée ne correspond à aucun nombre exact d'unités pivot.
     */
    private function multiplierExact(string $quantite, int $facteur): ?int
    {
        $quantite = str_replace(',', '.', trim($quantite));

        if (! preg_match('/^\d+(\.\d+)?$/', $quantite)) {
            throw new RuntimeException('Quantité invalide : ' . $quantite);
        }

        [$entier, $decimales] = array_pad(explode('.', $quantite, 2), 2, '');
        $n = strlen($decimales);

        // On travaille en centièmes, millièmes… selon le nombre de décimales
        // reçues, puis on divise à la fin : la division n'intervient qu'une fois.
        $numerateur = (int) ($entier . $decimales) * $facteur;
        $diviseur = 10 ** $n;

        return $numerateur % $diviseur === 0 ? intdiv($numerateur, $diviseur) : null;
    }
}
