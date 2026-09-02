<?php

namespace App\Services;

/**
 * Distances entre l'acheteur et les quincailleries.
 *
 * La formule de Haversine donne la distance à vol d'oiseau. Elle suffit à
 * classer des vendeurs dans une même agglomération, elle est instantanée et
 * elle ne dépend d'aucun service extérieur — donc elle fonctionne même quand le
 * réseau est mauvais, ce qui arrive.
 *
 * Le temps de trajet réel, lui, demandera un appel à un service de routage :
 * il sera ajouté plus tard, avec un repli sur cette estimation.
 */
class GeolocService
{
    /** Rayon moyen de la Terre, en kilomètres. */
    private const RAYON_TERRE_KM = 6371.0;

    /** Distance à vol d'oiseau, en kilomètres, arrondie à dix mètres près. */
    public function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round(self::RAYON_TERRE_KM * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    /**
     * Estimation du trajet en voiture, en minutes.
     *
     * En ville, la route ne suit pas la ligne droite : on majore la distance
     * d'un quart, puis on compte 22 km/h — la vitesse moyenne observée dans
     * l'agglomération dakaroise aux heures ouvrables.
     */
    public function dureeVoitureMinutes(float $distanceKm): int
    {
        return max(1, (int) round($distanceKm * 1.25 / 22 * 60));
    }
}
