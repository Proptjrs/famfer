<?php

namespace App\Exceptions;

use RuntimeException;

/** Levée quand on tente un changement d'état que la machine n'autorise pas. */
class TransitionInterdite extends RuntimeException
{
    public function __construct(public readonly string $depart, public readonly string $arrivee)
    {
        parent::__construct(sprintf(
            'Une commande « %s » ne peut pas passer à « %s ».', $depart, $arrivee
        ));
    }
}
