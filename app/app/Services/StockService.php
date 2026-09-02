<?php

namespace App\Services;

use App\Exceptions\StockInsuffisant;
use App\Models\MouvementStock;
use App\Models\Offre;
use Illuminate\Support\Facades\DB;

/**
 * Le stock, tenu comme un journal.
 *
 * Deux principes gouvernent ce service.
 *
 * D'abord, aucune quantité n'est modifiée sans qu'une ligne de journal ne
 * l'explique. La colonne « quantite_pivot » de l'offre n'est qu'un cache : elle
 * doit toujours égaler la somme des mouvements, et la commande de vérification
 * le contrôle. On peut donc répondre à « où en étais-je le 12 mars ? », ce
 * qu'un simple compteur ne permet jamais.
 *
 * Ensuite, toute lecture qui précède une écriture se fait sous verrou. Deux
 * acheteurs qui commandent la dernière tonne au même instant liraient sinon le
 * même stock disponible, et le vendeur devrait deux tonnes qu'il n'a pas.
 */
class StockService
{
    /**
     * Réserve de la marchandise pour une commande en cours.
     *
     * @throws StockInsuffisant
     */
    public function reserver(Offre $offre, int $quantitePivot, ?int $auteurId = null, ?string $motif = null): MouvementStock
    {
        return DB::transaction(function () use ($offre, $quantitePivot, $auteurId, $motif) {
            // « lockForUpdate » verrouille la ligne jusqu'à la fin de la
            // transaction : la seconde commande attend, lit le stock à jour, et
            // se voit refuser. Sans ce verrou, les deux passeraient.
            $o = Offre::whereKey($offre->id)->lockForUpdate()->firstOrFail();

            $disponible = $o->quantite_pivot - $o->quantite_reservee_pivot;
            if ($disponible < $quantitePivot) {
                throw new StockInsuffisant($disponible, $quantitePivot);
            }

            $o->increment('quantite_reservee_pivot', $quantitePivot);

            return $this->journaliser($o, 'reservation', $quantitePivot, $motif, $auteurId);
        });
    }

    /** Rend au stock une réservation qui n'a pas abouti : paiement expiré, refus du vendeur. */
    public function liberer(Offre $offre, int $quantitePivot, ?int $auteurId = null, ?string $motif = null): MouvementStock
    {
        return DB::transaction(function () use ($offre, $quantitePivot, $auteurId, $motif) {
            $o = Offre::whereKey($offre->id)->lockForUpdate()->firstOrFail();

            // On ne libère jamais plus que ce qui est réservé : sinon la réserve
            // deviendrait négative et le disponible dépasserait le stock réel.
            $aLiberer = min($quantitePivot, $o->quantite_reservee_pivot);
            $o->decrement('quantite_reservee_pivot', $aLiberer);

            return $this->journaliser($o, 'liberation', $aLiberer, $motif, $auteurId);
        });
    }

    /** La marchandise quitte le magasin : la réservation devient une sortie définitive. */
    public function sortir(Offre $offre, int $quantitePivot, ?int $auteurId = null, ?string $motif = null): MouvementStock
    {
        return DB::transaction(function () use ($offre, $quantitePivot, $auteurId, $motif) {
            $o = Offre::whereKey($offre->id)->lockForUpdate()->firstOrFail();

            $o->decrement('quantite_reservee_pivot', min($quantitePivot, $o->quantite_reservee_pivot));
            $o->decrement('quantite_pivot', $quantitePivot);

            return $this->journaliser($o, 'sortie_vente', -$quantitePivot, $motif, $auteurId);
        });
    }

    /** Arrivage : le vendeur reçoit de la marchandise. */
    public function approvisionner(Offre $offre, int $quantitePivot, ?int $auteurId = null, ?string $motif = null): MouvementStock
    {
        return DB::transaction(function () use ($offre, $quantitePivot, $auteurId, $motif) {
            $o = Offre::whereKey($offre->id)->lockForUpdate()->firstOrFail();
            $o->increment('quantite_pivot', $quantitePivot);

            return $this->journaliser($o, 'approvisionnement', $quantitePivot, $motif, $auteurId);
        });
    }

    /** Retour de chantier : la marchandise revient en stock. */
    public function retourner(Offre $offre, int $quantitePivot, ?int $auteurId = null, ?string $motif = null): MouvementStock
    {
        return DB::transaction(function () use ($offre, $quantitePivot, $auteurId, $motif) {
            $o = Offre::whereKey($offre->id)->lockForUpdate()->firstOrFail();
            $o->increment('quantite_pivot', $quantitePivot);

            return $this->journaliser($o, 'retour', $quantitePivot, $motif, $auteurId);
        });
    }

    /**
     * Le stock recalculé depuis le journal, sans lire le cache.
     *
     * C'est la valeur qui fait foi. Si elle diffère de « quantite_pivot », le
     * cache est faux et il faut chercher pourquoi.
     */
    public function stockJournalise(Offre $offre): int
    {
        return (int) MouvementStock::where('offre_id', $offre->id)
            ->whereIn('type', ['approvisionnement', 'sortie_vente', 'retour', 'regularisation_inventaire'])
            ->sum('quantite_pivot');
    }

    private function journaliser(Offre $offre, string $type, int $quantite, ?string $motif, ?int $auteurId): MouvementStock
    {
        return MouvementStock::create([
            'offre_id' => $offre->id,
            'type' => $type,
            'quantite_pivot' => $quantite,
            'motif' => $motif,
            'auteur_id' => $auteurId,
        ]);
    }
}
