<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de commande.
 *
 * Le nom et le prix sont recopiés, pas référencés : un vendeur qui change son
 * tarif le lendemain ne doit pas changer ce que le client a accepté.
 */
class LigneCommande extends Model
{
    protected $table = 'lignes_commande';

    protected $fillable = [
        'commande_id', 'produit_id', 'boutique_id',
        'nom_produit', 'prix_unitaire', 'quantite', 'montant', 'commission',
    ];

    protected $casts = [
        'prix_unitaire' => 'integer', 'quantite' => 'integer',
        'montant' => 'integer', 'commission' => 'integer',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }
}
