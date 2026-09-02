<?php

namespace App\Services;

use App\Models\Vendeur;
use RuntimeException;

/**
 * Le prix d'une livraison de fer.
 *
 * Le fer n'est pas un colis. Une tonne de T12 ne se transporte pas comme une
 * paire de chaussures : c'est le poids, autant que la distance, qui fait le
 * prix. Un tarif au kilomètre seul ferait payer le même montant pour dix
 * cornières et pour trois tonnes de rond à béton — et le vendeur perdrait de
 * l'argent à chaque grosse commande.
 *
 * D'où trois termes : une prise en charge fixe, un tarif kilométrique, et un
 * tarif à la tonne-kilomètre. C'est le calcul qu'un transporteur fait de tête.
 *
 * Les frais reviennent entièrement au vendeur, qui livre. La plateforme ne
 * prélève sa commission que sur la marchandise : prendre 8 % du carburant
 * d'autrui ne se défend pas.
 */
class LivraisonService
{
    /** La prise en charge : sortir le camion coûte, même pour un kilomètre. */
    public const BASE_F = 2_000;

    /** Le kilomètre parcouru, à vide comme en charge. */
    public const PAR_KM_F = 250;

    /** Le supplément par tonne transportée et par kilomètre. */
    public const PAR_TONNE_KM_F = 120;

    /** Au-delà, ce n'est plus une livraison de quartier : le vendeur devis. */
    public const RAYON_MAX_KM = 60;

    /** La charge utile d'un camion léger, en grammes. */
    public const CHARGE_MAX_G = 5_000_000;

    public function __construct(private GeolocService $geoloc) {}

    /**
     * Les frais pour un poids donné, à une distance donnée.
     *
     * Le résultat est un entier de francs, arrondi à la centaine supérieure :
     * personne au marché n'annonce « 4 387 F de livraison ».
     *
     * @param  int  $poidsPivot  le poids total en grammes
     *
     * @throws RuntimeException hors rayon, ou au-dessus de la charge utile
     */
    public function frais(float $distanceKm, int $poidsPivot): int
    {
        if ($poidsPivot <= 0) {
            throw new RuntimeException('Une livraison sans marchandise n\'a pas de prix.');
        }

        if ($distanceKm > self::RAYON_MAX_KM) {
            throw new RuntimeException(sprintf(
                'Au-delà de %d km, la livraison se négocie de gré à gré avec le vendeur (%s km ici).',
                self::RAYON_MAX_KM, number_format($distanceKm, 1, ',', ' ')
            ));
        }

        if ($poidsPivot > self::CHARGE_MAX_G) {
            throw new RuntimeException(sprintf(
                'Cette commande pèse %s kg : elle dépasse la charge d\'un camion (%s kg). '
                . 'Séparez-la en plusieurs livraisons ou choisissez le retrait.',
                number_format($poidsPivot / 1000, 0, ',', ' '),
                number_format(self::CHARGE_MAX_G / 1000, 0, ',', ' ')
            ));
        }

        // Tout se calcule en francs-millièmes puis se ramène à l'entier : le
        // poids et la distance sont fractionnaires, l'argent ne l'est pas.
        $millimes = self::BASE_F * 1000
            + (int) round($distanceKm * self::PAR_KM_F * 1000)
            + (int) round($distanceKm * ($poidsPivot / 1_000_000) * self::PAR_TONNE_KM_F * 1000);

        $francs = intdiv($millimes, 1000);

        // Arrondi à la centaine supérieure, jamais à l'inférieure : c'est le
        // vendeur qui avance le carburant, il ne doit pas y perdre.
        return (int) (ceil($francs / 100) * 100);
    }

    /** Les frais entre un vendeur et un point de livraison. */
    public function fraisVers(Vendeur $vendeur, float $lat, float $lng, int $poidsPivot): int
    {
        return $this->frais(
            $this->geoloc->distance($lat, $lng, $vendeur->latitude, $vendeur->longitude),
            $poidsPivot
        );
    }

    /**
     * Le détail, pour l'afficher à l'acheteur.
     *
     * Un montant de livraison qui tombe sans explication passe pour arbitraire.
     * Montrer les trois termes fait comprendre pourquoi trois tonnes coûtent
     * plus cher que trois cornières.
     */
    public function detail(float $distanceKm, int $poidsPivot): array
    {
        return [
            'base' => self::BASE_F,
            'distance_km' => round($distanceKm, 1),
            'part_distance' => (int) round($distanceKm * self::PAR_KM_F),
            'poids_kg' => (int) round($poidsPivot / 1000),
            'part_poids' => (int) round($distanceKm * ($poidsPivot / 1_000_000) * self::PAR_TONNE_KM_F),
            'total' => $this->frais($distanceKm, $poidsPivot),
        ];
    }
}
