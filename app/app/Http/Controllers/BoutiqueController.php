<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Produit;
use Illuminate\Http\Request;

/**
 * La vitrine publique d'une boutique.
 *
 * Un client ne compare pas que des prix : il regarde à qui il achète. Sans
 * cette page, la note et les avis d'un vendeur resteraient invisibles.
 */
class BoutiqueController extends Controller
{
    public function vitrine(Request $r, Boutique $boutique)
    {
        abort_unless($boutique->estVisible(), 404);

        return view('boutique', [
            'boutique' => $boutique,
            'produits' => Produit::where('boutique_id', $boutique->id)
                ->where('actif', true)
                ->orderByRaw('CASE WHEN stock > 0 THEN 0 ELSE 1 END')
                ->orderByDesc('nombre_ventes')
                ->paginate(24)->withQueryString(),
        ]);
    }
}
