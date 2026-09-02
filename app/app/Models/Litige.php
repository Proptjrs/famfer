<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Litige extends Model
{
    protected $fillable = [
        'commande_id', 'ouvert_par', 'motif', 'description', 'pieces_jointes',
        'etat', 'decision', 'arbitre_id', 'tranche_le',
    ];

    protected $casts = ['pieces_jointes' => 'array', 'tranche_le' => 'datetime'];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ouvert_par');
    }
}
