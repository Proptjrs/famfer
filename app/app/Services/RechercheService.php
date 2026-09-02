<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Offre;
use Illuminate\Support\Collection;

/**
 * La recherche d'un article, puis des offres qui le proposent.
 *
 * Deux temps, et c'est délibéré. L'acheteur cherche d'abord une marchandise —
 * « fer 10 » — et le système lui répond par un article du référentiel. Ce n'est
 * qu'ensuite qu'apparaissent les vendeurs qui le tiennent. Chercher directement
 * dans les offres mêlerait les articles et rendrait toute comparaison illisible.
 *
 * Le vocabulaire du chantier n'est pas celui du catalogue : personne ne demande
 * un « fer à béton haute adhérence T10 ». Les synonymes portent donc les mots
 * réellement employés, et la recherche les interroge au même titre que la
 * désignation officielle.
 */
class RechercheService
{
    public function __construct(private GeolocService $geoloc) {}

    /**
     * Les articles qui correspondent à ce que l'acheteur a tapé.
     *
     * @return Collection<int, Article>
     */
    public function articles(string $recherche, int $limite = 12): Collection
    {
        $termes = $this->normaliser($recherche);

        return $termes === '' ? collect() : $this->requete($termes)->limit($limite)->get();
    }

    /**
     * La même recherche, paginée et filtrable.
     *
     * Douze résultats suffisaient à onze articles ; avec un catalogue qui grandit,
     * un acheteur doit pouvoir restreindre par famille et par prix, et parcourir
     * les pages. C'est le minimum de toute place de marché.
     */
    public function paginer(string $recherche, ?int $familleId = null, ?int $prixMax = null, int $parPage = 12)
    {
        $termes = $this->normaliser($recherche);

        $requete = $termes === ''
            ? Article::query()->where('actif', true)
            : $this->requete($termes);

        if ($familleId) {
            $requete->where('famille_id', $familleId);
        }

        // Le filtre de prix porte sur la meilleure offre visible, donc sur une
        // donnée calculée : on ne peut pas l'écrire en SQL sans dupliquer la
        // règle de visibilité. On filtre après coup, sur la page obtenue.
        $page = $requete->with('famille')->orderBy('id')->paginate($parPage)->withQueryString();

        if ($prixMax) {
            $page->setCollection($page->getCollection()->filter(function (Article $a) use ($prixMax) {
                $meilleure = $a->offresVisibles()->sortBy(fn ($o) => $o->prix_par_unite)->first();

                return $meilleure && $meilleure->prix_par_unite <= $prixMax;
            })->values());
        }

        return $page;
    }

    private function requete(string $termes)
    {
        $motifs = array_filter(explode(' ', $termes));

        return Article::query()
            ->where('actif', true)
            ->where(function ($q) use ($motifs) {
                foreach ($motifs as $mot) {
                    // Chaque mot doit se retrouver quelque part : « fer 10 »
                    // exige « fer » ET « 10 », sinon toute recherche contenant
                    // « fer » remonterait le catalogue entier.
                    $q->where(function ($sous) use ($mot) {
                        $sous->whereRaw('LOWER(designation) LIKE ?', ["%{$mot}%"])
                             ->orWhereRaw('LOWER(COALESCE(synonymes, \'\')) LIKE ?', ["%{$mot}%"])
                             ->orWhereRaw('LOWER(reference) LIKE ?', ["%{$mot}%"]);
                    });
                }
            });
    }

    /**
     * Les offres d'un article, chez les vendeurs vérifiés seulement.
     *
     * @param  string  $tri  prix | distance | note
     * @return Collection<int, Offre>
     */
    public function offres(Article $article, ?float $lat = null, ?float $lng = null, string $tri = 'prix'): Collection
    {
        $offres = Offre::with('vendeur', 'article.unitesVente')
            ->where('article_id', $article->id)
            ->where('actif', true)
            ->whereHas('vendeur', fn ($q) => $q->where('statut', 'verifie'))
            ->get()
            // Une offre en rupture n'a rien à faire dans une liste de prix :
            // l'acheteur ne peut pas l'acheter.
            ->filter(fn (Offre $o) => $o->disponiblePivot() > 0)
            ->values();

        if ($lat !== null && $lng !== null) {
            $offres->each(function (Offre $o) use ($lat, $lng) {
                $o->distance_km = $this->geoloc->distance(
                    $lat, $lng, $o->vendeur->latitude, $o->vendeur->longitude
                );
            });
        }

        return match ($tri) {
            'distance' => $offres->sortBy(fn (Offre $o) => $o->distance_km ?? INF)->values(),
            'note' => $offres->sortByDesc(fn (Offre $o) => $o->vendeur->note_sur_cent ?? -1)->values(),
            default => $offres->sortBy(fn (Offre $o) => $o->prixParPivot())->values(),
        };
    }

    /**
     * Ramène la frappe de l'acheteur à une forme comparable.
     *
     * « FER À BÉTON T-10 » et « fer a beton t10 » doivent trouver la même chose :
     * on abaisse la casse, on retire les accents, et l'on sépare les lettres des
     * chiffres pour que « T10 » vaille « t 10 ».
     */
    private function normaliser(string $recherche): string
    {
        $s = mb_strtolower(trim($recherche));

        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u',
            'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
