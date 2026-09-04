<?php

namespace App\Http\Controllers;

use App\Models\Adresse;
use App\Models\Commande;
use App\Models\Produit;
use App\Services\Livraison;
use App\Services\Notation;
use App\Services\Panier;
use App\Services\PasseCommande;
use Illuminate\Http\Request;
use RuntimeException;

/** Commander, suivre ses commandes, noter, tenir son carnet d'adresses. */
class ClientController extends Controller
{
    public function __construct(
        private Panier $panier,
        private PasseCommande $passe,
        private Livraison $livraison,
        private Notation $notation,
    ) {}

    // ── Commander ────────────────────────────────────────────────────────────

    public function formulaireCommande(Request $r)
    {
        $contenu = $this->panier->contenu();

        if ($contenu->isEmpty()) {
            return redirect()->route('panier')->with('erreur', 'Votre panier est vide.');
        }

        $sousTotal = $this->panier->sousTotal();
        $adresses = $r->user()->adresses()->orderByDesc('par_defaut')->get();

        return view('commande', [
            'contenu' => $contenu,
            'sousTotal' => $sousTotal,
            'adresses' => $adresses,
            'regions' => Livraison::regions(),
            // Le montant se recalcule au changement d'adresse : le forfait
            // dépend de la région, et le client doit le voir avant de valider.
            'fraisParRegion' => collect(Livraison::regions())
                ->mapWithKeys(fn ($r) => [$r => $this->livraison->frais($r, $sousTotal)]),
            'fraisAutre' => $this->livraison->frais('—', $sousTotal),
        ]);
    }

    public function valider(Request $r)
    {
        $d = $r->validate([
            'adresse_id' => 'nullable|exists:adresses,id',
            'destinataire' => 'required_without:adresse_id|string|max:160',
            'telephone' => 'required_without:adresse_id|string|max:20',
            'region' => 'required_without:adresse_id|string|max:80',
            'ville' => 'required_without:adresse_id|string|max:80',
            'quartier' => 'required_without:adresse_id|string|max:120',
            'repere' => 'nullable|string|max:200',
            // Wave et Orange Money etaient acceptes ici, sans qu'aucun code
            // ne les traite : une commande ainsi payee etait livree, jamais
            // marquee payee, et generait pourtant une commission que le vendeur
            // devait sur un argent qu'il n'avait peut-etre jamais encaisse.
            // Tant qu'aucun contrat marchand n'existe avec l'operateur, la
            // seule valeur acceptee est celle que le logiciel sait mener a
            // terme. Une promesse que l'application ne tient pas coute plus
            // cher qu'une option absente.
            'paiement' => 'required|in:livraison',
        ]);

        $adresse = isset($d['adresse_id'])
            ? $this->adresseDuClient($r, (int) $d['adresse_id'])
            : Adresse::create([
                'utilisateur_id' => $r->user()->id,
                'destinataire' => $d['destinataire'], 'telephone' => $d['telephone'],
                'region' => $d['region'], 'ville' => $d['ville'],
                'quartier' => $d['quartier'], 'repere' => $d['repere'] ?? null,
                'par_defaut' => $r->user()->adresses()->count() === 0,
            ]);

        try {
            $commande = $this->passe->creer($r->user(), $adresse, $d['paiement']);
        } catch (RuntimeException $e) {
            // Stock parti entre-temps, ou panier vidé : on garde le panier et
            // on dit ce qui manque.
            return back()->with('erreur', $e->getMessage());
        }

        return redirect()->route('mes-commandes.detail', $commande)->with('ok',
            'Commande ' . $commande->reference . ' enregistrée. '
            . ($commande->paiement === 'livraison'
                ? 'Vous réglerez ' . number_format($commande->total, 0, ',', ' ')
                  . ' F au livreur.'
                : 'Vous serez contacté pour le règlement.'));
    }

    // ── Suivre ───────────────────────────────────────────────────────────────

    public function commandes(Request $r)
    {
        return view('client.commandes', [
            'liste' => Commande::with('lignes')
                ->where('utilisateur_id', $r->user()->id)
                ->orderByDesc('id')->paginate(10),
        ]);
    }

    public function commande(Request $r, Commande $commande)
    {
        $this->verifier($r, $commande);

        return view('client.commande', [
            'commande' => $commande->load('lignes.produit', 'avis'),
            'aNoter' => $this->notation->aNoter($commande),
        ]);
    }

