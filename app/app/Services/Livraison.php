<?php

namespace App\Services;

/**
 * Les frais de livraison.
 *
 * Un forfait par région, et la gratuité au-dessus d'un montant. C'est le
 * barème de toutes les places de marché grand public : le client doit pouvoir
 * le deviner avant d'arriver au paiement, ce qu'un calcul au poids et à la
 * distance ne permet pas.
 *
 * La gratuité n'est pas une générosité mais un levier : elle pousse à remplir
 * le panier, et le seuil se règle en un seul endroit.
 */
class Livraison
{
    /** Au-dessus de ce montant, la livraison est offerte. */
    public const SEUIL_GRATUIT = 50_000;

    /** Le forfait, par région. Dakar est proche ; le reste coûte la route. */
    private const FORFAITS = [
        'Dakar' => 1_500,
        'Thiès' => 2_500,
        'Diourbel' => 3_000,
        'Saint-Louis' => 3_500,
        'Kaolack' => 3_000,
        'Ziguinchor' => 5_000,
        'Tambacounda' => 5_000,
        'Matam' => 5_000,
    ];

    public const FORFAIT_AUTRE = 4_000;

    /** @return array<int, string> les régions desservies, pour un menu. */
    public static function regions(): array
    {
        return array_keys(self::FORFAITS);
    }

    public function frais(string $region, int $sousTotal): int
    {
        if ($sousTotal >= self::SEUIL_GRATUIT) {
            return 0;
        }

        return self::FORFAITS[$region] ?? self::FORFAIT_AUTRE;
    }

    /** Ce qu'il manque pour la livraison offerte, ou null si elle l'est déjà. */
    public function resteAvantGratuite(int $sousTotal): ?int
    {
        return $sousTotal >= self::SEUIL_GRATUIT ? null : self::SEUIL_GRATUIT - $sousTotal;
    }
}
