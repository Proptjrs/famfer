<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Models\Produit;
use App\Services\Catalogue;
use Illuminate\Http\Request;

/** L'accueil, la recherche, les rayons et la fiche produit. */
class CatalogueController extends Controller
{
    public function __construct(private Catalogue $catalogue) {}

    public function accueil()
    {
        return view('accueil', [
            'promotions' => $this->catalogue->enPromotion(8),
            'populaires' => $this->catalogue->lesPlusVendus(8),
            'rayons' => Categorie::rayonsAvecCompte(),
        ]);
    }

    public function recherche(Request $r)
    {
        return view('catalogue', [
            'titre' => ($q = trim($r->query('q', ''))) !== ''
                ? 'Résultats pour « ' . $q . ' »' : 'Tous les produits',
            'categorie' => null,
            'produits' => $this->catalogue->chercher($this->criteres($r)),
            'marques' => $this->catalogue->marques(),
        ]);
    }

    public function rayon(Request $r, Categorie $categorie)
    {
        return view('catalogue', [
            'titre' => $categorie->nom,
            'categorie' => $categorie,
            'produits' => $this->catalogue->chercher(
                $this->criteres($r) + ['categorie' => $categorie]
            ),
            'marques' => $this->catalogue->marques($categorie),
        ]);
    }

    public function produit(Produit $produit)
    {
        abort_unless($produit->actif && $produit->boutique->estVisible(), 404);

        return view('produit', [
            'produit' => $produit->load('boutique', 'categorie'),
            'avis' => $produit->avis()->with('utilisateur')
                ->whereNotNull('commentaire')->orderByDesc('id')->limit(10)->get(),
            // Le même article ailleurs : c'est ce qui fait comparer les prix.
            'ailleurs' => Produit::with('boutique')
                ->where('nom', $produit->nom)->where('id', '!=', $produit->id)
                ->where('actif', true)
                ->whereHas('boutique', fn ($q) => $q->where('statut', 'active'))
                ->orderBy('prix')->limit(5)->get(),
            'similaires' => Produit::with('boutique')
                ->where('categorie_id', $produit->categorie_id)
                ->where('id', '!=', $produit->id)->where('actif', true)
                ->where('stock', '>', 0)
                ->whereHas('boutique', fn ($q) => $q->where('statut', 'active'))
                ->orderByDesc('nombre_ventes')->limit(6)->get(),
        ]);
    }

    /** Les filtres communs à la recherche et aux rayons. */
    private function criteres(Request $r): array
    {
        return array_filter([
            'q' => trim($r->query('q', '')),
            'min' => $r->integer('min') ?: null,
            'max' => $r->integer('max') ?: null,
            'marque' => $r->query('marque'),
            'tri' => $r->query('tri'),
            'stock' => $r->boolean('stock'),
        ]);
    }
}
