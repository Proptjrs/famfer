<?php

namespace App\Http\Controllers;

use App\Models\Litige;
use App\Models\Vendeur;
use App\Notifications\DecisionInscription;
use App\Services\LitigeService;
use App\Services\PilotageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function __construct(
        private PilotageService $pilotage,
        private LitigeService $litiges,
    ) {}

    public function tableau()
    {
        return view('admin.tableau', [
            'chiffres' => $this->pilotage->pourLaPlateforme(),
            'aVerifier' => Vendeur::with('utilisateur')->where('statut', 'en_attente')->get(),
            'litiges' => Litige::with('commande.vendeur', 'auteur')->where('etat', 'ouvert')->get(),
        ]);
    }

    public function verifier(Request $r, Vendeur $vendeur)
    {
        $vendeur->update([
            'statut' => 'verifie', 'verifie_le' => now(), 'verifie_par' => $r->user()->id,
        ]);

        $this->prevenir($vendeur, true);

        return back()->with('ok', $vendeur->raison_sociale . ' est vérifiée et visible des acheteurs.');
    }

    public function refuser(Request $r, Vendeur $vendeur)
    {
        $d = $r->validate(['motif' => 'required|string|max:200']);
        $vendeur->update(['statut' => 'suspendu', 'motif_suspension' => $d['motif']]);

        $this->prevenir($vendeur, false);

        return back()->with('ok', 'Inscription refusée. Le demandeur en est informé.');
    }

    /**
     * Informe le demandeur de la décision.
     *
     * Une demande laissée sans réponse est ce qui décourage le plus sûrement une
     * quincaillerie de revenir. L'échec d'envoi, lui, ne remet pas la décision
     * en cause : elle est prise et enregistrée. On le journalise, et
     * l'administration passe à la suivante.
     */
    private function prevenir(Vendeur $vendeur, bool $acceptee): void
    {
        try {
            $vendeur->utilisateur->notify(new DecisionInscription($vendeur, $acceptee));
        } catch (\Throwable $e) {
            Log::warning('Décision d\'inscription non notifiée', [
                'vendeur' => $vendeur->id, 'erreur' => $e->getMessage(),
            ]);
        }
    }

    public function trancher(Request $r, Litige $litige)
    {
        $d = $r->validate([
            'sens' => 'required|in:acheteur,vendeur',
            'decision' => 'required|string|min:10|max:2000',
        ]);

        $d['sens'] === 'acheteur'
            ? $this->litiges->trancherPourAcheteur($litige, $r->user(), $d['decision'])
            : $this->litiges->trancherPourVendeur($litige, $r->user(), $d['decision']);

        return back()->with('ok', 'Litige tranché.');
    }
}
