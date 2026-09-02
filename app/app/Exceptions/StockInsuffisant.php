<?php

namespace App\Exceptions;

use RuntimeException;

/** Levée quand la quantité demandée dépasse ce qui reste réellement disponible. */
class StockInsuffisant extends RuntimeException
{
    public function __construct(public readonly int $disponible, public readonly int $demande)
    {
        parent::__construct(sprintf(
            'Stock insuffisant : %d demandés, %d disponibles.', $demande, $disponible
        ));
    }
}
