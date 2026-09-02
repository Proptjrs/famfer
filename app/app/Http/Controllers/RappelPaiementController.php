<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Services\PaiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * L'adresse que l'opérateur de paiement appelle pour confirmer un règlement.
 *
 * C'est la seule porte de la plateforme qui s'ouvre sans session : l'opérateur
 * n'est pas un utilisateur, il ne se connecte pas. Tout ce qui la protège tient
 * donc dans une signature.
 *
 * Ce que le code répond compte autant que ce qu'il fait. Un opérateur rappelle
 * tant qu'il n'a pas reçu un 2xx ; répondre 500 sur une commande introuvable le
 * ferait marteler l'adresse pendant des jours, et répondre 200 sur une
 * signature fausse reviendrait à accuser réception d'un paiement inventé. D'où
 * trois réponses distinctes :
 *
 *   401  signature absente, fausse ou périmée — rien n'est écrit ;
 *   404  commande inconnue — rien n'est écrit, et l'incident est journalisé ;
 *   200  rappel accepté, qu'il ait crédité maintenant ou qu'il l'ait déjà fait.
 *
 * Le dernier point est le cœur de l'idempotence : un rappel reçu deux fois
 * reçoit deux fois un accusé, mais ne crédite qu'une.
 */
class RappelPaiementController extends Controller
{
    public function __construct(private PaiementService $paiements) {}

    public function __invoke(Request $requete, string $operateur): JsonResponse
    {
        $corps = $requete->getContent();

        if (! $this->signatureValide($requete, $corps)) {
            Log::warning('Rappel de paiement refusé : signature invalide', [
                'operateur' => $operateur,
                'ip' => $requete->ip(),
            ]);

            return response()->json(['erreur' => 'signature invalide'], 401);
        }

        $donnees = $requete->validate([
            'reference' => 'required|string|max:40',
            'cle_idempotence' => 'required|string|max:120',
            'montant' => 'required|integer|min:1',
            'frais_operateur' => 'sometimes|integer|min:0',
        ]);

        // La référence de commande, et non son identifiant : c'est elle que
        // l'acheteur voit et que l'opérateur transporte.
        $commande = Commande::where('reference', $donnees['reference'])->first();

        if ($commande === null) {
            Log::error('Rappel de paiement pour une commande inconnue', [
                'operateur' => $operateur,
                'reference' => $donnees['reference'],
                'montant' => $donnees['montant'],
            ]);

            return response()->json(['erreur' => 'commande inconnue'], 404);
        }

        $enregistre = $this->paiements->traiterRappel(
            $commande,
            $operateur,
            $donnees['cle_idempotence'],
            $donnees['montant'],
            $donnees['frais_operateur'] ?? 0,
            ['reference' => $donnees['reference']],
        );

        return response()->json([
            'recu' => true,
            'deja_traite' => ! $enregistre,
        ]);
    }

    /**
     * Vérifie la signature du rappel.
     *
     * La signature porte sur l'horodatage ET sur le corps, joints par un point.
     * Signer le corps seul laisserait rejouer indéfiniment un enregistrement
     * capturé ; signer l'horodatage seul ne protégerait rien du tout.
     *
     * La comparaison passe par « hash_equals » et non par « === » : une
     * comparaison ordinaire s'arrête au premier caractère qui diffère, et le
     * temps qu'elle met à répondre laisse deviner la signature attendue, octet
     * par octet.
     */
    private function signatureValide(Request $requete, string $corps): bool
    {
        $secret = config('paiement.secret_rappel');

        // Pas de secret configuré, pas de rappel accepté. Dégrader en « on ne
        // vérifie pas » ouvrirait le séquestre au premier venu.
        if (empty($secret)) {
            Log::error('PAIEMENT_SECRET_RAPPEL n\'est pas configuré : tout rappel est refusé.');

            return false;
        }

        $signature = $requete->header('X-Famfer-Signature');
        $horodatage = $requete->header('X-Famfer-Horodatage');

        if (empty($signature) || ! is_numeric($horodatage)) {
            return false;
        }

        $age = abs(now()->timestamp - (int) $horodatage);
        if ($age > config('paiement.tolerance_horodatage')) {
            return false;
        }

        $attendue = hash_hmac('sha256', $horodatage . '.' . $corps, $secret);

        return hash_equals($attendue, $signature);
    }
}
