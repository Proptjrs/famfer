<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve l'administration à ceux qui en portent le rôle.
 *
 * Le groupe « administration/ » n'était gardé que par « auth ». N'importe quel
 * compte connecté — un acheteur, un vendeur concurrent — pouvait donc :
 * vérifier sa propre inscription sans passer par personne, suspendre une autre
 * quincaillerie, et trancher un litige en sa faveur. Ce dernier point déplace
 * de l'argent réel du séquestre vers un compte.
 *
 * Le rôle est porté par la colonne « role » du compte.
 *
 * Une tentative est journalisée plutôt que silencieusement refusée : sur une
 * plateforme qui détient des fonds, savoir qui a essayé d'entrer vaut autant
 * que de l'en empêcher.
 */
class EstAdministrateur
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if ($utilisateur === null || ! $utilisateur->estAdmin()) {
            Log::warning('Accès refusé à l\'administration', [
                'utilisateur' => $utilisateur?->id,
                'email' => $utilisateur?->email,
                'chemin' => $request->path(),
                'ip' => $request->ip(),
            ]);

            abort(403, 'Cet espace est réservé à l\'administration de la plateforme.');
        }

        return $next($request);
    }
}
