<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Litige;
use App\Models\Offre;
use App\Models\Vendeur;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Les chiffres qui servent à décider.
 *
 * Deux lectures d'un même système. Le vendeur veut savoir ce qui se vend et ce
 * qui dort. La plateforme veut savoir ce qu'elle gagne, ce qu'elle retient, et
 * où ça grince.
 *
 * Un principe : la commission n'est comptée comme revenu que sur les commandes
 * soldées. Compter celles qui sont encore en séquestre gonflerait le chiffre
 * d'affaires d'un argent qui peut encore repartir chez l'acheteur.
 */
class PilotageService
{
    public function __construct(private GrandLivre $livre) {}

    /** Ce que voit un vendeur sur son tableau de bord. */
    public function pourVendeur(Vendeur $vendeur, int $jours = 30): array
    {
        $depuis = Carbon::now()->subDays($jours);

        $commandes = Commande::where('vendeur_id', $vendeur->id)
            ->where('created_at', '>=', $depuis);

        $soldees = (clone $commandes)->where('etat', 'soldee');

        return [
            'periode_jours' => $jours,
            'commandes_recues' => (clone $commandes)->count(),
            'commandes_soldees' => (clone $soldees)->count(),
            'chiffre_affaires' => (int) (clone $soldees)->sum('montant_total'),
            'commission_versee' => (int) (clone $soldees)->sum('montant_commission'),
            // Ce qu'il a réellement encaissé : le total moins notre commission.
            'net_percu' => (int) (clone $soldees)->sum(DB::raw('montant_total - montant_commission')),
            // Ce que nous lui devons encore, à l'instant présent.
            'reste_du' => $this->livre->solde(GrandLivre::compteVendeur($vendeur->id)),
            'annulees' => (clone $commandes)->whereIn('etat', ['annulee', 'expiree'])->count(),
            'litiges_ouverts' => Litige::where('etat', 'ouvert')
                ->whereHas('commande', fn ($q) => $q->where('vendeur_id', $vendeur->id))->count(),
            'articles_en_rupture' => Offre::where('vendeur_id', $vendeur->id)
                ->where('actif', true)
                ->whereColumn('quantite_reservee_pivot', '>=', 'quantite_pivot')->count(),
        ];
    }

    /**
     * Les articles d'un vendeur qui ne bougent pas.
     *
     * Du fer immobilisé, c'est de la trésorerie qui dort — souvent le premier
     * problème d'un quincaillier, et celui qu'il voit le moins.
     */
    public function dormants(Vendeur $vendeur, int $jours = 60): array
    {
        $depuis = Carbon::now()->subDays($jours);

        return Offre::with('article')
            ->where('vendeur_id', $vendeur->id)
            ->where('actif', true)
            ->where('quantite_pivot', '>', 0)
            ->whereDoesntHave('mouvements', fn ($q) => $q
                ->where('type', 'sortie_vente')->where('created_at', '>=', $depuis))
            ->get()
            ->map(fn (Offre $o) => [
                'article' => $o->article->designation,
                'reference' => $o->article->reference,
                'quantite_pivot' => $o->quantite_pivot,
            ])->all();
    }

    /** Ce que voit l'administration de la plateforme. */
    public function pourLaPlateforme(int $jours = 30): array
    {
        $depuis = Carbon::now()->subDays($jours);
        $commandes = Commande::where('created_at', '>=', $depuis);
        $soldees = (clone $commandes)->where('etat', 'soldee');

        $total = (clone $commandes)->count();
        $annulees = (clone $commandes)->whereIn('etat', ['annulee', 'expiree', 'remboursee'])->count();

        return [
            'periode_jours' => $jours,
            'vendeurs_verifies' => Vendeur::where('statut', 'verifie')->count(),
            'vendeurs_en_attente' => Vendeur::where('statut', 'en_attente')->count(),
            'commandes' => $total,
            'volume_traite' => (int) (clone $soldees)->sum('montant_total'),

            // Le seul revenu de la plateforme, et rien d'autre.
            'commission_acquise' => (int) (clone $soldees)->sum('montant_commission'),

            // Ce que nous détenons pour le compte d'autrui. Ce n'est pas un
            // revenu : c'est une dette envers les acheteurs.
            'sequestre_detenu' => $this->livre->solde(GrandLivre::SEQUESTRE),
            'du_aux_vendeurs' => $this->duAuxVendeurs(),
            'frais_operateur' => $this->livre->solde(GrandLivre::FRAIS),

            'taux_annulation_pour_cent' => $total > 0 ? round($annulees * 100 / $total, 1) : 0.0,
            'litiges_ouverts' => Litige::where('etat', 'ouvert')->count(),
            'litiges_tranches_acheteur' => Litige::where('etat', 'tranche_acheteur')->count(),
            'livre_equilibre' => $this->livre->estEquilibre(),
            'sequestre_justifie' => $this->livre->sequestreJustifie(),
        ];
    }

    /** La somme de ce que nous devons à tous les vendeurs. */
    public function duAuxVendeurs(): int
    {
        return collect(DB::table('ecritures')->where('compte', 'like', 'vendeur:%')
            ->distinct()->pluck('compte'))
            ->sum(fn (string $compte) => $this->livre->solde($compte));
    }
}
