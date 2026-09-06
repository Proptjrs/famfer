<?php

namespace App\Console\Commands;

use App\Services\Visuels;
use Illuminate\Console\Command;

/**
 * Repose les visuels du catalogue à chaque démarrage.
 *
 * Le semeur ne suffit pas : il s'arrête net quand le catalogue existe déjà, et
 * le disque d'un conteneur ne survit pas à un redéploiement. La base garderait
 * ses lignes pendant que les fichiers auraient disparu, et chaque fiche
 * afficherait un cadre vide sans qu'aucune erreur ne le signale.
 */
class PoserLesVisuels extends Command
{
    protected $signature = 'famfer:visuels';

    protected $description = 'Repose les visuels de produit livrés avec le catalogue';

    public function handle(Visuels $visuels): int
    {
        $fait = $visuels->poser();

        $this->info(sprintf(
            '%d visuel(s) posé(s), %d refait(s) après perte du disque, %d déjà en place.',
            $fait['poses'], $fait['refaits'], $fait['ignores']
        ));

        return self::SUCCESS;
    }
}
