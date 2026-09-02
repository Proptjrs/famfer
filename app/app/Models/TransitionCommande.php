<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Le journal des changements d'état. Rien n'est modifié, on ajoute. */
class TransitionCommande extends Model
{
    protected $table = 'transitions_commande';
    public $timestamps = false;

    protected $fillable = ['commande_id', 'etat_depart', 'etat_arrivee', 'motif', 'auteur_id'];
    protected $casts = ['created_at' => 'datetime'];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
}
