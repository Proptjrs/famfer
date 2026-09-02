<?php

namespace App\Providers;

use App\Models\Famille;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Les gabarits livrés par Laravel supposent Tailwind, absent d'ici.
        Paginator::defaultView('partials.pagination');

        // Les dates s'affichent en français : « 12 septembre 2026 ».
        Carbon::setLocale('fr');

        // Le menu des familles est le même sur toutes les pages. Le passer
        // depuis chaque contrôleur obligerait à y penser à chaque nouvelle
        // page, et la barre disparaîtrait le jour où l'on oublie.
        //
        // La requête est mise en cache : le référentiel change quelques fois
        // par an, il n'a pas à être relu à chaque affichage.
        View::composer('layouts.app', function ($vue) {
            $vue->with('famillesDuMenu', Cache::remember(
                'menu.familles', now()->addHour(),
                fn () => Famille::whereNull('parente_id')
                    ->withCount(['articles' => fn ($q) => $q->where('actif', true)])
                    ->orderBy('rang')->get()
            ));
        });
    }
}
