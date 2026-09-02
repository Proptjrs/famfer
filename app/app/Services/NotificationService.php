<?php

namespace App\Services;

use App\Models\Commande;
use App\Notifications\EtapeCommande;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prévenir les deux parties, à chaque étape qui les concerne.
 *
 * Une place de marché où le vendeur doit rafraîchir son tableau de bord pour
 * découvrir qu'une commande l'attend depuis deux heures perd ses commandes par
 * expiration. Le courriel n'est pas un ornement : il est ce qui fait tenir le
 * délai d'acceptation.
 *
 * Deux précautions gouvernent ce service, et ce sont les seules choses
 * intéressantes qu'il contient.
 *
 * La première : rien ne part avant que la transaction soit validée. Un courriel
 * envoyé depuis l'intérieur d'un « DB::transaction » qui se solde ensuite par un
 * retour arrière annonce un fait qui n'a pas eu lieu — et l'on ne rattrape pas
 * un courriel parti.
 *
 * La seconde : un serveur de messagerie en panne ne doit jamais faire échouer
 * une commande. L'envoi est donc encapsulé ; l'échec est journalisé, la vente
 * continue. C'est un arbitrage, et il est délibéré : mieux vaut un acheteur non
 * prévenu qu'un paiement perdu.
 */
class NotificationService
{
    /**
     * Prévient qui de droit du passage à un nouvel état.
     *
     * Les états absents de ce tableau ne déclenchent rien : une commande qui
     * expire faute de paiement n'a personne à prévenir — l'acheteur l'a
     * abandonnée, et le vendeur ne l'a jamais vue.
     */
    public function surTransition(Commande $commande, string $etat): void
    {
        $commande->loadMissing('acheteur.utilisateur', 'vendeur.utilisateur');

        $acheteur = $commande->acheteur?->utilisateur;
        $vendeur = $commande->vendeur?->utilisateur;
        $reference = $commande->reference;
        $montant = number_format($commande->montant_total, 0, ',', ' ');

        $suivi = route('acheteur.commandes');
        $commerce = route('vendeur.tableau');

        $envoi = match ($etat) {
            // Le vendeur : l'argent est au séquestre, la marchandise est à sortir.
            'payee' => [$vendeur, new EtapeCommande($commande,
                'Une commande payée vous attend',
                [
                    "La commande {$reference} vient d'être réglée : {$montant} F sont retenus par FamFer "
                    . 'pour votre compte.',
                    'Vous avez deux heures pour l\'accepter, faute de quoi elle sera annulée et '
                    . 'l\'acheteur remboursé.',
                ],
                'Voir la commande', $commerce)],

            'acceptee' => [$acheteur, new EtapeCommande($commande,
                'Votre commande est acceptée',
                [
                    "Le vendeur a accepté la commande {$reference} et prépare votre marchandise.",
                    'Votre argent reste retenu par FamFer jusqu\'à ce que vous confirmiez la réception.',
                ],
                'Suivre ma commande', $suivi)],

            'prete' => [$acheteur, new EtapeCommande($commande,
                'Votre marchandise est prête',
                ["La commande {$reference} est prête chez le vendeur."],
                'Suivre ma commande', $suivi)],

            'en_livraison' => [$acheteur, new EtapeCommande($commande,
                'Votre marchandise est partie',
                [
                    "La commande {$reference} a quitté le magasin.",
                    'Confirmez la réception dès qu\'elle vous parvient : c\'est ce qui déclenche '
                    . 'le paiement du vendeur.',
                ],
                'Confirmer la réception', $suivi)],

            // Le vendeur : à partir d'ici, l'argent lui est dû.
            'receptionnee' => [$vendeur, new EtapeCommande($commande,
                'Réception confirmée',
                [
                    "L'acheteur a confirmé la réception de la commande {$reference}.",
                    'La somme qui vous revient est désormais disponible au virement.',
                ],
                'Voir mon argent', route('vendeur.argent'))],

            'en_litige' => [$vendeur, new EtapeCommande($commande,
                'Un litige a été ouvert',
                [
                    "Un litige a été ouvert sur la commande {$reference}.",
                    'Tous vos virements sont gelés le temps de l\'arbitrage : traitez-le sans tarder.',
                ],
                'Voir le litige', $commerce)],

            'remboursee' => [$acheteur, new EtapeCommande($commande,
                'Vous avez été remboursé',
                ["La commande {$reference} vous a été remboursée : {$montant} F."],
                'Voir mes commandes', $suivi)],

            'annulee' => [$acheteur, new EtapeCommande($commande,
                'Votre commande est annulée',
                [
                    "La commande {$reference} a été annulée : "
                    . ($commande->motif_annulation ?: 'sans motif précisé') . '.',
                    'Si vous aviez déjà réglé, le remboursement est en cours.',
                ],
                'Voir mes commandes', $suivi)],

            default => null,
        };

        if ($envoi === null || $envoi[0] === null) {
            return;
        }

        $this->envoyerApresValidation($envoi[0], $envoi[1]);
    }

    /**
     * Diffère l'envoi jusqu'à la validation de la transaction en cours.
     *
     * « DB::afterCommit » n'agit que s'il y a une transaction ouverte ; hors
     * transaction, la fermeture s'exécute immédiatement. Les deux cas sont donc
     * couverts par le même appel.
     */
    private function envoyerApresValidation(object $destinataire, object $notification): void
    {
        DB::afterCommit(function () use ($destinataire, $notification) {
            try {
                $destinataire->notify($notification);
            } catch (Throwable $e) {
                // Une messagerie en panne ne fait pas échouer une vente.
                Log::warning('Notification non envoyée', [
                    'destinataire' => $destinataire->email ?? null,
                    'erreur' => $e->getMessage(),
                ]);
            }
        });
    }
}
