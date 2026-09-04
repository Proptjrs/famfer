<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Commande;
use App\Models\LigneCommande;
use Illuminate\Support\Collection;

/**
 * Ce que la plateforme gagne, et ce que chaque boutique lui doit.
 *
 * Avec un séquestre, la question ne se poserait pas : la plateforme encaisserait
 * le client et reverserait au vendeur le solde, commission déduite. Le paiement
 * à la livraison inverse le sens du flux — c'est le vendeur qui touche les
 * espèces, la plateforme ne voit jamais l'argent. Elle ne retient donc rien :
 * elle facture.
 *
 * Deux règles gouvernent ce décompte, et elles sont l'une et l'autre en faveur
 * du vendeur.
 *
 * La commission n'est due que sur une commande **livrée**. Un colis refusé à la
 * porte a déjà coûté une tournée au commerçant ; lui facturer en plus la
 * commission d'une vente qui n'a pas eu lieu serait le punir deux fois. Un
 * retour efface la commission pour la même raison.
 *
 * Et elle ne porte que sur la **marchandise**. Les frais de livraison couvrent
 * un déplacement que le vendeur paie de sa poche : en prélever une part
 * reviendrait à taxer son carburant.
 */
class Commissions
{
    /** Les états sur lesquels la commission est exigible. */
    public const EXIGIBLE = ['livree'];

    /** Le décompte d'une boutique : dû, encaissé, net. */
    public function pourBoutique(Boutique $boutique): array
    {
        $lignes = LigneCommande::where('boutique_id', $boutique->id)
            ->whereHas('commande', fn ($q) => $q->whereIn('etat', self::EXIGIBLE));

        $marchandise = (int) (clone $lignes)->sum('montant');
        $commission = (int) (clone $lignes)->sum('commission');

        $port = $this->portEncaisse($boutique);

        return [
            'taux' => $boutique->tauxPourCent(),
            'marchandise' => $marchandise,
            'port' => $port,
            'encaisse' => $marchandise + $port,
            'commission' => $commission,
            'net' => $marchandise + $port - $commission,
            'ventes' => (int) (clone $lignes)->sum('quantite'),
        ];
    }

    /**
     * Le port qui revient à cette boutique.
     *
     * Une commande ne porte qu'un seul frais de livraison, quel que soit le
     * nombre d'enseignes qu'elle traverse. Le compter en entier pour chacune
     * inventerait de l'argent : la même somme apparaîtrait deux fois. On ne
     * l'attribue donc que sur les commandes dont cette boutique est le seul
     * fournisseur — celles qu'elle a effectivement livrées seule.
     *
     * Les commandes partagées restent à répartir : elles supposent une tournée
     * groupée, donc un accord entre vendeurs que le logiciel ne peut pas
     * deviner. Mieux vaut ne rien attribuer que de créditer à tort.
     */
    private function portEncaisse(Boutique $boutique): int
    {
        return (int) Commande::whereIn('etat', self::EXIGIBLE)
            ->whereHas('lignes', fn ($q) => $q->where('boutique_id', $boutique->id))
            ->whereDoesntHave('lignes', fn ($q) => $q->where('boutique_id', '!=', $boutique->id))
            ->sum('frais_livraison');
    }

    /**
     * Le relevé mois par mois, du plus récent au plus ancien.
     *
     * C'est le document que le vendeur reçoit et sur lequel il paie : une place
     * de marché qui facture sans détailler ne garde pas ses commerçants.
     */
    public function releveMensuel(Boutique $boutique, int $mois = 12): Collection
    {
        return LigneCommande::where('lignes_commande.boutique_id', $boutique->id)
            ->join('commandes', 'commandes.id', '=', 'lignes_commande.commande_id')
            ->whereIn('commandes.etat', self::EXIGIBLE)
            ->selectRaw("to_char(commandes.livree_le, 'YYYY-MM') as periode")
            ->selectRaw('sum(lignes_commande.montant) as marchandise')
            ->selectRaw('sum(lignes_commande.commission) as commission')
            ->selectRaw('count(distinct commandes.id) as commandes')
            ->groupBy('periode')->orderByDesc('periode')->limit($mois)->get();
    }

    /** Le revenu de la plateforme, toutes boutiques confondues. */
    public function pourLaPlateforme(): array
    {
        $lignes = LigneCommande::whereHas(
            'commande', fn ($q) => $q->whereIn('etat', self::EXIGIBLE)
        );

        $marchandise = (int) (clone $lignes)->sum('montant');
        $commission = (int) (clone $lignes)->sum('commission');

        return [
            'volume' => $marchandise,
            'commission' => $commission,
            // Le taux réellement obtenu, et non celui affiché au contrat : les
            // deux divergent dès qu'une enseigne négocie.
            'taux_moyen' => $marchandise > 0
                ? round($commission * 100 / $marchandise, 2)
                : 0.0,
            'perdue_sur_refus' => (int) LigneCommande::whereHas(
                'commande', fn ($q) => $q->whereIn('etat', ['refusee', 'retournee'])
            )->sum('commission'),
        ];
    }

    /** Les boutiques qui doivent le plus, pour la relance. */
    public function classement(int $limite = 10): Collection
    {
        return Boutique::query()
            ->select('boutiques.*')
            ->selectRaw('coalesce((
                select sum(l.commission) from lignes_commande l
                join commandes c on c.id = l.commande_id
                where l.boutique_id = boutiques.id and c.etat = \'livree\'
            ), 0) as commission_due')
            ->orderByDesc('commission_due')->limit($limite)->get();
    }
}
