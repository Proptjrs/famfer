<?php

namespace App\Notifications;

use App\Models\Commande;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le courriel qui accompagne un changement d'état de commande.
 *
 * Une seule classe pour toutes les étapes : le texte change, la mécanique non.
 * Écrire sept classes qui diffèrent par trois phrases aurait multiplié les
 * endroits où l'on oublie le lien de suivi.
 *
 * Le destinataire n'est pas décidé ici mais dans NotificationService : selon
 * l'étape, c'est l'acheteur qui doit être prévenu, ou le vendeur, et se
 * tromper de destinataire reviendrait à annoncer à un client que sa propre
 * commande vient de lui être payée.
 */
class EtapeCommande extends Notification
{
    use Queueable;

    public function __construct(
        public Commande $commande,
        public string $titre,
        public array $lignes,
        public string $bouton,
        public string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->titre . ' — commande ' . $this->commande->reference)
            ->greeting('Bonjour ' . $notifiable->name . ',');

        foreach ($this->lignes as $ligne) {
            $message->line($ligne);
        }

        return $message
            ->action($this->bouton, $this->url)
            ->salutation('FamFer');
    }
}
