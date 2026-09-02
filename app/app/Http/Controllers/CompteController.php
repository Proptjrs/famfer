<?php

namespace App\Http\Controllers;

use App\Models\Acheteur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Le compte de l'utilisateur : ses coordonnées, son mot de passe.
 *
 * Sans cette page, un acheteur qui déménageait de chantier n'avait aucun moyen
 * de corriger son adresse de livraison, et un mot de passe compromis restait en
 * place. Ce n'est pas un confort : sur une plateforme qui manipule de l'argent,
 * pouvoir changer son mot de passe est une mesure de sécurité.
 */
class CompteController extends Controller
{
    public function profil(Request $r)
    {
        return view('compte.profil', [
            'utilisateur' => $r->user(),
            'acheteur' => $r->user()->acheteur,
        ]);
    }

    public function majProfil(Request $r)
    {
        $u = $r->user();

        $d = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|unique:users,email,' . $u->id,
            'telephone' => 'required|string|max:20',
            'genre' => 'required|in:particulier,chantier,entreprise',
            'adresse_defaut' => 'nullable|string|max:200',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $u->update(['name' => $d['name'], 'email' => $d['email']]);

        // Un compte peut avoir été créé avant la fiche acheteur : on la crée
        // plutôt que d'échouer, sinon le formulaire serait inutilisable.
        Acheteur::updateOrCreate(
            ['utilisateur_id' => $u->id],
            [
                'genre' => $d['genre'],
                'telephone' => $d['telephone'],
                'adresse_defaut' => $d['adresse_defaut'] ?? null,
                'latitude' => $d['latitude'] ?? null,
                'longitude' => $d['longitude'] ?? null,
            ]
        );

        return back()->with('ok', 'Vos informations sont à jour.');
    }

    /**
     * Changer de mot de passe.
     *
     * L'ancien est exigé : sans lui, une session laissée ouverte sur un
     * téléphone posé au comptoir suffirait à s'emparer du compte — et donc de
     * l'argent qui y transite.
     */
    public function majMotDePasse(Request $r)
    {
        $d = $r->validate([
            'actuel' => 'required',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($d['actuel'], $r->user()->password)) {
            return back()->with('erreur', 'Le mot de passe actuel est faux.');
        }

        $r->user()->update(['password' => $d['password']]);

        // Les autres sessions tombent : si quelqu'un était entré, il sort.
        auth()->logoutOtherDevices($d['password']);

        return back()->with('ok', 'Mot de passe changé. Les autres appareils ont été déconnectés.');
    }
}
