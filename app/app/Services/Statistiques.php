<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Commande;
use App\Models\LigneCommande;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Les séries des tableaux de bord.
 *
 * Les deux tableaux de bord affichaient des compteurs : « 412 000 F », « 18
 * commandes ». Un chiffre sans point de comparaison n'aide à décider de rien —
 * il ne dit ni si le mois est bon, ni si la tendance monte, ni ce qu'il faudrait
 * faire. Un tableau de bord qui ne sert pas à décider est une page d'accueil
 * déguisée.
 *
 * Ce service produit ce qui manquait : des séries dans le temps, des
 * répartitions, et des variations d'une période à l'autre.
 *
 * Deux précautions de méthode, et elles comptent plus que le code.
 *
 * Les séries sont **complétées** : un mois sans vente vaut zéro et doit
 * apparaître. Une courbe qui saute les mois creux dessine une progression
 * régulière là où il y a eu un trou, et fait prendre une décision sur une forme
 * qui n'existe pas.
 *
 * Les variations sont **muettes sous un seuil de volume**. Passer de une à deux
 * ventes est une hausse de cent pour cent qui ne veut rien dire ; l'annoncer
 * comme une tendance serait pire que se taire.
 */
class Statistiques
{
    /** Sous ce nombre d'observations, une variation relève du hasard. */
    public const VOLUME_MINIMAL = 5;

    // ── Les séries dans le temps ─────────────────────────────────────────────

    /**
     * Le chiffre d'affaires livré, mois par mois, pour une boutique.
     *
     * @return Collection<int, array{libelle: string, valeur: int}>
     */
    public function ventesMensuelles(?Boutique $boutique = null, int $mois = 6): Collection
    {
        $requete = LigneCommande::query()
            ->join('commandes', 'commandes.id', '=', 'lignes_commande.commande_id')
            ->where('commandes.etat', 'livree')
            ->where('commandes.livree_le', '>=', now()->startOfMonth()->subMonths($mois - 1));

        if ($boutique) {
            $requete->where('lignes_commande.boutique_id', $boutique->id);
        }

        $brut = $requete
            ->selectRaw("to_char(commandes.livree_le, 'YYYY-MM') as periode")
            ->selectRaw('sum(lignes_commande.montant) as total')
            ->groupBy('periode')->pluck('total', 'periode');

        return $this->completer($brut, $mois);
    }

    /** La commission acquise par la plateforme, mois par mois. */
    public function commissionsMensuelles(int $mois = 6): Collection
    {
        $brut = LigneCommande::query()
            ->join('commandes', 'commandes.id', '=', 'lignes_commande.commande_id')
            ->where('commandes.etat', 'livree')
            ->where('commandes.livree_le', '>=', now()->startOfMonth()->subMonths($mois - 1))
            ->selectRaw("to_char(commandes.livree_le, 'YYYY-MM') as periode")
            ->selectRaw('sum(lignes_commande.commission) as total')
            ->groupBy('periode')->pluck('total', 'periode');

        return $this->completer($brut, $mois);
    }

    /** Le nombre de commandes passées, mois par mois. */
    public function commandesMensuelles(?Boutique $boutique = null, int $mois = 6): Collection
    {
        $requete = Commande::query()
            ->where('created_at', '>=', now()->startOfMonth()->subMonths($mois - 1));

        if ($boutique) {
            $requete->whereHas('lignes', fn ($q) => $q->where('boutique_id', $boutique->id));
        }

        $brut = $requete
            ->selectRaw("to_char(created_at, 'YYYY-MM') as periode")
            ->selectRaw('count(*) as total')
            ->groupBy('periode')->pluck('total', 'periode');

        return $this->completer($brut, $mois);
    }

    // ── Les répartitions ─────────────────────────────────────────────────────

