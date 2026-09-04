<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Commande;
use App\Models\LigneCommande;
use App\Models\Produit;
use App\Models\User;
use App\Services\Avertir;
use App\Services\Commissions;
use Illuminate\Http\Request;

/**
 * L'administration de la plateforme.
 *
 * Ce contrôleur n'est atteignable que par le rôle « admin » : le garde-fou est
 * posé sur le groupe de routes, et non ici. Sans lui, tout compte connecté
 * pourrait valider sa propre boutique.
 */
class AdminController extends Controller
{
    public function __construct(
        private Avertir $avertir,
        private Commissions $commissions,
    ) {}

    public function tableau()
    {
        $livrees = Commande::where('etat', 'livree');

        return view('admin.tableau', [
            'chiffres' => [
                'boutiques_actives' => Boutique::where('statut', 'active')->count(),
                'boutiques_en_attente' => Boutique::where('statut', 'en_attente')->count(),
                'produits' => Produit::where('actif', true)->count(),
                'clients' => User::where('role', 'client')->count(),
                'commandes' => Commande::count(),
                'volume_livre' => (int) (clone $livrees)->sum('total'),
                // Sans cette ligne, aucun écran ne disait si la plateforme
                // gagnait quelque chose.
                'commission' => (int) (clone $livrees)->sum('commission'),
                'a_expedier' => Commande::where('etat', 'en_preparation')->count(),
                'en_route' => Commande::whereIn('etat', ['expediee', 'en_livraison'])->count(),
                // Un colis refusé à la porte coûte une tournée : c'est
                // l'indicateur qui dit si le paiement à la livraison tient.
                'refusees' => Commande::where('etat', 'refusee')->count(),
                'taux_refus' => ($t = Commande::whereIn('etat', ['livree', 'refusee'])->count()) > 0
                    ? round(Commande::where('etat', 'refusee')->count() * 100 / $t, 1)
                    : 0.0,
            ],
            'aValider' => Boutique::with('utilisateur')->where('statut', 'en_attente')->get(),
            'meilleures' => Boutique::where('statut', 'active')
                ->orderByDesc('note_sur_cent')->orderByDesc('nombre_avis')->limit(5)->get(),
        ]);
    }

    public function boutiques(Request $r)
    {
        $requete = Boutique::with('utilisateur')->withCount('produits');

        if ($statut = $r->query('statut')) {
            $requete->where('statut', $statut);
        }

        return view('admin.boutiques', [
            'statutFiltre' => $statut,
            'boutiques' => $requete->orderBy('nom')->paginate(20)->withQueryString(),
            'parStatut' => Boutique::selectRaw('statut, count(*) as nombre')
                ->groupBy('statut')->pluck('nombre', 'statut'),
        ]);
    }

    public function activer(Boutique $boutique)
    {
        $boutique->update(['statut' => 'active', 'motif_suspension' => null]);
        $this->avertir->surDecisionBoutique($boutique->fresh(), true);

        return back()->with('ok', $boutique->nom . ' est active et visible au catalogue.');
    }

    public function suspendre(Request $r, Boutique $boutique)
    {
        $d = $r->validate(['motif' => 'required|string|max:200']);
        $boutique->update(['statut' => 'suspendue', 'motif_suspension' => $d['motif']]);
        $this->avertir->surDecisionBoutique($boutique->fresh(), false);

        return back()->with('ok', $boutique->nom . ' est suspendue. Ses produits sont retirés.');
    }

    /** La mise en avant réservée aux enseignes démarchées par la plateforme. */
    public function officielle(Boutique $boutique)
    {
        $boutique->update(['officielle' => ! $boutique->officielle]);

        return back()->with('ok', $boutique->officielle
            ? $boutique->nom . ' est désormais boutique officielle.'
            : $boutique->nom . ' n\'est plus boutique officielle.');
    }

    /** Ce que la plateforme gagne — la seule page qui dise si elle vit. */
    public function revenus()
    {
        return view('admin.revenus', [
            'chiffres' => $this->commissions->pourLaPlateforme(),
            'classement' => $this->commissions->classement(),
        ]);
    }

    /**
     * Renégocier le taux d'une boutique.
     *
     * Le taux se négocie : une enseigne qui apporte du volume n'a aucune raison
     * de payer comme un nouveau venu. Le nouveau taux ne vaut que pour l'avenir
     * — les commandes déjà passées portent le leur, figé.
     */
    public function taux(Request $r, Boutique $boutique)
    {
        $d = $r->validate([
            'taux' => 'required|numeric|min:0|max:30',
        ]);

        $boutique->update([
            'taux_commission_pour_mille' => (int) round($d['taux'] * 10),
        ]);

        return back()->with('ok', sprintf(
            'La commission de %s passe à %s %%. Les commandes déjà passées gardent leur taux.',
            $boutique->nom, rtrim(rtrim(number_format($d['taux'], 1, ',', ' '), '0'), ',')
        ));
    }

    public function commandes(Request $r)
    {
        $requete = Commande::with('utilisateur', 'lignes');

        if ($etat = $r->query('etat')) {
            $requete->where('etat', $etat);
        }

        return view('admin.commandes', [
            'etatFiltre' => $etat,
            'liste' => $requete->orderByDesc('id')->paginate(25)->withQueryString(),
            'parEtat' => Commande::selectRaw('etat, count(*) as nombre')
                ->groupBy('etat')->pluck('nombre', 'etat'),
        ]);
    }
}
