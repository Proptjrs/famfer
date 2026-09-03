<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** La boutique d'un vendeur : ce que le client voit avant de lui acheter. */
class Boutique extends Model
{
    protected $fillable = [
        'utilisateur_id', 'nom', 'slug', 'description', 'telephone',
        'adresse', 'ville', 'officielle', 'statut', 'motif_suspension',
    ];

    protected $casts = [
        'officielle' => 'boolean',
        'note_sur_cent' => 'integer',
        'nombre_avis' => 'integer',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class);
    }

    public function estVisible(): bool
    {
        return $this->statut === 'active';
    }

    /** La note sur cinq, à une décimale — « nouvelle boutique » si aucune. */
    public function noteSurCinq(): ?float
    {
        return $this->note_sur_cent === null ? null : round($this->note_sur_cent / 20, 1);
    }
}
