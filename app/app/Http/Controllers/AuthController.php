<?php

namespace App\Http\Controllers;

use App\Models\Acheteur;
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
        $donnees = $r->validate([
            'email' => 'required|email:rfc',
            'password' => 'required',
        ]);

        if (! Auth::attempt($donnees, $r->boolean('memoriser'))) {
            // Un seul message pour les deux cas : dire « cette adresse n'existe
            // pas » révélerait qui est inscrit sur la plateforme.
            throw ValidationException::withMessages([
                'email' => 'Ces identifiants ne correspondent à aucun compte.',
            ]);
        }

        $r->session()->regenerate();

        return redirect()->intended(route('accueil'));
    }

    public function formulaireInscription()
    {
        return view('auth.inscription');
    }

    public function inscrire(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|unique:users,email',
            'telephone' => 'required|string|max:20',
            'genre' => 'required|in:particulier,chantier,entreprise',
            'password' => 'required|min:8|confirmed',
        ]);

        $u = User::create([
            'name' => $d['name'], 'email' => $d['email'], 'password' => $d['password'],
        ]);

        // Tout compte naît acheteur. Devenir vendeur suppose une demande, puis
        // une vérification : on ne s'auto-déclare pas commerçant.
        Acheteur::create([
            'utilisateur_id' => $u->id,
            'genre' => $d['genre'],
            'telephone' => $d['telephone'],
        ]);

        Auth::login($u);
        $r->session()->regenerate();

        return redirect()->route('accueil')->with('ok', 'Bienvenue ' . $u->name . '.');
    }

    public function deconnecter(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('accueil');
    }
}
