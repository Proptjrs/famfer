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

        return redirect()->intended($this->accueilDuRole($r->user()));
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

        // « intended » aussi à l'inscription : une quincaillerie qui a cliqué
        // sur « Vendre sur FamFer » sans avoir de compte passe par ici, et
        // doit ressortir sur le formulaire de demande — pas sur le catalogue,
        // où elle aurait à retrouver la porte par laquelle elle était entrée.
        return redirect()->intended($this->accueilDuRole($u))
            ->with('ok', 'Bienvenue ' . $u->name . '.');
    }

    /**
     * La page d'arrivée dépend de ce qu'on est venu faire.
     *
     * Tout le monde atterrissait sur le catalogue. Un quincaillier qui se
     * connecte à sept heures du matin vient traiter ses commandes, pas acheter
     * du fer : lui présenter la vitrine lui fait chercher son propre commerce
     * dans le menu. L'administration, elle, ne vient jamais pour acheter.
     *
     * « intended » garde la priorité : quelqu'un qui a cliqué sur « Vendre sur
     * FamFer » avant de se connecter doit arriver sur le formulaire de demande,
     * pas ici.
     */
    private function accueilDuRole(User $utilisateur): string
    {
        if ($utilisateur->est_admin) {
            return route('admin.tableau');
        }

        // Y compris en attente de vérification : c'est là qu'il prépare ses
        // offres, et là qu'on lui dit où en est son dossier.
        if ($utilisateur->vendeur) {
            return route('vendeur.tableau');
        }

        return route('accueil');
    }

    public function deconnecter(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('accueil');
    }
}
