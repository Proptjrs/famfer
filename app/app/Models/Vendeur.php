<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendeur extends Model
{
    protected $fillable = [
        'utilisateur_id', 'raison_sociale', 'ninea', 'telephone', 'adresse',
        'commune', 'latitude', 'longitude', 'statut', 'verifie_le', 'verifie_par',
        'motif_suspension', 'taux_commission_pour_mille',
        'versement_operateur', 'versement_numero', 'versement_titulaire',
        'versement_modifie_le',
    ];

    /**
     * La note et le nombre d'avis n'ont pas leur place dans « fillable ».
     *
     * Ils ne se saisissent pas : EvaluationService les recalcule depuis les
     * évaluations. Les laisser affectables en masse reviendrait à autoriser un
     * jour un formulaire à écrire sa propre réputation.
     */
    protected $casts = [
        'latitude' => 'float', 'longitude' => 'float',
        'note_sur_cent' => 'integer', 'nombre_evaluations' => 'integer',
        'verifie_le' => 'datetime', 'versement_modifie_le' => 'datetime',
        'taux_commission_pour_mille' => 'integer',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function offres(): HasMany
    {
        return $this->hasMany(Offre::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * La note sur cinq, telle qu'on l'affiche.
     *
     * Elle est nulle tant que personne n'a noté : afficher « 0/5 » à une maison
     * qui vient d'ouvrir serait un mensonge, et une condamnation.
     */
    public function noteSurCinq(): ?float
    {
        return $this->note_sur_cent === null ? null : round($this->note_sur_cent / 20, 1);
    }

    /** Seul un vendeur vérifié est visible des acheteurs. */
    /**
     * Sait-on où lui envoyer son argent ?
     *
     * Tant que non, aucun virement ne peut être préparé : une écriture qui
     * éteint la dette sans destination laisserait le vendeur sans recours et
     * le grand livre sans faute apparente.
     */
    public function peutEtreVire(): bool
    {
        return $this->versement_operateur !== null
            && $this->versement_numero !== null
            && $this->versement_titulaire !== null;
    }

    /** « Wave · 77 000 00 01 · Quincaillerie Ndiaye », pour l'affichage. */
    public function compteDeVersement(): ?string
    {
        return $this->peutEtreVire()
            ? strtoupper($this->versement_operateur) . ' · ' . $this->versement_numero
              . ' · ' . $this->versement_titulaire
            : null;
    }

    public function estVisible(): bool
    {
        return $this->statut === 'verifie';
    }

    /** La commission due sur un montant, en francs entiers. */
    public function commissionSur(int $montant): int
    {
        return intdiv($montant * $this->taux_commission_pour_mille, 1000);
    }
}
