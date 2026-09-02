<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Services\CommandeService;
use App\Services\EvaluationService;
use App\Services\LitigeService;
use App\Services\PaiementService;
use Illuminate\Http\Request;

class AcheteurController extends Controller
{
    public function __construct(
        private CommandeService $commandes,
        private PaiementService $paiements,
        private LitigeService $litiges,
        private EvaluationService $evaluations,
    ) {}

    public function commandes(Request $r)
    {
        $liste = Commande::with('vendeur', 'lignes.offre.article', 'evaluation')
            ->where('acheteur_id', $r->user()->acheteur?->id)
            ->orderByDesc('id')->get();

        return view('acheteur.commandes', compact('liste'));
    }

    /**
     * Le paiement, simulé.
     *
     * En production, l'acheteur part chez l'opérateur et c'est le rappel de
     * celui-ci qui confirme. Ici, on appelle le même service avec une clé
     * d'idempotence forgée : le chemin est identique, seule la source du signal
     * change.
     */
    public function payer(Request $r, Commande $commande)
    {
        $this->verifierProprietaire($r, $commande);

        $this->paiements->traiterRappel(
            // L'opérateur vient de la configuration : c'est lui qui nomme le
            // compte d'argent dans le grand livre, et un semis fait sous
            // « wave » ne doit pas se mélanger à des écritures « om ».
            $commande, config('paiement.operateur'), 'SIM-' . $commande->reference,
            $commande->montant_total, fraisOperateur: (int) round($commande->montant_total * 0.015)
        );

        return back()->with('ok', 'Paiement enregistré. Votre argent est retenu jusqu\'à la livraison.');
    }

    public function confirmerReception(Request $r, Commande $commande)
    {
        $this->verifierProprietaire($r, $commande);
        $this->commandes->confirmerReception($commande, $r->user()->id);

        return back()->with('ok', 'Réception confirmée. Le vendeur va être payé.');
    }

    public function ouvrirLitige(Request $r, Commande $commande)
    {
        $this->verifierProprietaire($r, $commande);

        $d = $r->validate([
            'motif' => 'required|in:non_livre,quantite_manquante,article_non_conforme,marchandise_abimee,autre',
            'description' => 'required|string|min:10|max:2000',
        ]);

        try {
            $this->litiges->ouvrir($commande, $r->user(), $d['motif'], $d['description']);
        } catch (\RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('ok', 'Litige ouvert. Le reversement au vendeur est gelé.');
    }

    /**
     * Noter le vendeur, une fois la marchandise reçue.
     *
     * Le service refuse tout le reste — note hors barème, commande non reçue,
     * seconde note. Le contrôleur n'a donc rien à réinventer : il transmet et
     * rapporte le refus.
     */
    public function noter(Request $r, Commande $commande)
    {
        $this->verifierProprietaire($r, $commande);

        $d = $r->validate([
            'note' => 'required|integer|min:1|max:5',
            'commentaire' => 'nullable|string|max:1000',
        ]);

        try {
            $this->evaluations->noter($commande, $d['note'], $d['commentaire'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('ok', 'Merci. Votre avis est publié sur la fiche du vendeur.');
    }

    private function verifierProprietaire(Request $r, Commande $commande): void
    {
        abort_unless($commande->acheteur_id === $r->user()->acheteur?->id, 403);
    }
}
