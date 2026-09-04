<?php

namespace App\Services;

use App\Models\Commande;
use App\Notifications\EtapeCommande;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Le temps comme source de vérité.
 *
 * Le code de remise fait parler le client au moment de la livraison ; sa
 * confirmation le fait parler après. Restait le cas où personne ne parle — et
 * c'est le plus fréquent, bien avant la fraude : un commerçant débordé qui ne
 * clôture jamais ses commandes, un acheteur qui ne retourne pas sur le site une
 * fois son fer reçu.
 *
 * Le silence n'est pas neutre, et il ne dit pas la même chose selon qui se tait.
 *
 * Le silence du **vendeur** est suspect. Un colis expédié il y a une semaine et
 * jamais clos dort dans un magasin, ou bien il a été remis sans être déclaré —
 * et la seconde hypothèse est exactement celle qui l'arrange. On demande alors
 * au client, qui n'a lui aucune raison de mentir dans ce sens : confirmer sa
 * réception ne lui rapporte rien.
 *
 * Le silence du **client** vaut acceptation. Sans cela, un vendeur honnête
 * n'aurait jamais de certitude : un refus enregistré en janvier pourrait lui
 * être contesté en juin, quand plus personne ne se souvient de rien. La fenêtre
 * de contestation se ferme, et ce qui est clos le reste.
 */
class Veille
{
    /** Au-delà, un colis expédié et jamais clos mérite une question. */
    public const JOURS_AVANT_RELANCE = 5;

    /** Au-delà, ce qui est déclaré ne se conteste plus. */
    public const JOURS_DE_CONTESTATION = 10;

    public function __construct(private Sms $sms) {}

    /**
     * Le tour de veille, à passer une fois par jour.
     *
     * @return array<string, int> ce qui a été fait, pour le journal et l'essai
     */
    public function passer(): array
    {
        return [
            'relancees' => $this->relancerLesDormantes(),
            'closes' => $this->fermerLesFenetres(),
        ];
    }

    /**
     * Demande au client ce qu'il en est.
     *
     * Une seule fois par commande : au-delà, on harcèle quelqu'un qui a déjà
     * payé. La relance passe par les deux canaux, parce qu'ils n'atteignent pas
     * les mêmes gens — un client sans courriel reste joignable par téléphone.
     */
    public function relancerLesDormantes(): int
    {
        $limite = now()->subDays(self::JOURS_AVANT_RELANCE);

        $dormantes = Commande::with('utilisateur')
            ->whereIn('etat', ['expediee', 'en_livraison'])
            ->whereNull('relance_le')
            ->where('expediee_le', '<=', $limite)
            ->get();

        foreach ($dormantes as $commande) {
            $this->demander($commande);
            $commande->forceFill(['relance_le' => now()])->save();
        }

        return $dormantes->count();
    }

    /**
     * Ferme la fenêtre de contestation des commandes closes depuis longtemps.
     *
     * On ne touche pas aux litiges en cours : un dossier ouvert reste ouvert
     * tant que l'administration n'a pas tranché, quel que soit son âge.
     */
    public function fermerLesFenetres(): int
    {
        return Commande::whereIn('etat', ['livree', 'refusee', 'annulee', 'retournee'])
            ->whereNull('close_le')
            ->whereNotNull('cloturee_le')
            ->where('cloturee_le', '<=', now()->subDays(self::JOURS_DE_CONTESTATION))
            ->update(['close_le' => now()]);
    }

    /**
     * La question au client, sur les deux canaux.
     *
     * Ni l'un ni l'autre ne doit faire échouer le tour de veille : une
     * messagerie en panne ne justifie pas de laisser dormir les autres
     * commandes.
     */
    private function demander(Commande $commande): void
    {
        $reference = $commande->reference;
        $total = number_format($commande->total, 0, ',', ' ');

        DB::afterCommit(function () use ($commande, $reference, $total) {
            try {
                $commande->utilisateur?->notify(new EtapeCommande(
                    $commande,
                    'Avez-vous reçu votre commande ?',
                    [
                        "La commande {$reference} est partie il y a "
                        . self::JOURS_AVANT_RELANCE . ' jours et le vendeur ne l\'a pas '
                        . 'encore clôturée.',
                        'Si vous l\'avez reçue et réglée, confirmez-le depuis votre suivi : '
                        . 'cela clôt la vente même si le vendeur ne l\'a pas fait.',
                        'Si vous n\'avez rien reçu, dites-le nous depuis la même page.',
                    ],
                    'Répondre', route('mes-commandes.detail', $commande)
                ));
            } catch (Throwable $e) {
                Log::warning('Relance non envoyée', [
                    'commande' => $reference, 'erreur' => $e->getMessage(),
                ]);
            }

            $this->sms->envoyer($commande->telephone, sprintf(
                'FamFer : votre commande %s (%s F) est-elle bien arrivee ? '
                . 'Confirmez ou signalez sur %s',
                $reference, $total, route('mes-commandes.detail', $commande)
            ));
        });
    }
}
