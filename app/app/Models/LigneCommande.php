<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneCommande extends Model
{
    protected $table = 'lignes_commande';

    protected $fillable = [
        'commande_id', 'offre_id', 'quantite_pivot', 'unite_affichee',
        'quantite_affichee', 'prix_unitaire_fige', 'montant',
    ];

    protected $casts = [
        'quantite_pivot' => 'integer',
        'prix_unitaire_fige' => 'integer',
        'montant' => 'integer',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function offre(): BelongsTo
    {
        return $this->belongsTo(Offre::class);
    }
}
