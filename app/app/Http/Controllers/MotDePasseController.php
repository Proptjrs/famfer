<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as Broker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Le mot de passe oublié.
 *
 * Sans ce chemin, un commerçant qui perd son mot de passe perd son stock, ses
 * commandes en cours et l'argent qui l'attend au séquestre : il n'y a aucune
 * autre porte. C'est pour cela qu'il figure ici et non dans les finitions.
 *
 * Le jeton de réinitialisation vit une heure et ne sert qu'une fois — c'est le
 * courtier de Laravel qui s'en charge, et il vaut mieux que tout ce que
 * j'écrirais à la main.
 */
class MotDePasseController extends Controller
{
    public function formulaireDemande()
    {
        return view('auth.oubli');
    }

    public function envoyer(Request $r)
    {
        $r->validate(['email' => 'required|email:rfc']);

        Broker::sendResetLink($r->only('email'));

        // La réponse est la même que l'adresse existe ou non : dire « ce compte
        // n'existe pas » permettrait d'énumérer les inscrits de la plateforme.
        return back()->with('ok',
            'Si un compte existe pour cette adresse, un lien de réinitialisation vient d\'y être envoyé. '
            . 'Il est valable une heure.');
    }

    public function formulaireReinitialisation(Request $r, string $token)
    {
        return view('auth.reinitialisation', ['token' => $token, 'email' => $r->query('email')]);
    }

    public function reinitialiser(Request $r)
    {
        $r->validate([
            'token' => 'required',
            'email' => 'required|email:rfc',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $etat = Broker::reset(
            $r->only('email', 'password', 'password_confirmation', 'token'),
            function ($utilisateur, $motDePasse) {
                $utilisateur->forceFill([
                    'password' => $motDePasse,
                    // Le jeton « se souvenir de moi » est régénéré : les
                    // sessions restées ouvertes ailleurs tombent avec l'ancien.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($utilisateur));
            }
        );

        return $etat === Broker::PasswordReset
            ? redirect()->route('connexion')->with('ok', 'Mot de passe changé. Connectez-vous.')
            : back()->withInput($r->only('email'))
                ->with('erreur', 'Ce lien n\'est plus valable. Demandez-en un nouveau.');
    }
}
