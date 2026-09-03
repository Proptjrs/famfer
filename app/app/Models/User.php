<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Un compte, et le rôle qu'il porte.
 *
 * Trois rôles seulement : client, vendeur, administration. Un vendeur reste
 * client — il achète aussi — mais son rôle décide de ce qu'il voit en entrant
 * et de ce à quoi il a droit.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'telephone'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'bloque_le' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function boutique(): HasOne
    {
        return $this->hasOne(Boutique::class, 'utilisateur_id');
    }

    public function adresses(): HasMany
    {
        return $this->hasMany(Adresse::class, 'utilisateur_id');
    }

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class, 'utilisateur_id');
    }

    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class, 'utilisateur_id');
    }

    public function estAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function estVendeur(): bool
    {
        return $this->role === 'vendeur';
    }

    /** L'adresse à proposer d'abord au moment de commander. */
    public function adresseParDefaut(): ?Adresse
    {
        return $this->adresses()->orderByDesc('par_defaut')->orderByDesc('id')->first();
    }
}
