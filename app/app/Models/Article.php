<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    protected $fillable = [
        'famille_id', 'designation', 'reference', 'synonymes',
        'unite_pivot', 'caracteristiques', 'photo', 'actif',
    ];

    protected $casts = ['caracteristiques' => 'array', 'actif' => 'boolean'];

    public function famille(): BelongsTo
    {
        return $this->belongsTo(Famille::class);
    }

    public function unitesVente(): HasMany
    {
        return $this->hasMany(UniteVente::class);
    }

    /**
     * Les offres réellement achetables : vendeur vérifié, offre active, stock
     * disponible. C'est la seule liste qu'un acheteur doit jamais voir.
     */
    public function offresVisibles()
    {
        return $this->hasMany(Offre::class)
            ->with('vendeur', 'article.unitesVente')
            ->where('actif', true)
            ->whereHas('vendeur', fn ($q) => $q->where('statut', 'verifie'))
            ->get()
            ->filter(fn (Offre $o) => $o->disponiblePivot() > 0)
            ->values();
    }

    /** L'unité proposée en premier au vendeur comme à l'acheteur. */
    public function uniteParDefaut(): ?UniteVente
    {
        return $this->unitesVente()->where('par_defaut', true)->first()
            ?? $this->unitesVente()->first();
    }
}
