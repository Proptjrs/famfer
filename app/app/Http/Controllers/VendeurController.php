<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Commande;
use App\Models\LigneCommande;
use App\Models\PhotoProduit;
use App\Models\Produit;
use App\Services\PasseCommande;
use App\Services\Photos;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * L'espace du vendeur.
 *
 * Chaque action lit la boutique du compte connecté plutôt qu'un identifiant
 * passé en paramètre : sur une place de marché, un vendeur ne doit jamais
 * pouvoir désigner le commerce d'un autre, même en devinant un numéro.
 */
class VendeurController extends Controller
{
    public function __construct(
        private PasseCommande $passe,
        private Photos $photos,
    ) {}

    // ── Ouvrir une boutique ──────────────────────────────────────────────────

    public function formulaireBoutique(Request $r)
    {
        return $r->user()->boutique
            ? redirect()->route('vendeur.tableau')
            : view('vendeur.ouvrir');
    }

    public function ouvrir(Request $r)
    {
        abort_if((bool) $r->user()->boutique, 409, 'Ce compte a déjà une boutique.');

        $d = $r->validate([
            'nom' => 'required|string|max:160|unique:boutiques,nom',
            'description' => 'nullable|string|max:1000',
            'telephone' => 'required|string|max:20',
            'adresse' => 'required|string|max:200',
            'ville' => 'required|string|max:80',
        ]);

        Boutique::create($d + [
            'utilisateur_id' => $r->user()->id,
            'slug' => Str::slug($d['nom']),
            // Personne ne s'auto-valide : la boutique attend l'administration
            // avant d'apparaître au catalogue.
            'statut' => 'en_attente',
        ]);

        // Le compte devient vendeur s'il ne l'était pas — quelqu'un peut avoir
        // commencé comme client puis décidé de vendre.
        if ($r->user()->role === 'client') {
            $r->user()->update(['role' => 'vendeur']);
        }

        return redirect()->route('vendeur.tableau')->with('ok',
            'Boutique enregistrée. Vos produits resteront invisibles tant que '
            . 'l\'administration ne l\'aura pas validée.');
    }

    // ── Le quotidien ─────────────────────────────────────────────────────────

    public function tableau(Request $r)
    {
        $b = $this->boutique($r);
        $lignes = LigneCommande::where('boutique_id', $b->id);

        return view('vendeur.tableau', [
            'boutique' => $b,
            'chiffres' => [
                'produits' => Produit::where('boutique_id', $b->id)->count(),
                'en_rupture' => Produit::where('boutique_id', $b->id)
                    ->where('actif', true)->where('stock', 0)->count(),
                'a_expedier' => $this->sesCommandes($b)->where('etat', 'en_preparation')->count(),
                'en_route' => $this->sesCommandes($b)
                    ->whereIn('etat', ['expediee', 'en_livraison'])->count(),
                // Ce que ses ventes livrées ont rapporté, port non compris.
                'chiffre_affaires' => (int) (clone $lignes)
                    ->whereHas('commande', fn ($q) => $q->where('etat', 'livree'))
                    ->sum('montant'),
                'articles_vendus' => (int) (clone $lignes)
                    ->whereHas('commande', fn ($q) => $q->where('etat', 'livree'))
                    ->sum('quantite'),
                'refusees' => $this->sesCommandes($b)->where('etat', 'refusee')->count(),
            ],
            'aTraiter' => $this->sesCommandes($b)
                ->whereIn('etat', ['en_preparation', 'expediee', 'en_livraison'])
                ->with('lignes')->orderBy('id')->limit(10)->get(),
        ]);
    }

    // ── Les produits ─────────────────────────────────────────────────────────

    public function produits(Request $r)
    {
        $b = $this->boutique($r);

        return view('vendeur.produits', [
            'boutique' => $b,
            'produits' => Produit::with('categorie', 'photos')
                ->where('boutique_id', $b->id)->orderByDesc('id')->paginate(20),
        ]);
    }

