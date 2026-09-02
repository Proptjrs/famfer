<?php

namespace App\Notifications;

use App\Models\Vendeur;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * La réponse de l'administration à une demande d'inscription vendeur.
 *
 * Une demande laissée sans réponse est ce qui décourage le plus sûrement une
 * quincaillerie de revenir : elle a rempli un formulaire, préparé ses offres,
 * et rien ne s'est passé. Qu'elle soit acceptée ou refusée, elle doit savoir.
 */
class DecisionInscription extends Notification
{
    use Queueable;

    public function __construct(public Vendeur $vendeur, public bool $acceptee) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->greeting('Bonjour ' . $notifiable->name . ',');

        if ($this->acceptee) {
            return $message
                ->subject('Votre établissement est vérifié — FamFer')
                ->line($this->vendeur->raison_sociale . ' est désormais vérifiée.')
                ->line('Vos offres sont visibles des acheteurs et vous pouvez recevoir des commandes.')
                ->line('Les sommes encaissées pour votre compte vous seront virées après chaque '
                    . 'réception confirmée.')
                ->action('Ouvrir mon commerce', route('vendeur.tableau'))
                ->salutation('FamFer');
        }

        return $message
            ->subject('Votre demande d\'inscription — FamFer')
            ->line('Votre demande pour ' . $this->vendeur->raison_sociale . ' n\'a pas été retenue.')
            ->line('Motif : ' . ($this->vendeur->motif_suspension ?: 'non précisé') . '.')
            ->line('Vous pouvez nous répondre pour corriger votre dossier.')
            ->salutation('FamFer');
    }
}
