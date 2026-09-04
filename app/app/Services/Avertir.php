<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Commande;
use App\Notifications\DecisionBoutique;
use App\Notifications\EtapeCommande;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prévenir les deux parties, à chaque étape qui les concerne.
 *
 * Rien ne partait : ni confirmation au client, ni alerte au vendeur. Un
 * commerçant qui ne consultait pas son tableau de bord ne savait pas qu'on lui
 * avait acheté quelque chose, et un colis pouvait dormir une semaine dans son
 * magasin. Sur une place de marché, le courriel n'est pas un ornement : c'est ce
 * qui déclenche le travail.
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
 * une commande. L'envoi est encapsulé ; l'échec est journalisé, la vente
 * continue. C'est un arbitrage délibéré : mieux vaut un client non prévenu
 * qu'une commande perdue.
 */
class Avertir
{
    public function __construct(private Sms $sms) {}

    /**
     * Prévient qui de droit du passage à un nouvel état.
     *
     * Les états absents de ce tableau ne déclenchent rien.
     */
    public function surEtat(Commande $commande, string $etat): void
    {
        $commande->loadMissing('utilisateur', 'lignes.boutique.utilisateur');

        $client = $commande->utilisateur;
        $reference = $commande->reference;
        $total = number_format($commande->total, 0, ',', ' ');
        $suivi = route('mes-commandes.detail', $commande);

        $envois = match ($etat) {
            'expediee' => [[$client, new EtapeCommande($commande,
                'Votre commande est partie',
                array_filter([
                    "La commande {$reference} a quitté le magasin.",
                    $commande->paiement === 'livraison'
                        ? "Préparez {$total} F : vous réglerez au livreur."
                        : 'Vous serez contacté pour le règlement.',
                    // Le code de remise voyage avec l'annonce du départ : le
                    // client doit l'avoir sous les yeux quand on sonne, et tous
                    // n'auront pas ouvert leur suivi de commande à ce
                    // moment-là.
                    $commande->code_livraison
                        ? "Votre code de remise : {$commande->code_livraison}. Ne le "
                          . "donnez au livreur qu'en recevant le colis et en payant : "
                          . "c'est lui qui prouve que la livraison a eu lieu."
                        : null,
                ]),
                'Suivre ma commande', $suivi)]],

            'livree' => [[$client, new EtapeCommande($commande,
                'Votre commande est livrée',
                [
                    "La commande {$reference} vous a été remise.",
                    'Vous pouvez maintenant noter les articles reçus : c\'est ce qui '
                    . 'aide les prochains acheteurs à choisir.',
                ],
                'Donner mon avis', $suivi)]],

            'refusee' => [[$client, new EtapeCommande($commande,
                'Commande refusée à la livraison',
                [
                    "La commande {$reference} a été refusée à la livraison.",
                    'Motif : ' . ($commande->motif ?: 'non précisé') . '.',
                    'Si c\'est une erreur, recommandez : la marchandise est revenue '
                    . 'en stock chez le vendeur.',
                ],
                'Voir mes commandes', route('mes-commandes'))]],

            'annulee' => [[$client, new EtapeCommande($commande,
                'Votre commande est annulée',
                [
                    "La commande {$reference} a été annulée.",
                    'Motif : ' . ($commande->motif ?: 'non précisé') . '.',
                    'Rien ne vous a été prélevé : le règlement se fait à la livraison.',
                ],
                'Voir mes commandes', route('mes-commandes'))]],

            'retournee' => [[$client, new EtapeCommande($commande,
                'Retour enregistré',
                [
                    "Le retour de la commande {$reference} est enregistré.",
                    'Motif : ' . ($commande->motif ?: 'non précisé') . '.',
                ],
                'Voir mes commandes', route('mes-commandes'))]],

            'litige' => [[$client, new EtapeCommande($commande,
                'Litige ouvert sur votre commande',
                [
                    "Un litige a été ouvert sur la commande {$reference} par "
                    . ($commande->litige_par === 'client' ? 'vous' : 'le vendeur') . '.',
                    'Motif : ' . ($commande->litige_motif ?: 'non précisé') . '.',
                    "L'administration de FamFer examine les deux versions et "
                    . "tranchera. Aucune commission n'est due tant que le litige dure.",
                ],
                'Voir ma commande', $suivi)]],

            default => [],
        };

        // Le code de remise part aussi par téléphone. Le courriel suppose un
        // écran et une connexion ; le SMS atteint le client qui n'a ni l'un ni
        // l'autre au moment où le livreur sonne — et une preuve que l'acheteur
        // ne peut pas produire ne prouve rien.
        if ($etat === 'expediee' && $commande->code_livraison) {
            $this->sms->envoyer($commande->telephone, sprintf(
                'FamFer : votre commande %s arrive. Code de remise %s. '
                . 'A donner au livreur en payant %s F, pas avant.',
                $reference, $commande->code_livraison, $total
            ));
        }

        foreach ($envois as [$destinataire, $notification]) {
            $this->plusTard($destinataire, $notification);
        }
    }

    /**
     * Une commande vient d'être passée.
     *
     * Deux courriels d'un coup, et ils ne disent pas la même chose : le client
     * reçoit une confirmation, chaque vendeur concerné reçoit un ordre de
     * travail. Un panier réparti sur trois boutiques prévient les trois.
     */
    public function surNouvelleCommande(Commande $commande): void
    {
        $commande->loadMissing('utilisateur', 'lignes.boutique.utilisateur');

        $reference = $commande->reference;
        $total = number_format($commande->total, 0, ',', ' ');

        $this->plusTard($commande->utilisateur, new EtapeCommande(
            $commande,
            'Votre commande est enregistrée',
            [
                "Commande {$reference} — {$total} F, livraison comprise.",
                $commande->paiement === 'livraison'
                    ? "Vous réglerez {$total} F au livreur, en espèces, à la réception."
                    : 'Vous serez contacté pour le règlement.',
                'Livraison à : ' . $commande->adresse_livraison,
            ],
            'Suivre ma commande', route('mes-commandes.detail', $commande)
        ));

        // Chaque boutique n'est prévenue qu'une fois, même si la commande porte
        // plusieurs de ses articles.
        foreach ($commande->lignes->groupBy('boutique_id') as $lignes) {
            $boutique = $lignes->first()->boutique;
            $vendeur = $boutique?->utilisateur;

            if (! $vendeur) {
                continue;
            }

            $montant = number_format($lignes->sum('montant'), 0, ',', ' ');

            $this->plusTard($vendeur, new EtapeCommande(
                $commande,
                'Nouvelle commande à préparer',
                [
                    "La commande {$reference} porte {$lignes->count()} de vos articles, "
                    . "pour {$montant} F.",
                    'Livraison à : ' . $commande->adresse_livraison,
                    'Préparez-la, puis marquez-la expédiée depuis votre espace.',
                ],
                'Voir la commande', route('vendeur.commandes')
            ));
        }
    }

    /** L'administration a statué sur une boutique. */
    public function surDecisionBoutique(Boutique $boutique, bool $acceptee): void
    {
        $vendeur = $boutique->utilisateur;

        if ($vendeur) {
            $this->plusTard($vendeur, new DecisionBoutique($boutique, $acceptee));
        }
    }

    /**
     * Diffère l'envoi jusqu'à la validation de la transaction en cours.
     *
     * « DB::afterCommit » n'agit que s'il y a une transaction ouverte ; hors
     * transaction, la fermeture s'exécute immédiatement. Les deux cas sont donc
     * couverts par le même appel.
     */
    private function plusTard(object $destinataire, object $notification): void
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
