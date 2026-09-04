<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une commande, payée à la livraison.
 *
 * Le client règle au livreur : la plateforme ne détient jamais son argent. La
 * contrepartie est que le colis part avant d'être payé, d'où un état
 * « refusée » distinct d'« annulée » — l'un coûte une tournée, l'autre non.
 */
class Commande extends Model
{
    /** Les états, et ceux qu'on peut atteindre depuis chacun. */
    public const SUITES = [
        'en_preparation' => ['expediee', 'annulee'],
        // « refusee » aussi depuis « expediee » : le passage par « en livraison »
        // est facultatif — un vendeur qui livre lui-même annonce la remise, pas
        // le départ du camion — et un colis se refuse à la porte dans les deux
        // cas. Sans cela, l'état restait inatteignable en pratique.
        'expediee'       => ['en_livraison', 'livree', 'refusee', 'annulee', 'litige'],
        'en_livraison'   => ['livree', 'refusee', 'litige'],
        'livree'         => ['retournee', 'litige'],
        // « refusee » n'est plus terminal : c'est la porte de sortie du client
        // dont le vendeur a nié la livraison après l'avoir encaissée. Sans
        // cette transition, la fraude était sans recours.
        'refusee'        => ['litige'],
        // Seule l'administration sort d'un litige, et elle tranche vers un état
        // réel — jamais vers un autre litige.
        'litige'         => ['livree', 'refusee', 'annulee'],
        // Terminaux.
        'annulee'        => [],
        'retournee'      => [],
    ];

    /** Les deux camps qui peuvent ouvrir un litige. */
    public const PARTIES = ['client', 'vendeur'];

    protected $fillable = [
        'reference', 'utilisateur_id', 'destinataire', 'telephone', 'adresse_livraison',
        'etat', 'paiement', 'paye', 'sous_total', 'frais_livraison', 'total',
        'taux_commission_pour_mille', 'commission',
        'expediee_le', 'livree_le', 'cloturee_le', 'motif',
        'code_livraison', 'code_remis_le', 'confirmee_le',
        'litige_par', 'litige_motif', 'litige_le', 'etat_conteste',
    ];

    protected $casts = [
        'paye' => 'boolean',
        'sous_total' => 'integer', 'frais_livraison' => 'integer', 'total' => 'integer',
        'taux_commission_pour_mille' => 'integer', 'commission' => 'integer',
        'code_remis_le' => 'datetime', 'confirmee_le' => 'datetime',
        'litige_le' => 'datetime',
        'expediee_le' => 'datetime', 'livree_le' => 'datetime', 'cloturee_le' => 'datetime',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneCommande::class);
    }

    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class);
    }

    /**
     * Ce que la boutique garde, sur cette commande.
     *
     * Le total encaissé au client, moins la commission due à la plateforme.
     * Les frais de livraison en font partie : c'est le vendeur qui livre.
     */
    public function netVendeur(): int
    {
        return $this->total - $this->commission;
    }

    /**
     * La commission est-elle due ?
     *
     * Seulement sur une commande livrée. Un colis refusé à la porte a déjà
     * coûté une tournée au vendeur ; lui facturer en plus la commission d'une
     * vente qui n'a pas eu lieu serait le punir deux fois.
     */
    public function commissionDue(): bool
    {
        return $this->etat === 'livree';
    }

    public function peutAllerVers(string $etat): bool
    {
        return in_array($etat, self::SUITES[$this->etat] ?? [], true);
    }

    /**
     * Le client peut-il confirmer avoir reçu ?
     *
     * C'est le contrepoids de tout le dispositif : sa déclaration vaut celle du
     * vendeur. Un commerçant qui aurait encaissé puis nié la livraison se voit
     * contredit par l'acheteur, et la commission redevient due.
     */
    public function confirmableParLeClient(): bool
    {
        return in_array($this->etat, ['expediee', 'en_livraison'], true)
            && $this->confirmee_le === null;
    }

    /**
     * Le client peut-il contester ?
     *
     * Deux situations, opposées et toutes deux réelles : le vendeur a déclaré
     * un refus qui n'a pas eu lieu, ou une livraison qui n'a pas eu lieu.
     */
    public function contestableParLeClient(): bool
    {
        return in_array($this->etat, ['refusee', 'livree'], true);
    }

    public function enLitige(): bool
    {
        return $this->etat === 'litige';
    }

    /** Le client peut encore annuler tant que rien n'est parti. */
    public function annulableParLeClient(): bool
    {
        return $this->etat === 'en_preparation';
    }
}
