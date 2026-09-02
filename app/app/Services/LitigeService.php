<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Litige;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Les litiges, et leur arbitrage.
 *
 * Un litige ne s'ouvre que sur une commande dont l'argent est encore retenu.
 * Une fois reversé au vendeur, la plateforme n'a plus les moyens de trancher :
 * elle devrait réclamer, ce qui n'est pas la même chose.
 */
class LitigeService
{
    /** États où l'argent est encore en séquestre, donc où un litige a une prise. */
    private const ETATS_ARBITRABLES = ['prete', 'en_livraison', 'receptionnee'];

    public function __construct(
        private GrandLivre $livre,
        private CommandeService $commandes,
    ) {}

    /**
     * @throws RuntimeException si la commande n'est plus arbitrable, ou si un
     *                          litige y est déjà ouvert
     */
    public function ouvrir(Commande $commande, User $auteur, string $motif, string $description): Litige
    {
        // L'ordre compte : ouvrir un litige fait passer la commande à
        // « en_litige », qui n'est pas un état arbitrable. Contrôler l'état
        // d'abord renverrait donc « l'argent n'est plus retenu » à quelqu'un qui
        // veut seulement signaler un second problème — un message faux.
        if (Litige::where('commande_id', $commande->id)->where('etat', 'ouvert')->exists()) {
            throw new RuntimeException('Un litige est déjà ouvert sur cette commande.');
        }

        if (! in_array($commande->etat, self::ETATS_ARBITRABLES, true)) {
            throw new RuntimeException(sprintf(
                'Une commande « %s » ne peut plus faire l\'objet d\'un litige : l\'argent n\'est plus retenu.',
                $commande->etat
            ));
        }

        return DB::transaction(function () use ($commande, $auteur, $motif, $description) {
            $litige = Litige::create([
                'commande_id' => $commande->id,
                'ouvert_par' => $auteur->id,
                'motif' => $motif,
                'description' => $description,
                'etat' => 'ouvert',
            ]);

            // La commande passe en litige : la machine à états interdit alors de
            // la solder tant que rien n'est tranché.
            if ($commande->peutAllerVers('en_litige')) {
                $this->commandes->passerEnLitige($commande, $auteur->id);
            }

            return $litige;
        });
    }

    /**
     * L'arbitre donne raison à l'acheteur : remboursement intégral.
     *
     * Aucune commission n'est perçue. La plateforme perd les frais de
     * l'opérateur : c'est le coût du service, et il doit se voir dans les
     * comptes plutôt que disparaître.
     */
    public function trancherPourAcheteur(Litige $litige, User $arbitre, string $decision): Litige
    {
        return DB::transaction(function () use ($litige, $arbitre, $decision) {
            $commande = $litige->commande;

            $this->livre->rembourser($commande);
            $this->commandes->rembourser($commande, $decision, $arbitre->id);

            $litige->update([
                'etat' => 'tranche_acheteur',
                'decision' => $decision,
                'arbitre_id' => $arbitre->id,
                'tranche_le' => now(),
            ]);

            return $litige->fresh();
        });
    }

    /** L'arbitre donne raison au vendeur : la commande se solde normalement. */
    public function trancherPourVendeur(Litige $litige, User $arbitre, string $decision): Litige
    {
        return DB::transaction(function () use ($litige, $arbitre, $decision) {
            $commande = $litige->commande;

            $this->livre->solder($commande);
            $this->commandes->marquerSoldee($commande, $arbitre->id);

            $litige->update([
                'etat' => 'tranche_vendeur',
                'decision' => $decision,
                'arbitre_id' => $arbitre->id,
                'tranche_le' => now(),
            ]);

            return $litige->fresh();
        });
    }
}
