<?php

namespace App\Console\Commands;

use App\Models\Commande;
use App\Services\CommandeService;
use Illuminate\Console\Command;

/**
 * Les trois délais du cycle de commande.
 *
 * Ils s'exécutent même si personne ne visite le site : un panier abandonné
 * bloquerait sinon le stock d'un vendeur indéfiniment, et un acheteur distrait
 * retiendrait l'argent d'un vendeur pour toujours.
 */
class BalayerLesDelais extends Command
{
    protected $signature = 'famfer:delais';
    protected $description = 'Applique les délais de paiement, d\'acceptation et de réception';

    public function handle(CommandeService $commandes): int
    {
        $expirees = 0;
        foreach (Commande::where('etat', 'en_attente_paiement')
            ->where('expire_le', '<', now())->get() as $c) {
            $commandes->expirer($c);
            $expirees++;
        }

        $abandonnees = 0;
        foreach (Commande::where('etat', 'payee')
            ->where('acceptation_due_le', '<', now())->get() as $c) {
            // Le vendeur n'a pas répondu : l'acheteur est remboursé, et la note
            // du vendeur s'en ressentira.
            $commandes->annuler($c, 'Le vendeur n\'a pas répondu dans le délai');
            $abandonnees++;
        }

        $reputees = 0;
        foreach (Commande::where('etat', 'en_livraison')
            ->where('reception_due_le', '<', now())->get() as $c) {
            // Sans réponse de l'acheteur, la réception est réputée acquise :
            // sinon le vendeur ne serait jamais payé.
            $commandes->confirmerReception($c);
            $reputees++;
        }

        $this->info(sprintf(
            '  %d paiements expirés · %d commandes non acceptées · %d réceptions réputées acquises',
            $expirees, $abandonnees, $reputees
        ));

        return self::SUCCESS;
    }
}
