<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    protected $fillable = [
        'commande_id', 'operateur', 'cle_idempotence', 'reference_externe',
        'montant', 'frais_operateur', 'etat', 'charge_utile',
    ];

    protected $casts = [
        'montant' => 'integer',
        'frais_operateur' => 'integer',
        'charge_utile' => 'array',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
}
