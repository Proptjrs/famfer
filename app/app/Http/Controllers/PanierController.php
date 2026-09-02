<?php

namespace App\Http\Controllers;

use App\Exceptions\StockInsuffisant;
use App\Models\Offre;
use App\Services\CommandeService;
use App\Services\ConversionUnites;
use App\Services\GeolocService;
use App\Services\LivraisonService;
use Illuminate\Http\Request;

/**
 * Le panier vit en session, pas en base.
 *
 * Rien n'est réservé tant que la commande n'est pas passée : un panier
 * abandonné ne doit bloquer le stock de personne. La réservation n'intervient
 * qu'à la validation, et elle tombe si le paiement n'arrive pas.
 */
class PanierController extends Controller
{
    public function __construct(
        private ConversionUnites $conversion,
        private CommandeService $commandes,
        private LivraisonService $livraison,
        private GeolocService $geoloc,
    ) {}

    public function ajouter(Request $r, Offre $offre)
    {
        $d = $r->validate([
            'quantite' => 'required|string|max:12',
            'unite' => 'required|string|max:20',
        ]);

        try {
            $pivot = $this->conversion->versPivot($offre->article, $d['unite'], $d['quantite']);
        } catch (\RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        if ($pivot > $offre->disponiblePivot()) {
            return back()->with('erreur', 'Ce vendeur n\'a pas cette quantité en stock.');
        }

        $panier = session('panier', []);
        $panier[$offre->id] = [
            'offre_id' => $offre->id,
            'quantite' => $d['quantite'],
            'unite' => $d['unite'],
            'pivot' => $pivot,
        ];
        session(['panier' => $panier]);

        return redirect()->route('panier.voir')->with('ok', 'Ajouté au panier.');
    }

    public function voir(Request $r)
    {
        $panier = session('panier', []);
        $offres = Offre::with('article.unitesVente', 'vendeur')
            ->whereIn('id', array_keys($panier))->get()->keyBy('id');

        // Un panier réparti sur plusieurs quincailleries donnera une commande
        // par vendeur : trois livraisons, trois séquestres, trois reversements.
        $parVendeur = collect($panier)
            ->filter(fn ($l) => $offres->has($l['offre_id']))
            ->groupBy(fn ($l) => $offres[$l['offre_id']]->vendeur_id);

        // Le point de livraison : celui que l'acheteur vient d'indiquer, sinon
        // celui de son compte. Sans lui, aucun devis n'est possible.
        $lat = $r->filled('lat') ? (float) $r->input('lat') : $r->user()->acheteur?->latitude;
        $lng = $r->filled('lng') ? (float) $r->input('lng') : $r->user()->acheteur?->longitude;

        // Le devis, vendeur par vendeur : c'est le même service qui facturera.
        $devis = [];
        if ($lat !== null && $lng !== null) {
            foreach ($parVendeur as $vendeurId => $lignes) {
                $vendeur = $offres[$lignes->first()['offre_id']]->vendeur;
                $poids = $lignes->sum('pivot');

                try {
                    $devis[$vendeurId] = $this->livraison->detail(
                        $this->geoloc->distance($lat, $lng, $vendeur->latitude, $vendeur->longitude),
                        $poids
                    );
                } catch (\RuntimeException $e) {
                    // Hors rayon ou trop lourd : on le dit ici, avant la
                    // validation, plutôt que de laisser l'acheteur buter dessus.
                    $devis[$vendeurId] = ['refus' => $e->getMessage()];
                }
            }
        }

        return view('acheteur.panier', compact('panier', 'offres', 'parVendeur', 'devis', 'lat', 'lng'));
    }

    public function retirer(Offre $offre)
    {
        $panier = session('panier', []);
        unset($panier[$offre->id]);
        session(['panier' => $panier]);

        return back()->with('ok', 'Retiré du panier.');
    }

    /** Valide le panier : une commande par vendeur, et la marchandise est réservée. */
    public function valider(Request $r)
    {
        $acheteur = $r->user()->acheteur;
        if (! $acheteur) {
            return back()->with('erreur', 'Votre compte n\'est pas un compte acheteur.');
        }

        $panier = session('panier', []);
        if ($panier === []) {
            return back()->with('erreur', 'Votre panier est vide.');
        }

        $offres = Offre::with('article.unitesVente', 'vendeur')
            ->whereIn('id', array_keys($panier))->get()->keyBy('id');

        $creees = [];
        foreach (collect($panier)->groupBy(fn ($l) => $offres[$l['offre_id']]->vendeur_id) as $lignes) {
            $pour = $lignes->map(fn ($l) => [
                'offre' => $offres[$l['offre_id']],
                'quantite' => $l['quantite'],
                'unite' => $l['unite'],
            ])->values()->all();

            try {
                $creees[] = $this->commandes->creer(
                    $acheteur, $pour,
                    $r->input('mode_remise', 'retrait'),
                    $r->input('adresse'),
                    $r->filled('lat') ? (float) $r->input('lat') : null,
                    $r->filled('lng') ? (float) $r->input('lng') : null,
                );
            } catch (StockInsuffisant $e) {
                return back()->with('erreur', 'Le stock a changé pendant votre commande : ' . $e->getMessage());
            } catch (\RuntimeException $e) {
                // Hors rayon, au-dessus de la charge utile, ou sans adresse
                // repérée : la commande n'est pas créée, le panier est gardé.
                return back()->with('erreur', $e->getMessage());
            }
        }

        session()->forget('panier');

        return redirect()->route('acheteur.commandes')
            ->with('ok', count($creees) . ' commande(s) créée(s). Réglez sous quinze minutes.');
    }
}
