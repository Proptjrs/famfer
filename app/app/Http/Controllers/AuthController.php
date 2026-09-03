<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function formulaireConnexion()
    {
        return view('auth.connexion');
    }

    public function connecter(Request $r)
    {
        $donnees = $r->validate(['email' => 'required|email:rfc', 'password' => 'required']);

        if (! Auth::attempt($donnees, $r->boolean('memoriser'))) {
            // Un seul message pour les deux cas : dire « cette adresse n'existe
            // pas » révélerait qui est inscrit sur la plateforme.
            throw ValidationException::withMessages([
                'email' => 'Ces identifiants ne correspondent à aucun compte.',
            ]);
        }

        $r->session()->regenerate();

        return redirect()->intended($this->accueilDuRole($r->user()));
    }

    public function formulaireInscription()
    {
        // Qui arrive par « Vendez sur FamFer » a déjà répondu à la question :
        // on la lui présente cochée plutôt que de la reposer à zéro.
        $intention = session('url.intended', '');
        $roleParDefaut = str_contains($intention, 'ouvrir-boutique') ? 'vendeur' : 'client';

        return view('auth.inscription', compact('roleParDefaut'));
    }

    public function inscrire(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|unique:users,email',
            'telephone' => 'required|string|max:20',
            'password' => 'required|min:8|confirmed',
            // Ce que la personne vient faire : l'acteur se décide ici, et
            // nulle part ailleurs.
            'role' => 'required|in:client,vendeur',
        ]);

        $u = User::create([
            'name' => $d['name'], 'email' => $d['email'],
            'telephone' => $d['telephone'], 'password' => $d['password'],
            'role' => $d['role'],
        ]);

        Auth::login($u);
        $r->session()->regenerate();

        if ($d['role'] === 'vendeur') {
            return redirect()->route('vendeur.ouvrir')->with('ok',
                'Bienvenue ' . $u->name . '. Décrivez maintenant votre boutique : '
                . 'elle sera validée avant d\'apparaître au catalogue.');
        }

        return redirect()->intended(route('accueil'))
            ->with('ok', 'Bienvenue ' . $u->name . '.');
    }

    public function deconnecter(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('accueil');
    }

    /**
     * La page d'arrivée dépend de ce qu'on est venu faire.
     *
     * Un commerçant qui se connecte vient traiter ses commandes, pas acheter ;
     * l'administration ne vient jamais pour acheter.
     */
    private function accueilDuRole(User $u): string
    {
        return match (true) {
            $u->estAdmin() => route('admin.tableau'),
            $u->estVendeur() => route('vendeur.tableau'),
            default => route('accueil'),
        };
    }
}
