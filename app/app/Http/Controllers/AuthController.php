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
        // Qui arrive par « Vendez sur FamFer » a déjà répondu à la question :
        // on ne la lui repose pas à zéro, on présente son choix coché.
        $intention = session('url.intended', '');
        $roleParDefaut = str_contains($intention, 'devenir-vendeur') ? 'vendeur' : 'acheteur';

        return view('auth.inscription', compact('roleParDefaut'));
    }

    public function inscrire(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|unique:users,email',
            'telephone' => 'required|string|max:20',
            'genre' => 'required|in:particulier,chantier,entreprise',
            'password' => 'required|min:8|confirmed',
            // Ce que la personne vient faire. Rien ne le demandait : tout compte
            // naissait acheteur, et une quincaillerie devait ensuite retrouver
            // seule la porte « Vendez sur FamFer ». L'acteur se décide ici.
            'role' => 'required|in:acheteur,vendeur',
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

        // Qui vient vendre part droit au dossier d'établissement. Le laisser
        // sur le catalogue lui ferait chercher la porte par laquelle il vient
        // pourtant d'annoncer qu'il entrait.
        if ($d['role'] === 'vendeur') {
            return redirect()->route('vendeur.demande')->with('ok',
                'Bienvenue ' . $u->name . '. Décrivez maintenant votre établissement : '
                . 'il sera vérifié avant que vos offres n\'apparaissent chez les acheteurs.');
        }

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
