<?php

namespace App\Services;

use App\Models\Produit;
use Illuminate\Support\Collection;

/**
 * Le panier, en session.
 *
 * Rien n'est réservé : sur une place de marché où le paiement se fait à la
 * livraison, un panier abandonné ne doit bloquer le stock de personne. Le
 * stock n'est décrémenté qu'à la commande, et la disponibilité se revérifie à
 * ce moment-là — entre-temps, un autre client a pu acheter le dernier article.
 */
class Panier
{
    private const CLE = 'panier';

    /** @return array<int, int> identifiant du produit => quantité */
    public function lignes(): array
    {
        return session(self::CLE, []);
    }

    public function ajouter(Produit $produit, int $quantite = 1): void
    {
        $lignes = $this->lignes();
        $voulu = ($lignes[$produit->id] ?? 0) + $quantite;

        // On ne laisse pas mettre au panier plus que le stock : découvrir au
        // paiement qu'on ne peut pas tout avoir est la pire des surprises.
        $lignes[$produit->id] = max(1, min($voulu, $produit->stock));

        session([self::CLE => $lignes]);
    }

    public function fixer(Produit $produit, int $quantite): void
    {
        $lignes = $this->lignes();

        if ($quantite <= 0) {
            unset($lignes[$produit->id]);
        } else {
            $lignes[$produit->id] = min($quantite, $produit->stock);
        }

        session([self::CLE => $lignes]);
    }

    public function retirer(int $produitId): void
    {
        $lignes = $this->lignes();
        unset($lignes[$produitId]);
        session([self::CLE => $lignes]);
    }

    public function vider(): void
    {
        session()->forget(self::CLE);
    }

    public function nombreArticles(): int
    {
        return array_sum($this->lignes());
    }

    /**
     * Le contenu du panier, produits chargés.
     *
     * Les produits devenus inachetables — retirés de la vente, en rupture, ou
     * dont la boutique a fermé — sont écartés ici plutôt qu'au paiement.
     *
     * La quantité demandée est rendue telle quelle, à côté de ce qui reste
     * réellement en stock. Les raboter en silence ferait croire au client qu'il
     * commande deux barres quand il n'en recevrait qu'une : l'écart doit se
     * voir sur la page du panier, et bloquer la commande tant qu'il subsiste.
     *
     * @return Collection<int, array{produit: Produit, quantite: int,
     *                               disponible: int, ajuste: bool, montant: int}>
     */
    public function contenu(): Collection
    {
        $lignes = $this->lignes();

        if ($lignes === []) {
            return collect();
        }

        return Produit::with('boutique')
            ->whereIn('id', array_keys($lignes))
            ->get()
            ->filter(fn (Produit $p) => $p->achetable())
            ->map(function (Produit $p) use ($lignes) {
                $demande = $lignes[$p->id];
                $disponible = min($demande, $p->stock);

                return [
                    'produit' => $p,
                    'quantite' => $demande,
                    'disponible' => $disponible,
                    'ajuste' => $demande > $disponible,
                    // Le montant porte sur ce qui peut réellement partir.
                    'montant' => $p->prix * $disponible,
                ];
            })
            ->values();
    }

    public function sousTotal(): int
    {
        return (int) $this->contenu()->sum('montant');
    }

    /** Les lignes dont la quantité demandée dépasse le stock restant. */
    public function lignesAjustees(): Collection
    {
        return $this->contenu()->filter(fn (array $l) => $l['ajuste'])->values();
    }
}
