<?php

namespace App\Notifications;

use App\Models\Vendeur;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le compte de versement d'un vendeur vient de changer.
 *
 * C'est le geste qu'un intrus ferait en premier après avoir pris la main sur un
 * compte de commerçant : détourner la destination des virements. Le courriel ne
 * l'empêche pas — mais il fait que le vendeur l'apprend le jour même, et non
 * le jour où l'argent n'arrive pas.
 *
 * L'ancien compte est rappelé dans le message : sans lui, le vendeur ne peut
 * pas savoir si le changement est celui qu'il a fait lui-même.
 */
class ChangementCompteVersement extends Notification
{
    use Queueable;

    public function __construct(public Vendeur $vendeur, public string $ancien) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre compte de versement a été modifié — FamFer')
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Le compte qui reçoit les virements de '
                . $this->vendeur->raison_sociale . ' vient d\'être modifié.')
            ->line('Avant : ' . $this->ancien)
            ->line('Après : ' . $this->vendeur->compteDeVersement())
            ->line('Si vous n\'êtes pas à l\'origine de ce changement, changez '
                . 'votre mot de passe immédiatement et prévenez-nous : vos '
                . 'prochains virements partiraient ailleurs.')
            ->action('Vérifier mon compte', route('vendeur.argent'))
            ->salutation('FamFer');
    }
}
