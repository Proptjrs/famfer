<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Une photo de produit, stockée sur le disque public. */
class PhotoProduit extends Model
{
    protected $table = 'photos_produit';

    protected $fillable = ['produit_id', 'chemin', 'description', 'rang'];

    protected $casts = ['rang' => 'integer'];

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    /** L'adresse publique du fichier. */
    public function url(): string
    {
        return Storage::disk('public')->url($this->chemin);
    }

    /**
     * Efface le fichier avec l'enregistrement.
     *
     * Sans cela, supprimer un produit laisserait ses images sur le disque
     * indéfiniment — et le disque d'un hébergeur se paie.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $photo) {
            Storage::disk('public')->delete($photo->chemin);
        });
    }
}
