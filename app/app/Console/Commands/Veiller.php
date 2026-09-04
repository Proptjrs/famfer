<?php

namespace App\Console\Commands;

use App\Services\Veille;
use Illuminate\Console\Command;

/**
 * Le tour de veille quotidien.
 *
 * Séparé du service pour que l'essai puisse appeler l'un sans l'autre : une
 * commande Artisan qui contient sa propre logique ne se teste qu'à travers une
 * sortie console, ce qui est le plus mauvais endroit où vérifier une règle.
 */
class Veiller extends Command
{
    protected $signature = 'famfer:veiller';

    protected $description = 'Relance les commandes dormantes et ferme les fenêtres de contestation';

    public function handle(Veille $veille): int
    {
        $fait = $veille->passer();

        $this->info(sprintf(
            '%d commande(s) relancée(s), %d fenêtre(s) de contestation fermée(s).',
            $fait['relancees'], $fait['closes']
        ));

        return self::SUCCESS;
    }
}
