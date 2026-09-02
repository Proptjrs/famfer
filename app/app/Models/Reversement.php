<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reversement extends Model
{
    protected $fillable = ['vendeur_id', 'montant', 'etat', 'reference_virement', 'envoye_le'];
    protected $casts = ['montant' => 'integer', 'envoye_le' => 'datetime'];

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(Vendeur::class);
    }
}
