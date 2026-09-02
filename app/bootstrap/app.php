<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Le nom de nos routes est en français ; sans cela, un visiteur non
        // identifié provoque une erreur 500 au lieu d'arriver sur la connexion.
        $middleware->redirectGuestsTo(fn () => route('connexion'));
        $middleware->redirectUsersTo(fn () => route('accueil'));

        // L'opérateur de paiement ne peut pas porter de jeton CSRF : il n'a pas
        // visité de page pour en recevoir un. Sa requête est authentifiée
        // autrement, par la signature que vérifie RappelPaiementController.
        $middleware->validateCsrfTokens(except: ['rappel-paiement/*']);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
