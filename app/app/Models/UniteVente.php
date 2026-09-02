<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UniteVente extends Model
{
    protected $table = 'unites_vente';
    protected $fillable = ['article_id', 'unite', 'facteur_vers_pivot', 'par_defaut'];
    protected $casts = ['facteur_vers_pivot' => 'integer', 'par_defaut' => 'boolean'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
