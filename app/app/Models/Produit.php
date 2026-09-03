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

    public function photos(): HasMany
    {
        return $this->hasMany(PhotoProduit::class)->orderBy('rang')->orderBy('id');
    }

    /**
     * L'illustration de repli : celle de la catégorie du produit.
     *
     * Trois échelons, du plus précis au plus général : la photo du produit,
     * l'image de sa famille, puis le dessin au trait. Le dernier ne manque
     * jamais, donc aucun produit n'apparaît comme un cadre vide.
     */
    public function imageDeCategorie(): ?string
    {
        $categorie = $this->relationLoaded('categorie') ? $this->categorie : $this->categorie()->first();

        if (! $categorie) {
            return null;
        }

        return $categorie->urlImage()
            ?? $categorie->parente?->urlImage();
    }

    /**
     * La vignette : la première photo, ou rien.
     *
     * Les vues se rabattent sur le dessin vectoriel quand il n'y a pas de
     * photo. Un catalogue à moitié photographié vaut mieux qu'un catalogue où
     * les produits sans photo apparaissent comme des cadres vides.
     */
    public function vignette(): ?PhotoProduit
    {
        return $this->relationLoaded('photos')
            ? $this->photos->first()
            : $this->photos()->first();
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
