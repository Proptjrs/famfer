<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Evaluation;
use App\Models\Famille;
use App\Models\Offre;
use App\Models\Vendeur;
use App\Services\GeolocService;
use App\Services\RechercheService;
use Illuminate\Http\Request;

class RechercheController extends Controller
{
    public function __construct(
        private RechercheService $recherche,
        private GeolocService $geoloc,
    ) {}

    public function accueil(Request $r)
    {
        $termes = trim((string) $r->query('q', ''));
        $famille = $r->integer('famille') ?: null;
        $prixMax = $r->integer('prix_max') ?: null;
        $filtre = $termes !== '' || $famille || $prixMax;

        $articles = $filtre ? $this->recherche->paginer($termes, $famille, $prixMax) : null;

        // Une page au-delà de la dernière annoncerait « 12 articles » puis
        // « rien ne correspond ». On ramène à la dernière page réelle.
        if ($articles && $articles->isEmpty() && $articles->total() > 0) {
            return redirect($articles->url($articles->lastPage()));
        }

        return view('accueil', [
            'termes' => $termes,
            'famille' => $famille,
            'prixMax' => $prixMax,
            'filtre' => $filtre,
            'articles' => $articles,
            'familles' => Famille::whereNull('parente_id')->orderBy('rang')->get(),
        ]);
    }

    /**
     * La fiche publique d'une quincaillerie.
     *
     * Un acheteur ne compare pas que des prix : il regarde à qui il confie son
     * argent. Sans cette page, la note et le nombre d'avis restaient invisibles,
     * et la confiance qu'on lui demande n'avait aucun appui.
     */
    public function vendeur(Vendeur $vendeur)
    {
        abort_unless($vendeur->estVisible(), 404);

        return view('vendeur-public', [
            'vendeur' => $vendeur,
            // Une maison qui tient tout le catalogue afficherait cinquante
            // cartes d'un bloc : on en montre vingt-quatre, classées par
            // famille, et l'acheteur passe par la recherche pour le reste.
            'offres' => Offre::with('article')
                ->where('vendeur_id', $vendeur->id)
                ->where('actif', true)
                ->get()
                ->filter(fn (Offre $o) => $o->disponiblePivot() > 0)
                ->sortBy(fn (Offre $o) => $o->article->famille_id)
                ->values(),
            'enStock' => Offre::where('vendeur_id', $vendeur->id)
                ->where('actif', true)->count(),
            'avis' => Evaluation::with('commande')
                ->where('vendeur_id', $vendeur->id)
                ->whereNotNull('commentaire')
                ->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    /** Les offres d'un article, chez les vendeurs vérifiés. */
    public function article(Request $r, Article $article)
    {
        $lat = $r->query('lat') !== null ? (float) $r->query('lat') : null;
        $lng = $r->query('lng') !== null ? (float) $r->query('lng') : null;
        $tri = in_array($r->query('tri'), ['prix', 'distance', 'note'], true) ? $r->query('tri') : 'prix';

        $offres = $this->recherche->offres($article, $lat, $lng, $tri);

        foreach ($offres as $o) {
            $o->duree_min = isset($o->distance_km)
                ? $this->geoloc->dureeVoitureMinutes($o->distance_km) : null;
        }

        return view('article', compact('article', 'offres', 'tri', 'lat', 'lng'));
    }
}
