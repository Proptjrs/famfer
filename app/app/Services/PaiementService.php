<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * L'encaissement, et le rappel de l'opérateur.
 *
 * L'opérateur de paiement — Wave, Orange Money, un agrégateur — rappelle la
 * plateforme pour confirmer qu'un acheteur a payé. Ce rappel a deux défauts que
 * l'on ne peut pas corriger côté opérateur : il peut arriver DEUX FOIS, et il
 * peut ne JAMAIS arriver.
 *
 * Le premier défaut se traite par l'idempotence : un même rappel ne crédite
 * qu'une fois. Le second se traite par la réconciliation nocturne, qui compare
 * le relevé de l'opérateur à nos écritures.
 *
 * Sans ces deux dispositifs, le grand livre ment — et il ment silencieusement,
 * ce qui est pire.
 */
class PaiementService
{
    public function __construct(
        private GrandLivre $livre,
        private CommandeService $commandes,
    ) {}

    /**
     * Traite un rappel de l'opérateur.
     *
     * Renvoie true si le paiement a été enregistré à cette occasion, false s'il
     * l'était déjà — dans les deux cas l'opérateur doit recevoir un accusé, sans
     * quoi il rappellera indéfiniment.
     */
    public function traiterRappel(
        Commande $commande,
        string $operateur,
        string $cleIdempotence,
        int $montant,
        int $fraisOperateur = 0,
        array $chargeUtile = [],
    ): bool {
        try {
            return DB::transaction(function () use (
                $commande, $operateur, $cleIdempotence, $montant, $fraisOperateur, $chargeUtile
            ) {
                // C'est la contrainte d'unicité de la base qui protège, et non
                // un « if » qui la précéderait : entre la vérification et
                // l'écriture, un second rappel peut passer. Ici, le second se
                // heurte à la base elle-même.
                $paiement = Paiement::create([
                    'commande_id' => $commande->id,
                    'operateur' => $operateur,
                    'cle_idempotence' => $cleIdempotence,
                    'reference_externe' => $chargeUtile['reference'] ?? null,
                    'montant' => $montant,
                    'frais_operateur' => $fraisOperateur,
                    'etat' => 'confirme',
                    'charge_utile' => $chargeUtile,
                ]);

                // Le montant reçu doit correspondre à la commande. S'il diffère,
                // on enregistre le paiement mais on ne solde rien : un humain
                // doit regarder.
                if ($montant !== $commande->montant_total) {
                    $paiement->update(['etat' => 'echoue']);
                    Log::warning('Montant reçu différent du montant attendu', [
                        'commande' => $commande->reference,
                        'attendu' => $commande->montant_total,
                        'recu' => $montant,
                    ]);

                    return true;
                }

                $this->livre->encaisser($commande, $montant, $fraisOperateur, $operateur);
                $this->commandes->marquerPayee($commande);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            // Rappel déjà traité. On ne recrédite pas, et l'on répond calmement.
            return false;
        }
    }

    /**
     * Compare le relevé de l'opérateur à ce que nous avons enregistré.
     *
     * Trois cas, et seul le premier est banal :
     *
     *   présent des deux côtés     rien à faire ;
     *   chez l'opérateur seulement un rappel s'est perdu — on rattrape ;
     *   chez nous seulement        anomalie grave : nous avons crédité un
     *                              paiement dont l'opérateur n'a pas trace.
     *
     * @param  array<int, array{cle: string, montant: int}>  $releve
     * @return array{rattrapes: array<int, string>, anomalies: array<int, string>}
     */
    public function reconcilier(array $releve): array
    {
        $nos = Paiement::whereIn('cle_idempotence', array_column($releve, 'cle'))
            ->pluck('cle_idempotence')
            ->all();

        $rattrapes = [];
        foreach ($releve as $ligne) {
            if (! in_array($ligne['cle'], $nos, true)) {
                $rattrapes[] = $ligne['cle'];
            }
        }

        // L'inverse : ce que nous avons et que l'opérateur ignore.
        $clesReleve = array_column($releve, 'cle');
        $anomalies = Paiement::where('etat', 'confirme')
            ->whereNotIn('cle_idempotence', $clesReleve ?: [''])
            ->pluck('cle_idempotence')
            ->all();

        if ($anomalies !== []) {
            Log::error('Paiements sans contrepartie chez l\'opérateur', ['cles' => $anomalies]);
        }

        return ['rattrapes' => $rattrapes, 'anomalies' => $anomalies];
    }
}
