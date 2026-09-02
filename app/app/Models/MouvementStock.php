<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne du journal de stock. Rien n'est jamais modifié ni supprimé :
 * une erreur se corrige par un mouvement inverse, qui laisse les deux traces.
 */
class MouvementStock extends Model
{
    protected $table = 'mouvements_stock';
    protected $fillable = ['offre_id', 'type', 'quantite_pivot', 'motif', 'auteur_id'];
    protected $casts = ['quantite_pivot' => 'integer'];

    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class);
    }
}
