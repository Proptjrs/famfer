<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une note ne s'écrit qu'après réception : sans achat, elle ne vaut rien. */
class Evaluation extends Model
{
    protected $fillable = ['commande_id', 'vendeur_id', 'note', 'commentaire'];
    protected $casts = ['note' => 'integer'];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(Vendeur::class);
    }
}
