<?php

namespace App\Providers;

use App\Models\Categorie;
use App\Models\Commande;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Les gabarits livrés par Laravel supposent Tailwind, absent d'ici.
        Paginator::defaultView('partials.pagination');

        Carbon::setLocale('fr');

        // La barre des rayons est la même partout. La passer depuis chaque
        // contrôleur obligerait à y penser à chaque nouvelle page, et elle
        // disparaîtrait le jour où on l'oublie.
        View::composer('layouts.app', function ($vue) {
            $vue->with('rayonsDuMenu', Cache::remember(
                'menu.rayons', now()->addHour(),
                fn () => Categorie::rayonsAvecCompte()
            ));

            // Le nombre de litiges ouverts, sur la pastille du menu. Un dossier
            // en attente d'arbitrage bloque une commission et laisse deux
            // parties sans reponse : il ne doit pas falloir ouvrir une page
            // pour s'apercevoir qu'il existe.
            $vue->with('litigesOuverts', auth()->check() && auth()->user()->estAdmin()
                ? Commande::where('etat', 'litige')->count()
                : 0);
        });
    }
}
