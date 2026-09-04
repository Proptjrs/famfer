<?php

namespace App\Notifications;

use App\Models\Boutique;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * La réponse de l'administration sur une boutique.
 *
 * Une demande laissée sans réponse est ce qui décourage le plus sûrement un
 * commerçant de revenir : il a rempli un formulaire, préparé ses produits, et
 * rien ne s'est passé. Qu'elle soit acceptée ou suspendue, il doit l'apprendre.
 */
class DecisionBoutique extends Notification
{
    use Queueable;

    public function __construct(public Boutique $boutique, public bool $acceptee) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->greeting('Bonjour ' . $notifiable->name . ',');

        if ($this->acceptee) {
            return $message
                ->subject('Votre boutique est en ligne — FamFer')
                ->line($this->boutique->nom . ' est validée.')
                ->line('Vos produits apparaissent au catalogue et vous pouvez '
                    . 'recevoir des commandes.')
                ->line('Pensez à photographier votre marchandise : les fiches sans '
                    . 'photo se vendent nettement moins.')
                ->action('Ouvrir ma boutique', route('vendeur.tableau'))
                ->salutation('FamFer');
        }

        return $message
            ->subject('Votre boutique est suspendue — FamFer')
            ->line($this->boutique->nom . ' est suspendue et ses produits sont '
                . 'retirés du catalogue.')
            ->line('Motif : ' . ($this->boutique->motif_suspension ?: 'non précisé') . '.')
            ->line('Répondez à ce message pour corriger votre dossier.')
            ->salutation('FamFer');
    }
}
