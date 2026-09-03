<?php

namespace App\Services;

use App\Models\Categorie;
use App\Models\Produit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Chercher et parcourir le catalogue.
 *
 * Une place de marché grand public se parcourt de deux façons : en tapant un
 * mot, ou en descendant dans les rayons. Les deux aboutissent à la même liste,
 * filtrable et triable — c'est pourquoi il n'y a qu'une méthode.
 */
class Catalogue
{
    /** Les tris proposés, et ce qu'ils font. */
    public const TRIS = [
        'pertinence' => 'Les plus pertinents',
        'prix' => 'Prix croissant',
        'prix_desc' => 'Prix décroissant',
        'note' => 'Les mieux notés',
        'ventes' => 'Les plus vendus',
        'remise' => 'Les plus fortes remises',
    ];

    /**
     * @param  array{q?: string, categorie?: Categorie, min?: int, max?: int,
     *               tri?: string, marque?: string, stock?: bool}  $criteres
     */
    public function chercher(array $criteres, int $parPage = 24): LengthAwarePaginator
    {
        $requete = Produit::query()
            ->with('boutique', 'categorie')
            ->where('actif', true)
            // Un produit dont la boutique est fermée n'a rien à faire en rayon.
            ->whereHas('boutique', fn ($q) => $q->where('statut', 'active'));

        if ($mots = trim($criteres['q'] ?? '')) {
            $requete->where(function ($q) use ($mots) {
                foreach (preg_split('/\s+/', mb_strtolower($mots)) as $mot) {
                    // Chaque mot doit se retrouver quelque part : « fer 10 »
                    // exige « fer » ET « 10 », sinon toute recherche contenant
                    // « fer » remonterait le catalogue entier.
                    $q->where(function ($sous) use ($mot) {
                        $sous->whereRaw('LOWER(nom) LIKE ?', ["%{$mot}%"])
                             ->orWhereRaw('LOWER(COALESCE(marque, \'\')) LIKE ?', ["%{$mot}%"])
                             ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ["%{$mot}%"]);
                    });
                }
            });
        }

        if ($categorie = $criteres['categorie'] ?? null) {
            $requete->whereIn('categorie_id', $categorie->avecSesEnfants());
        }

        if ($min = $criteres['min'] ?? null) {
            $requete->where('prix', '>=', $min);
        }

        if ($max = $criteres['max'] ?? null) {
            $requete->where('prix', '<=', $max);
        }

        if ($marque = $criteres['marque'] ?? null) {
            $requete->where('marque', $marque);
        }

        if ($criteres['stock'] ?? false) {
            $requete->where('stock', '>', 0);
        }

        // Le tri par défaut met devant ce qui est en stock : proposer d'abord
        // ce qu'on ne peut pas acheter est le meilleur moyen de perdre un
        // client dès la première page.
        match ($criteres['tri'] ?? 'pertinence') {
            'prix' => $requete->orderBy('prix'),
            'prix_desc' => $requete->orderByDesc('prix'),
            'note' => $requete->orderByDesc('note_sur_cent')->orderByDesc('nombre_avis'),
            'ventes' => $requete->orderByDesc('nombre_ventes'),
            'remise' => $requete->orderByRaw(
                'CASE WHEN prix_barre IS NULL OR prix_barre <= prix THEN 0 '
                . 'ELSE (prix_barre - prix) * 100 / prix_barre END DESC'
            ),
            default => $requete->orderByRaw('CASE WHEN stock > 0 THEN 0 ELSE 1 END')
                               ->orderByDesc('nombre_ventes')->orderBy('id'),
        };

        return $requete->paginate($parPage)->withQueryString();
    }

    /** Les marques présentes dans une sélection, pour le filtre latéral. */
    public function marques(?Categorie $categorie = null): array
    {
        $requete = Produit::query()->where('actif', true)->whereNotNull('marque');

        if ($categorie) {
            $requete->whereIn('categorie_id', $categorie->avecSesEnfants());
        }

        return $requete->distinct()->orderBy('marque')->pluck('marque')->all();
    }

    /** Les produits mis en avant sur l'accueil. */
    public function enPromotion(int $combien = 8)
    {
        return Produit::with('boutique')
            ->where('actif', true)->where('stock', '>', 0)
            ->whereNotNull('prix_barre')
            ->whereColumn('prix_barre', '>', 'prix')
            ->whereHas('boutique', fn ($q) => $q->where('statut', 'active'))
            ->orderByRaw('(prix_barre - prix) * 100 / prix_barre DESC')
            ->limit($combien)->get();
    }

    public function lesPlusVendus(int $combien = 8)
    {
        return Produit::with('boutique')
            ->where('actif', true)->where('stock', '>', 0)
            ->whereHas('boutique', fn ($q) => $q->where('statut', 'active'))
            ->orderByDesc('nombre_ventes')->limit($combien)->get();
    }
}
