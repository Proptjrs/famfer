<?php

namespace App\Support;

/**
 * Le genre des unités de vente.
 *
 * « la kilogramme » se lisait dans toute l'application, parce que les vues
 * écrivaient « la {{ $unite }} » sans se poser la question. Sur une place de
 * marché où le prix se compare à l'unité, la faute revient à chaque ligne de
 * résultat.
 *
 * Onze unités seulement : une table suffit, et elle vaut mieux qu'une règle
 * devinée sur la terminaison — « tonne » et « carton » se ressemblent et ne
 * partagent pas le genre.
 */
class Unites
{
    private const FEMININES = ['barre', 'boite', 'boîte', 'feuille', 'tonne'];

    /** « la » ou « le », selon l'unité. */
    public static function determinant(string $unite): string
    {
        return in_array(mb_strtolower($unite), self::FEMININES, true) ? 'la' : 'le';
    }

    /** « la barre », « le kilogramme ». */
    public static function avecDeterminant(string $unite): string
    {
        return self::determinant($unite) . ' ' . $unite;
    }

    /** « 3 barres », « 1 barre » : l'accord en nombre. */
    public static function accorde(string $unite, int|float $quantite): string
    {
        return abs($quantite) > 1 ? $unite . 's' : $unite;
    }
}