    /** La fiche d'un produit du vendeur : ses champs, et ses photos. */
    public function editerProduit(Request $r, Produit $produit)
    {
        $this->verifierProduit($r, $produit);

        return view('vendeur.produit', [
            'boutique' => $this->boutique($r),
            'produit' => $produit->load('photos'),
            'categories' => Categorie::whereNotNull('parente_id')
                ->with('parente')->orderBy('parente_id')->orderBy('rang')->get(),
        ]);
    }

    public function nouveauProduit(Request $r)
    {
        return view('vendeur.produit', [
            'boutique' => $this->boutique($r),
            'produit' => null,
            'categories' => Categorie::whereNotNull('parente_id')
                ->with('parente')->orderBy('parente_id')->orderBy('rang')->get(),
        ]);
    }

    public function publier(Request $r)
    {
        $b = $this->boutique($r);
        $d = $this->valider($r);

        Produit::create($d + [
            'boutique_id' => $b->id,
            'slug' => Str::slug($d['nom']) . '-' . $b->id . '-' . Str::random(4),
        ]);

        return redirect()->route('vendeur.produits')->with('ok', 'Produit publié.');
    }

    public function modifier(Request $r, Produit $produit)
    {
        $this->verifierProduit($r, $produit);
        $produit->update($this->valider($r));

        return back()->with('ok', 'Produit mis à jour.');
    }

    /**
     * Retirer un produit de la vente sans l'effacer.
     *
     * Le supprimer emporterait les lignes de commande qui le désignent, et donc
     * l'historique de ce qui a été vendu.
     */
    public function basculer(Request $r, Produit $produit)
    {
        $this->verifierProduit($r, $produit);
        $produit->update(['actif' => ! $produit->actif]);

        return back()->with('ok', $produit->actif
            ? 'Produit remis en vente.' : 'Produit retiré de la vente.');
    }

    // ── Les photos ───────────────────────────────────────────────────────────

    /**
     * Téléverser les photos d'un produit.
     *
     * Plusieurs à la fois : un vendeur qui photographie sa marchandise le fait
     * en une passe, et lui faire recommencer huit fois le formulaire est le
     * meilleur moyen qu'il n'en mette aucune.
     */
    public function televerser(Request $r, Produit $produit)
    {
        $this->verifierProduit($r, $produit);

        $r->validate([
            'photos' => 'required|array|min:1|max:8',
            'photos.*' => 'required|file',
        ], [
            'photos.required' => 'Choisissez au moins une image.',
        ]);

        $posees = 0;
        $refus = [];

        foreach ($r->file('photos') as $fichier) {
            try {
                $this->photos->ajouter($produit, $fichier);
                $posees++;
            } catch (RuntimeException $e) {
                // Une image refusée n'empêche pas les autres de passer : le
                // vendeur voit ce qui n'est pas passé et pourquoi.
                $refus[] = $fichier->getClientOriginalName() . ' — ' . $e->getMessage();
            }
        }

        $message = $posees > 0
            ? $posees . ' photo(s) ajoutée(s).'
            : 'Aucune photo ajoutée.';

        return $refus === []
            ? back()->with('ok', $message)
            : back()->with('erreur', $message . ' ' . implode(' ', $refus));
    }

    public function supprimerPhoto(Request $r, PhotoProduit $photo)
    {
        $this->verifierProduit($r, $photo->produit);
        $photo->delete();

        return back()->with('ok', 'Photo supprimée.');
    }

    // ── Les commandes ────────────────────────────────────────────────────────

    public function commandes(Request $r)
    {
        $b = $this->boutique($r);
        $etat = $r->query('etat');

        $requete = $this->sesCommandes($b)->with('lignes', 'utilisateur');

        if ($etat && array_key_exists($etat, Commande::SUITES)) {
            $requete->where('etat', $etat);
        }

        return view('vendeur.commandes', [
            'boutique' => $b,
            'etatFiltre' => $etat,
            'liste' => $requete->orderByDesc('id')->paginate(20)->withQueryString(),
            'parEtat' => $this->sesCommandes($b)
                ->selectRaw('etat, count(*) as nombre')->groupBy('etat')
                ->pluck('nombre', 'etat'),
        ]);
    }

