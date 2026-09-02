<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Acheteur extends Model
{
    protected $fillable = [
        'utilisateur_id', 'genre', 'telephone', 'adresse_defaut', 'latitude', 'longitude',
    ];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float'];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }
}
