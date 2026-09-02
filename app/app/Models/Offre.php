<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offre extends Model
{
    protected $fillable = [
        'vendeur_id', 'article_id', 'prix_par_unite', 'unite_affichee',
        'quantite_pivot', 'quantite_reservee_pivot', 'delai_preparation_h', 'actif',
    ];

    protected $casts = [
        'prix_par_unite' => 'integer',
        'quantite_pivot' => 'integer',
        'quantite_reservee_pivot' => 'integer',
        'actif' => 'boolean',
    ];

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(Vendeur::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    /** Ce qu'un acheteur peut réellement commander maintenant. */
    public function disponiblePivot(): int
    {
        return max(0, $this->quantite_pivot - $this->quantite_reservee_pivot);
    }

    /**
     * Le prix ramené à l'unité pivot, pour comparer deux vendeurs.
     *
     * L'un affiche 4 200 F la barre, l'autre 570 F le kilo : seul le prix au
     * gramme permet de dire lequel est le moins cher.
     */
    public function prixParPivot(): float
    {
        $facteur = $this->article->unitesVente()
            ->where('unite', $this->unite_affichee)->value('facteur_vers_pivot');

        return $facteur ? $this->prix_par_unite / $facteur : 0.0;
    }
}
