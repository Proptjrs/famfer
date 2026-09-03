<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une catégorie du catalogue. Deux niveaux : rayon, puis sous-rayon. */
class Categorie extends Model
{
    protected $fillable = ['parente_id', 'nom', 'slug', 'icone', 'rang'];

    public function parente(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'parente_id');
    }

    public function enfants(): HasMany
    {
        return $this->hasMany(Categorie::class, 'parente_id')->orderBy('rang');
    }

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class);
    }

    /**
     * Les rayons de premier niveau, avec le nombre de produits qu'ils portent.
     *
     * « withCount » ne compte que les produits rattachés au rayon lui-même, or
     * ils sont tous rangés dans ses sous-rayons : la barre affichait donc zéro
     * partout. On compte en remontant chaque produit à son rayon parent, en
     * une seule requête.
     */
    public static function rayonsAvecCompte()
    {
        $comptes = \App\Models\Produit::query()
            ->join('categories', 'produits.categorie_id', '=', 'categories.id')
            ->where('produits.actif', true)
            ->selectRaw('COALESCE(categories.parente_id, categories.id) AS rayon, COUNT(*) AS n')
            ->groupBy('rayon')
            ->pluck('n', 'rayon');

        return self::whereNull('parente_id')->orderBy('rang')->get()
            ->each(fn (self $r) => $r->produits_count = (int) ($comptes[$r->id] ?? 0));
    }

    /**
     * Les identifiants de cette catégorie et de ses enfants.
     *
     * Cliquer sur un rayon doit montrer tout ce qu'il contient, y compris ce
     * qui est rangé dans ses sous-rayons — sinon la page principale d'un rayon
     * paraît vide alors que le catalogue est plein.
     */
    public function avecSesEnfants(): array
    {
        return [$this->id, ...$this->enfants()->pluck('id')->all()];
    }
}
