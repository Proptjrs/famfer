<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un avis, adossé à une commande livrée : sans achat, une note ne vaut rien. */
class Avis extends Model
{
    protected $table = 'avis';

    protected $fillable = [
        'utilisateur_id', 'produit_id', 'commande_id', 'note', 'titre', 'commentaire',
    ];

    protected $casts = ['note' => 'integer'];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
}