    /**
     * La répartition des commandes par état.
     *
     * Rendue en barre empilée plutôt qu'en camembert : au-delà de trois parts,
     * un camembert ne se lit plus, et il y a ici sept états.
     *
     * @return Collection<int, array{etat: string, libelle: string, nombre: int, part: float, ton: string}>
     */
    public function repartitionDesEtats(?Boutique $boutique = null): Collection
    {
        $requete = Commande::query();

        if ($boutique) {
            $requete->whereHas('lignes', fn ($q) => $q->where('boutique_id', $boutique->id));
        }

        $comptes = $requete->selectRaw('etat, count(*) as nombre')
            ->groupBy('etat')->pluck('nombre', 'etat');

        $total = max(1, (int) $comptes->sum());

        $libelles = [
            'en_preparation' => ['En préparation', 'neutre'],
            'expediee'       => ['Expédiée', 'info'],
            'en_livraison'   => ['En livraison', 'info'],
            'livree'         => ['Livrée', 'ok'],
            'litige'         => ['Litige', 'alerte'],
            'refusee'        => ['Refusée', 'grave'],
            'retournee'      => ['Retournée', 'grave'],
            'annulee'        => ['Annulée', 'neutre'],
        ];

        return collect($libelles)
            ->map(fn ($def, $etat) => [
                'etat' => $etat,
                'libelle' => $def[0],
                'ton' => $def[1],
                'nombre' => (int) ($comptes[$etat] ?? 0),
                'part' => round(((int) ($comptes[$etat] ?? 0)) * 100 / $total, 1),
            ])
            ->filter(fn ($l) => $l['nombre'] > 0)
            ->values();
    }

    // ── Les variations ───────────────────────────────────────────────────────

    /**
     * La variation d'un montant livré entre les 30 derniers jours et les 30
     * précédents, en pourcentage.
     *
     * Rend « null » — et l'indicateur n'affiche alors rien — lorsque le volume
     * est trop faible pour qu'une variation signifie quoi que ce soit. Mieux
     * vaut ne rien dire que d'annoncer « +100 % » sur deux ventes.
     */
    public function variationDesVentes(?Boutique $boutique = null): ?float
    {
        $recent = $this->montantLivreEntre(now()->subDays(30), now(), $boutique);
        $avant  = $this->montantLivreEntre(now()->subDays(60), now()->subDays(30), $boutique);

        $ventes = $this->nombreLivreEntre(now()->subDays(60), now(), $boutique);

        if ($ventes < self::VOLUME_MINIMAL || $avant <= 0) {
            return null;
        }

        return round(($recent - $avant) * 100 / $avant, 1);
    }

    private function montantLivreEntre(Carbon $de, Carbon $a, ?Boutique $boutique): int
    {
        $requete = LigneCommande::query()
            ->join('commandes', 'commandes.id', '=', 'lignes_commande.commande_id')
            ->where('commandes.etat', 'livree')
            ->whereBetween('commandes.livree_le', [$de, $a]);

        if ($boutique) {
            $requete->where('lignes_commande.boutique_id', $boutique->id);
        }

        return (int) $requete->sum('lignes_commande.montant');
    }

    private function nombreLivreEntre(Carbon $de, Carbon $a, ?Boutique $boutique): int
    {
        $requete = Commande::where('etat', 'livree')->whereBetween('livree_le', [$de, $a]);

        if ($boutique) {
            $requete->whereHas('lignes', fn ($q) => $q->where('boutique_id', $boutique->id));
        }

        return $requete->count();
    }

    // ── Outils ───────────────────────────────────────────────────────────────

    /**
     * Complète une série mensuelle par des zéros.
     *
     * C'est la précaution qui rend la courbe honnête : sans elle, un mois sans
     * vente disparaît, et la courbe relie directement les deux mois voisins en
     * dessinant une progression régulière là où il y a eu un trou.
     *
     * @param  Collection<string, mixed>  $brut  indexée par « YYYY-MM »
     * @return Collection<int, array{libelle: string, valeur: int, periode: string}>
     */
    private function completer(Collection $brut, int $mois): Collection
    {
        return collect(range($mois - 1, 0))->map(function (int $recul) use ($brut) {
            $date = now()->startOfMonth()->subMonths($recul);
            $cle = $date->format('Y-m');

            return [
                'periode' => $cle,
                // « janv. », « févr. » : trois lettres tiennent sous une barre,
                // « janvier » se chevauche avec son voisin.
                'libelle' => ucfirst($date->translatedFormat('M')),
                'valeur' => (int) ($brut[$cle] ?? 0),
            ];
        });
    }
}
