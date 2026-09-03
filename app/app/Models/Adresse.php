<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une adresse du carnet du client. */
class Adresse extends Model
{
    protected $table = 'adresses';

    protected $fillable = [
        'utilisateur_id', 'destinataire', 'telephone',
        'region', 'ville', 'quartier', 'repere', 'par_defaut',
    ];

    protected $casts = ['par_defaut' => 'boolean'];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    /** L'adresse en une ligne, telle qu'elle est recopiée sur la commande. */
    public function enUneLigne(): string
    {
        return collect([$this->quartier, $this->repere, $this->ville, $this->region])
            ->filter()->implode(', ');
    }
}
