<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Le compte : coordonnées, mot de passe, rôle.
 *
 * Pouvoir changer son mot de passe n'est pas un confort : c'est la seule
 * réponse d'un commerçant dont le compte a été pris.
 */
class CompteController extends Controller
{
    public function profil(Request $r)
    {
        return view('compte', [
            'utilisateur' => $r->user(),
            'boutique' => $r->user()->boutique,
        ]);
    }

    public function majProfil(Request $r)
    {
        $u = $r->user();

        $d = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|unique:users,email,' . $u->id,
            'telephone' => 'required|string|max:20',
        ]);

        $u->update($d);

        return back()->with('ok', 'Vos informations sont à jour.');
    }

    public function majMotDePasse(Request $r)
    {
        $d = $r->validate([
            'actuel' => 'required',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // L'ancien est exigé : sans lui, une session laissée ouverte sur un
        // téléphone posé au comptoir suffirait à s'emparer du compte.
        if (! Hash::check($d['actuel'], $r->user()->password)) {
            return back()->with('erreur', 'Le mot de passe actuel est faux.');
        }

        $r->user()->update(['password' => $d['password']]);
        auth()->logoutOtherDevices($d['password']);

        return back()->with('ok', 'Mot de passe changé. Les autres appareils ont été déconnectés.');
    }
}