    public function expedier(Request $r, Commande $commande)
    {
        $this->verifierCommande($r, $commande);

        try {
            $this->passe->expedier($commande);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('ok', 'Commande expédiée.');
    }

    public function livrer(Request $r, Commande $commande)
    {
        $this->verifierCommande($r, $commande);

        try {
            // Le passage par « en livraison » est implicite quand le vendeur
            // livre lui-même : il annonce la remise, pas le départ du camion.
            if ($commande->etat === 'expediee') {
                $commande = $this->passe->mettreEnLivraison($commande);
            }
            $this->passe->livrer($commande);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('ok', 'Commande livrée et réglée.');
    }

    /**
     * Le client a refusé le colis à la porte.
     *
     * C'est le risque propre au paiement à la livraison, et il n'était
     * enregistrable nulle part : l'état existait dans la machine, aucun écran
     * ne pouvait l'atteindre, et le taux de refus des tableaux de bord affichait
     * donc zéro quoi qu'il arrive.
     *
     * Distinct d'une annulation : la tournée a eu lieu, elle a coûté. La
     * marchandise, elle, rentre et retourne en stock.
     */
    public function refuser(Request $r, Commande $commande)
    {
        $this->verifierCommande($r, $commande);

        $d = $r->validate(['motif' => 'required|string|max:200'], [
            'motif.required' => 'Dites pourquoi le colis a été refusé : '
                . 'c\'est ce qui permet de savoir si le problème se répète.',
        ]);

        try {
            $this->passe->refuser($commande, $d['motif']);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('ok', 'Refus enregistré. La marchandise revient en stock.');
    }

    /**
     * Le client rend une commande déjà livrée.
     *
     * La marchandise revient, donc le stock aussi. Le compteur de ventes
     * redescend : une vente rendue n'est pas une vente.
     */
    public function retourner(Request $r, Commande $commande)
    {
        $this->verifierCommande($r, $commande);

        $d = $r->validate(['motif' => 'required|string|max:200']);

        try {
            $this->passe->retourner($commande, $d['motif']);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('ok', 'Retour enregistré. La marchandise revient en stock.');
    }

    // ── La boutique ──────────────────────────────────────────────────────────

    public function maBoutique(Request $r)
    {
        return view('vendeur.boutique', ['boutique' => $this->boutique($r)]);
    }

    public function majBoutique(Request $r)
    {
        $b = $this->boutique($r);

        $b->update($r->validate([
            'description' => 'nullable|string|max:1000',
            'telephone' => 'required|string|max:20',
            'adresse' => 'required|string|max:200',
            'ville' => 'required|string|max:80',
        ]));

        return back()->with('ok', 'Boutique mise à jour.');
    }

    // ── Garde-fous ───────────────────────────────────────────────────────────

    private function boutique(Request $r): Boutique
    {
        $b = $r->user()->boutique;
        abort_unless($b, 403, 'Ce compte n\'a pas de boutique.');

        return $b;
    }

    /** Les commandes qui contiennent au moins un produit de cette boutique. */
    private function sesCommandes(Boutique $b)
    {
        return Commande::whereHas('lignes', fn ($q) => $q->where('boutique_id', $b->id));
    }

    private function verifierProduit(Request $r, Produit $produit): void
    {
        abort_unless($produit->boutique_id === $r->user()->boutique?->id, 403);
    }

    private function verifierCommande(Request $r, Commande $commande): void
    {
        abort_unless(
            $commande->lignes()->where('boutique_id', $r->user()->boutique?->id)->exists(),
            403
        );
    }

    private function valider(Request $r): array
    {
        return $r->validate([
            'categorie_id' => 'required|exists:categories,id',
            'nom' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'marque' => 'nullable|string|max:80',
            'prix' => 'required|integer|min:100',
            // Le prix barré doit être supérieur, sinon la remise affichée
            // serait négative — ou nulle, et donc mensongère.
            'prix_barre' => 'nullable|integer|gt:prix',
            'stock' => 'required|integer|min:0',
            'dessin' => 'nullable|string|max:40',
        ], [
            'prix_barre.gt' => 'Le prix barré doit être supérieur au prix de vente.',
        ]);
    }
}
