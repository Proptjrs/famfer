<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne du grand livre. Elle ne se modifie ni ne se supprime : un déclencheur
 * de la base le refuse, y compris à une commande SQL lancée à la main.
 */
class Ecriture extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'operation_id', 'operation', 'compte', 'sens', 'montant', 'commande_id', 'libelle',
    ];

    protected $casts = ['montant' => 'integer', 'created_at' => 'datetime'];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
}
