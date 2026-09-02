<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commande extends Model
{
    /**
     * Les états, et les transitions autorisées depuis chacun.
     *
     * Le tableau n'est pas décoratif : chaque changement d'état le consulte. Sans
     * lui, une commande pourrait passer de « payée » à « soldée » sans être
     * jamais livrée, et le vendeur serait payé pour rien.
     */
    public const TRANSITIONS = [
        'en_attente_paiement' => ['payee', 'expiree'],
        'payee'               => ['acceptee', 'annulee'],
        'acceptee'            => ['prete', 'annulee'],
        'prete'               => ['en_livraison', 'receptionnee', 'annulee'],
        'en_livraison'        => ['receptionnee', 'en_litige'],
        'receptionnee'        => ['soldee', 'en_litige'],
        'en_litige'           => ['soldee', 'remboursee'],
        // États terminaux : plus rien n'en part.
        'soldee'              => [],
        'remboursee'          => [],
        'annulee'             => [],
        'expiree'             => [],
    ];

    /** Les états où la marchandise est réservée mais pas encore sortie. */
    public const ETATS_RESERVES = ['en_attente_paiement', 'payee', 'acceptee'];

    protected $fillable = [
        'reference', 'acheteur_id', 'vendeur_id', 'etat', 'mode_remise',
        'adresse_livraison', 'montant_articles', 'frais_livraison', 'montant_total',
        'taux_commission_pour_mille', 'montant_commission',
        'payee_le', 'acceptee_le', 'prete_le', 'livree_le', 'receptionnee_le',
        'soldee_le', 'annulee_le', 'motif_annulation',
        'expire_le', 'acceptation_due_le', 'reception_due_le',
    ];

    protected $casts = [
        'montant_articles' => 'integer',
        'frais_livraison' => 'integer',
        'montant_total' => 'integer',
        'montant_commission' => 'integer',
        'taux_commission_pour_mille' => 'integer',
        'payee_le' => 'datetime', 'acceptee_le' => 'datetime', 'prete_le' => 'datetime',
        'livree_le' => 'datetime', 'receptionnee_le' => 'datetime', 'soldee_le' => 'datetime',
        'annulee_le' => 'datetime', 'expire_le' => 'datetime',
        'acceptation_due_le' => 'datetime', 'reception_due_le' => 'datetime',
    ];

    public function acheteur(): BelongsTo
    {
        return $this->belongsTo(Acheteur::class);
    }

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(Vendeur::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneCommande::class);
    }

    public function evaluation()
    {
        return $this->hasOne(Evaluation::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(TransitionCommande::class);
    }

    public function peutAllerVers(string $etat): bool
    {
        return in_array($etat, self::TRANSITIONS[$this->etat] ?? [], true);
    }

    /** Ce que le vendeur touchera : le total moins la commission. */
    public function montantVendeur(): int
    {
        return $this->montant_total - $this->montant_commission;
    }
}
