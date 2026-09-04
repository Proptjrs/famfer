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
        'taux_commission_pour_mille',
    ];

    protected $casts = [
        'officielle' => 'boolean',
        'taux_commission_pour_mille' => 'integer',
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

    /** Le taux en pour cent, pour l'affichage : 80 pour mille = 8 %. */
    public function tauxPourCent(): float
    {
        return $this->taux_commission_pour_mille / 10;
    }

    /**
     * La commission due sur un montant de marchandise.
     *
     * En division entière : un franc n'a pas de sous-multiple, et arrondir au
     * plus proche donnerait à la plateforme un franc qu'elle n'a pas gagné.
     */
    public function commissionSur(int $montant): int
    {
        return intdiv($montant * $this->taux_commission_pour_mille, 1000);
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
