<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Commande;
use App\Models\Famille;
use App\Models\Offre;
use App\Models\Reversement;
use App\Models\Vendeur;
use App\Notifications\ChangementCompteVersement;
use App\Services\CommandeService;
use App\Services\ConversionUnites;
use App\Services\GrandLivre;
use App\Services\PilotageService;
use App\Services\ReversementService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VendeurController extends Controller
{
    public function __construct(
        private PilotageService $pilotage,
        private CommandeService $commandes,
        private ReversementService $reversements,
        private StockService $stock,
        private ConversionUnites $conversion,
        private GrandLivre $livre,
    ) {}

    // ── Devenir vendeur ──────────────────────────────────────────────────────

    /**
     * Le formulaire de demande.
     *
     * Personne ne s'auto-déclare commerçant. La fiche part en attente, et
     * l'administration vérifie l'établissement avant qu'il n'apparaisse chez le
     * moindre acheteur. C'est la contrepartie du séquestre : nous encaissons
     * pour le compte de ces gens, nous devons savoir qui ils sont.
     */
    public function formulaireDemande(Request $r)
    {
        return $r->user()->vendeur
            ? redirect()->route('vendeur.tableau')
            : view('vendeur.demande');
    }

    public function demander(Request $r)
    {
        abort_if((bool) $r->user()->vendeur, 409, 'Une demande existe déjà pour ce compte.');

        $d = $r->validate([
            'raison_sociale' => 'required|string|max:160',
            'ninea' => 'nullable|string|max:20|unique:vendeurs,ninea',
            'telephone' => 'required|string|max:20',
            'adresse' => 'required|string|max:200',
            'commune' => 'required|string|max:80',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        Vendeur::create($d + ['utilisateur_id' => $r->user()->id, 'statut' => 'en_attente']);

        return redirect()->route('vendeur.tableau')->with('ok',
            "Demande enregistrée. Vos offres resteront invisibles tant que l'administration "
            . "n'aura pas vérifié votre établissement.");
    }

    // ── Publier un article ───────────────────────────────────────────────────

    /** Le catalogue des articles que ce vendeur ne propose pas encore. */
    public function nouvelleOffre(Request $r)
    {
        $v = $this->vendeur($r);
        $dejaTenus = Offre::where('vendeur_id', $v->id)->pluck('article_id');

        return view('vendeur.nouvelle-offre', [
            'vendeur' => $v,
            'familles' => Famille::with(['articles' => fn ($q) => $q
                ->whereNotIn('id', $dejaTenus)
                ->where('actif', true)
                ->with('unitesVente')
                ->orderBy('id')])
                ->orderBy('rang')->get(),
        ]);
    }

    public function publier(Request $r)
    {
        $v = $this->vendeur($r);

        $d = $r->validate([
            'article_id' => 'required|exists:articles,id',
            'prix_par_unite' => 'required|integer|min:1',
            'unite_affichee' => 'required|string|max:20',
            'delai_preparation_h' => 'required|integer|min:0|max:168',
            'quantite' => 'required|string|max:12',
        ]);

        $article = Article::with('unitesVente')->findOrFail($d['article_id']);

        // L'unité doit appartenir à l'article : sans ce contrôle, un vendeur
        // pourrait annoncer un prix « à la tonne » sur une brouette.
        if (! $article->unitesVente->contains('unite', $d['unite_affichee'])) {
            return back()->withInput()->with('erreur', "Cette unité n'existe pas pour cet article.");
        }

        if (Offre::where('vendeur_id', $v->id)->where('article_id', $article->id)->exists()) {
            return back()->withInput()->with('erreur', 'Vous proposez déjà cet article.');
        }

        try {
            $pivot = $this->conversion->versPivot($article, $d['unite_affichee'], $d['quantite']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('erreur', $e->getMessage());
        }

        $offre = Offre::create([
            'vendeur_id' => $v->id,
            'article_id' => $article->id,
            'prix_par_unite' => $d['prix_par_unite'],
            'unite_affichee' => $d['unite_affichee'],
            'delai_preparation_h' => $d['delai_preparation_h'],
        ]);

        $this->stock->approvisionner($offre, $pivot, $r->user()->id, "Stock initial de l'offre");

        return redirect()->route('vendeur.offres')->with('ok', 'Article publié.');
    }

    /** Changer un prix ne touche pas aux commandes passées : elles l'ont figé. */
    public function modifierOffre(Request $r, Offre $offre)
    {
        $this->verifierProprietaire($r, $offre);

        $offre->update($r->validate([
            'prix_par_unite' => 'required|integer|min:1',
            'delai_preparation_h' => 'required|integer|min:0|max:168',
        ]));

        return back()->with('ok', 'Offre mise à jour.');
    }

    /**
     * Retirer une offre de la vente sans l'effacer.
     *
     * La supprimer emporterait l'historique des mouvements de stock et des
     * commandes passées. On la rend seulement invisible.
     */
    public function basculerOffre(Request $r, Offre $offre)
    {
        $this->verifierProprietaire($r, $offre);
        $offre->update(['actif' => ! $offre->actif]);

        return back()->with('ok', $offre->actif
            ? 'Offre remise en vente.' : 'Offre retirée de la vente.');
    }

    // ── Le quotidien ─────────────────────────────────────────────────────────

    public function tableau(Request $r)
    {
        $v = $this->vendeur($r);

        return view('vendeur.tableau', [
            'vendeur' => $v,
            'chiffres' => $this->pilotage->pourVendeur($v),
            'dormants' => $this->pilotage->dormants($v),
            'aTraiter' => Commande::with('acheteur.utilisateur', 'lignes.offre.article')
                ->where('vendeur_id', $v->id)
                ->whereIn('etat', ['payee', 'acceptee', 'prete'])
                ->orderBy('acceptation_due_le')->get(),
        ]);
    }

    public function offres(Request $r)
    {
        $v = $this->vendeur($r);

        return view('vendeur.offres', [
            'vendeur' => $v,
            'offres' => Offre::with('article.unitesVente')
                ->where('vendeur_id', $v->id)->orderBy('id')->get(),
        ]);
    }

    /** Un arrivage : la marchandise entre par un mouvement, jamais par une saisie directe. */
    public function approvisionner(Request $r, Offre $offre)
    {
        $this->verifierProprietaire($r, $offre);

        $d = $r->validate(['quantite' => 'required|string|max:12', 'unite' => 'required|string|max:20']);

        try {
            $pivot = $this->conversion->versPivot($offre->article, $d['unite'], $d['quantite']);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        $this->stock->approvisionner($offre, $pivot, $r->user()->id, 'Arrivage');

        return back()->with('ok', 'Stock mis à jour.');
    }

    public function accepter(Request $r, Commande $commande)
    {
        $this->verifierCommande($r, $commande);
        $this->commandes->accepter($commande, $r->user()->id);

        return back()->with('ok', 'Commande acceptée.');
    }

    public function refuser(Request $r, Commande $commande)
    {
        $this->verifierCommande($r, $commande);
        $this->commandes->annuler($commande, 'Refusée par le vendeur', $r->user()->id);

        return back()->with('ok', "Commande refusée. L'acheteur est remboursé.");
    }

    public function preparer(Request $r, Commande $commande)
    {
        $this->verifierCommande($r, $commande);
        $this->commandes->marquerPrete($commande, $r->user()->id);

        return back()->with('ok', 'Commande prête.');
    }

    public function remettre(Request $r, Commande $commande)
    {
        $this->verifierCommande($r, $commande);
        $this->commandes->remettre($commande, $r->user()->id);

        return back()->with('ok', 'Marchandise remise. Elle sort du stock.');
    }

    /**
     * Toutes ses commandes, et pas seulement celles à traiter.
     *
     * Le tableau de bord ne montrait que « payée, acceptée, prête » : dès
     * qu'une commande était remise, elle disparaissait de l'écran du vendeur.
     * Il n'avait aucun moyen de retrouver ce qu'il avait vendu la semaine
     * précédente, ni de comprendre pourquoi une commande avait été annulée.
     */
    public function commandes(Request $r)
    {
        $v = $this->vendeur($r);
        $etat = $r->query('etat');

        $requete = Commande::with('acheteur.utilisateur', 'lignes.offre.article', 'evaluation')
            ->where('vendeur_id', $v->id);

        if ($etat && array_key_exists($etat, Commande::TRANSITIONS)) {
            $requete->where('etat', $etat);
        }

        return view('vendeur.commandes', [
            'vendeur' => $v,
            'etatFiltre' => $etat,
            'liste' => $requete->orderByDesc('id')->paginate(20)->withQueryString(),
            // Le compte par état, pour que les onglets disent combien il y a
            // derrière chacun avant même de cliquer.
            'parEtat' => Commande::where('vendeur_id', $v->id)
                ->selectRaw('etat, count(*) as nombre')
                ->groupBy('etat')->pluck('nombre', 'etat'),
        ]);
    }

    /**
     * L'argent : ce qui a été viré, ce qui est en attente, ce qui est gelé.
     *
     * Un commerçant à qui l'on retient les fonds veut savoir combien, depuis
     * quand, et pourquoi. Sans cette page, le séquestre ressemblait à une boîte
     * noire — c'est précisément ce qui fait fuir un vendeur d'une place de marché.
     */
    public function argent(Request $r)
    {
        $v = $this->vendeur($r);

        return view('vendeur.argent', [
            'vendeur' => $v,
            'reversements' => Reversement::where('vendeur_id', $v->id)
                ->orderByDesc('id')->paginate(20),
            // Le solde vient du grand livre, pas d'un cumul de commandes : c'est
            // la même source que celle qui sera virée.
            'solde' => $this->livre->solde('vendeur:' . $v->id),
            'litiges' => $this->reversements->litigesOuverts($v),
            'aSolder' => Commande::where('vendeur_id', $v->id)
                ->where('etat', 'receptionnee')->count(),
            // Le partage entre ce qui revient au vendeur et ce que la
            // plateforme prélève, sur trente jours.
            'chiffres' => $this->pilotage->pourVendeur($v),
        ]);
    }

    /**
     * Le journal de stock d'une offre : chaque gramme s'explique par une ligne.
     *
     * C'est la contrepartie du choix de ne pas tenir un compteur : le stock est
     * la somme du journal, donc le vendeur doit pouvoir lire ce journal. La
     * dernière colonne recalcule le solde ligne à ligne — si elle ne finit pas
     * sur le stock affiché, quelque chose est faux, et cela se voit.
     */
    public function journal(Request $r, Offre $offre)
    {
        $this->verifierProprietaire($r, $offre);

        $mouvements = $offre->mouvements()->orderBy('id')->get();

        $cumul = 0;
        foreach ($mouvements as $m) {
            $cumul += $m->quantite_pivot;
            $m->cumul = $cumul;
        }

        return view('vendeur.journal', [
            'vendeur' => $offre->vendeur,
            'offre' => $offre->load('article'),
            'mouvements' => $mouvements->reverse()->values(),
            'cumul' => $cumul,
        ]);
    }

    /**
     * Où envoyer l'argent de ce vendeur.
     *
     * Un changement de compte de versement est exactement ce qu'un intrus
     * ferait après avoir pris la main sur un compte : on horodate la
     * modification, et le titulaire en est prévenu.
     */
    public function enregistrerVersement(Request $r)
    {
        $v = $this->vendeur($r);

        $d = $r->validate([
            'versement_operateur' => 'required|in:wave,om',
            // Un numéro sénégalais, avec ou sans indicatif ni séparateurs.
            'versement_numero' => ['required', 'string', 'max:20',
                'regex:/^(\+?221)?[\s.-]?(7[05678])[\s.-]?\d{3}[\s.-]?\d{2}[\s.-]?\d{2}$/'],
            'versement_titulaire' => 'required|string|max:160',
        ], [
            'versement_numero.regex' =>
                'Ce numéro ne ressemble pas à un numéro de téléphone sénégalais.',
        ]);

        $ancien = $v->compteDeVersement();

        $v->update($d + ['versement_modifie_le' => now()]);

        if ($ancien !== null && $ancien !== $v->fresh()->compteDeVersement()) {
            try {
                $r->user()->notify(new ChangementCompteVersement($v->fresh(), $ancien));
            } catch (\Throwable $e) {
                Log::warning('Changement de compte de versement non notifié', [
                    'vendeur' => $v->id, 'erreur' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('ok', 'Compte de versement enregistré.');
    }

    /** Le vendeur demande son virement : gelé si un litige est ouvert. */
    public function demanderReversement(Request $r)
    {
        $v = $this->vendeur($r);
        $this->reversements->solderLesCommandesRecues($v);

        try {
            $rev = $this->reversements->preparer($v);
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('ok',
            'Virement de ' . number_format($rev->montant, 0, ',', ' ') . ' F préparé.');
    }

    // ── Garde-fous ───────────────────────────────────────────────────────────

    /**
     * Le vendeur du compte courant.
     *
     * Un acheteur qui tomberait sur ces adresses n'a rien à y faire : sur une
     * place de marché, chacun ne voit que son propre commerce.
     */
    private function vendeur(Request $r): Vendeur
    {
        $v = $r->user()->vendeur;
        abort_unless($v, 403, "Ce compte n'est pas un compte vendeur.");

        return $v;
    }

    private function verifierProprietaire(Request $r, Offre $offre): void
    {
        abort_unless($offre->vendeur_id === $r->user()->vendeur?->id, 403);
    }

    private function verifierCommande(Request $r, Commande $commande): void
    {
        abort_unless($commande->vendeur_id === $r->user()->vendeur?->id, 403);
    }
}
