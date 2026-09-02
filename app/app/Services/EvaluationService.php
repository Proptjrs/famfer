<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Evaluation;
use App\Models\Vendeur;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Les notes des vendeurs.
 *
 * Une note ne vaut que si elle est adossée à un achat. Partout où l'on peut
 * noter sans avoir acheté, les notes finissent par ne plus rien dire : les
 * concurrents se descendent, les amis se remontent. Ici, chaque évaluation est
 * rattachée à une commande reçue, et une commande ne se note qu'une fois.
 *
 * La note affichée est recalculée depuis les évaluations, jamais saisie. Un
 * champ que l'on peut écrire à la main est un champ qui finit par mentir.
 */
class EvaluationService
{
    /** Les états où la marchandise est effectivement arrivée chez l'acheteur. */
    private const ETATS_NOTABLES = ['receptionnee', 'soldee'];

    /**
     * @throws RuntimeException si la commande n'est pas notable, ou l'est déjà
     */
    public function noter(Commande $commande, int $note, ?string $commentaire = null): Evaluation
    {
        if ($note < 1 || $note > 5) {
            throw new RuntimeException('La note va de 1 à 5.');
        }

        if (! in_array($commande->etat, self::ETATS_NOTABLES, true)) {
            throw new RuntimeException(
                'On ne note qu\'une commande reçue : sans achat, une note ne vaut rien.'
            );
        }

        if (Evaluation::where('commande_id', $commande->id)->exists()) {
            throw new RuntimeException('Cette commande est déjà notée.');
        }

        return DB::transaction(function () use ($commande, $note, $commentaire) {
            $evaluation = Evaluation::create([
                'commande_id' => $commande->id,
                'vendeur_id' => $commande->vendeur_id,
                'note' => $note,
                'commentaire' => $commentaire,
            ]);

            $this->recalculer($commande->vendeur);

            return $evaluation;
        });
    }

    /**
     * Recalcule la moyenne d'un vendeur depuis ses évaluations.
     *
     * La note est conservée sur cent plutôt que sur cinq : elle se compare et
     * s'affiche plus finement, et elle reste un entier — donc exacte.
     */
    public function recalculer(Vendeur $vendeur): void
    {
        $evaluations = Evaluation::where('vendeur_id', $vendeur->id);
        $nombre = $evaluations->count();

        // Écriture directe, et non « update » : ces deux colonnes sont
        // volontairement hors du « fillable » du vendeur. Une note que l'on peut
        // affecter en masse est une note qu'un formulaire finira par écrire ;
        // seul ce service a le droit de la toucher.
        $vendeur->nombre_evaluations = $nombre;
        $vendeur->note_sur_cent = $nombre > 0
            ? (int) round($evaluations->avg('note') * 20)
            : null;
        $vendeur->save();
    }

    /** Les commandes reçues qu'il reste à noter, pour un acheteur. */
    public function aNoter(int $acheteurId)
    {
        return Commande::with('vendeur')
            ->where('acheteur_id', $acheteurId)
            ->whereIn('etat', self::ETATS_NOTABLES)
            ->whereDoesntHave('evaluation')
            ->orderByDesc('id')
            ->get();
    }
}
