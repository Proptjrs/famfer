<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Famille extends Model
{
    protected $table = 'familles';
    protected $fillable = ['nom', 'code', 'parente_id', 'rang'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function sousFamilles(): HasMany
    {
        return $this->hasMany(self::class, 'parente_id');
    }
}
