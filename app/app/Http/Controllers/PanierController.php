<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Services\Livraison;
use App\Services\Panier;
use Illuminate\Http\Request;

/**
 * Le panier.
 *
 * Il ne demande pas de compte : exiger une inscription avant d'avoir rempli
 * son panier fait fuir la moitié des visiteurs. L'identité n'est demandée qu'à
 * la commande, quand il faut bien savoir où livrer.
 */
class PanierController extends Controller
{
    public function __construct(private Panier $panier, private Livraison $livraison) {}

    public function voir()
    {
        $sousTotal = $this->panier->sousTotal();

        return view('panier', [
            'contenu' => $this->panier->contenu(),
            'sousTotal' => $sousTotal,
            'resteAvantGratuite' => $this->livraison->resteAvantGratuite($sousTotal),
        ]);
    }

    public function ajouter(Request $r, Produit $produit)
    {
        if (! $produit->achetable()) {
            return back()->with('erreur', 'Ce produit n\'est plus disponible.');
        }

        $this->panier->ajouter($produit, max(1, $r->integer('quantite') ?: 1));

        return back()->with('ok', $produit->nom . ' a été ajouté au panier.');
    }

    public function modifier(Request $r, Produit $produit)
    {
        $this->panier->fixer($produit, $r->integer('quantite'));

        return back();
    }

    public function retirer(Produit $produit)
    {
        $this->panier->retirer($produit->id);

        return back()->with('ok', 'Retiré du panier.');
    }
}
