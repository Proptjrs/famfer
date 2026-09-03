<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un produit, vendu par une boutique.
 *
 * Le prix barré n'est pas décoratif : la remise se calcule des deux prix et
 * n'est jamais saisie. Annoncer « -40 % » sur des chiffres qui ne le disent pas
 * est la première tricherie d'une place de marché, et la plus facile à éviter.
 */
class Produit extends Model
{
    protected $fillable = [
        'boutique_id', 'categorie_id', 'nom', 'slug', 'description', 'marque',
        'prix', 'prix_barre', 'stock', 'dessin', 'actif',
    ];

    protected $casts = [
        'prix' => 'integer', 'prix_barre' => 'integer',
        'stock' => 'integer', 'actif' => 'boolean',
        'note_sur_cent' => 'integer', 'nombre_avis' => 'integer',
        'nombre_ventes' => 'integer',
    ];

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class);
    }

    /** Le pourcentage de remise, ou null s'il n'y en a pas. */
    public function remise(): ?int
    {
        if (! $this->prix_barre || $this->prix_barre <= $this->prix) {
            return null;
        }

        return (int) round(($this->prix_barre - $this->prix) * 100 / $this->prix_barre);
    }

    public function enStock(): bool
    {
        return $this->stock > 0;
    }

    public function noteSurCinq(): ?float
    {
        return $this->note_sur_cent === null ? null : round($this->note_sur_cent / 20, 1);
    }

    /** Achetable : encore en vente, en stock, et la boutique est ouverte. */
    public function achetable(): bool
    {
        return $this->actif && $this->enStock() && $this->boutique->estVisible();
    }
}