    public function annuler(Request $r, Commande $commande)
    {
        $this->verifier($r, $commande);

        if (! $commande->annulableParLeClient()) {
            return back()->with('erreur',
                'Cette commande est déjà partie : contactez le vendeur pour un retour.');
        }

        $this->passe->annuler($commande, 'Annulée par le client');

        return back()->with('ok', 'Commande annulée. Le stock est rendu au vendeur.');
    }

    /**
     * Le client déclare avoir reçu et payé.
     *
     * Sa parole vaut celle du vendeur. C'est ce qui empêche un commerçant
     * d'encaisser les espèces puis de déclarer un refus pour garder l'argent
     * sans payer de commission.
     */
    public function confirmer(Request $r, Commande $commande)
    {
        $this->verifier($r, $commande);

        if (! $commande->confirmableParLeClient()) {
            return back()->with('erreur',
                'Cette commande n\'est pas en cours de livraison.');
        }

        $this->passe->confirmerParLeClient($commande);

        return back()->with('ok',
            'Réception confirmée. Vous pouvez maintenant noter les articles reçus.');
    }

    /**
     * Le client conteste ce que le vendeur a déclaré.
     *
     * Deux cas opposés, tous deux réels : un refus qui n'a pas eu lieu — le
     * colis a bien été remis et payé — ou une livraison qui n'a pas eu lieu.
     */
    public function contester(Request $r, Commande $commande)
    {
        $this->verifier($r, $commande);

        if (! $commande->contestableParLeClient()) {
            return back()->with('erreur',
                'Il n\'y a rien à contester sur cette commande.');
        }

        $d = $r->validate(['motif' => 'required|string|min:10|max:300']);

        $this->passe->contester($commande, 'client', $d['motif']);

        return back()->with('ok',
            'Litige ouvert. L\'administration de FamFer va examiner votre dossier.');
    }

    public function noter(Request $r, Commande $commande)
    {
        $this->verifier($r, $commande);

        $d = $r->validate([
            'produit_id' => 'required|exists:produits,id',
            'note' => 'required|integer|min:1|max:5',
            'titre' => 'nullable|string|max:160',
            'commentaire' => 'nullable|string|max:1500',
        ]);

        try {
            $this->notation->noter(
                $commande->load('lignes'), Produit::findOrFail($d['produit_id']),
                $d['note'], $d['titre'] ?? null, $d['commentaire'] ?? null
            );
        } catch (RuntimeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        return back()->with('ok', 'Merci. Votre avis est publié sur la fiche du produit.');
    }

    // ── Le carnet d'adresses ─────────────────────────────────────────────────

    public function adresses(Request $r)
    {
        return view('client.adresses', [
            'adresses' => $r->user()->adresses()->orderByDesc('par_defaut')->get(),
            'regions' => Livraison::regions(),
        ]);
    }

    public function ajouterAdresse(Request $r)
    {
        $d = $r->validate([
            'destinataire' => 'required|string|max:160',
            'telephone' => 'required|string|max:20',
            'region' => 'required|string|max:80',
            'ville' => 'required|string|max:80',
            'quartier' => 'required|string|max:120',
            'repere' => 'nullable|string|max:200',
        ]);

        $premiere = $r->user()->adresses()->count() === 0;

        Adresse::create($d + [
            'utilisateur_id' => $r->user()->id,
            'par_defaut' => $premiere || $r->boolean('par_defaut'),
        ]);

        // Une seule adresse par défaut : la nouvelle prend la place de l'autre.
        if (! $premiere && $r->boolean('par_defaut')) {
            $r->user()->adresses()->where('id', '!=', Adresse::max('id'))
                ->update(['par_defaut' => false]);
        }

        return back()->with('ok', 'Adresse enregistrée.');
    }

    public function supprimerAdresse(Request $r, Adresse $adresse)
    {
        abort_unless($adresse->utilisateur_id === $r->user()->id, 403);
        $adresse->delete();

        return back()->with('ok', 'Adresse supprimée.');
    }

    // ── Garde-fous ───────────────────────────────────────────────────────────

    private function verifier(Request $r, Commande $commande): void
    {
        abort_unless($commande->utilisateur_id === $r->user()->id, 403);
    }

    private function adresseDuClient(Request $r, int $id): Adresse
    {
        $adresse = Adresse::findOrFail($id);
        abort_unless($adresse->utilisateur_id === $r->user()->id, 403);

        return $adresse;
    }
}
