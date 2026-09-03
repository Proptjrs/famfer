<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Litige;
use App\Models\Reversement;
use App\Models\Vendeur;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Le reversement aux vendeurs.
 *
 * On ne reverse jamais au fil de l'eau : on solde d'abord les commandes reçues,
 * ce qui crédite le compte du vendeur au grand livre, puis on lui verse le total
 * dû en un seul virement. Cela réduit les frais et laisse une trace unique.
 *
 * Un litige ouvert gèle la totalité du reversement de ce vendeur. C'est brutal,
 * et c'est voulu : tant que la plateforme détient l'argent, elle peut encore
 * trancher. Une fois versé, il faudrait le réclamer — autant dire rien.
 */
class ReversementService
{
    public function __construct(
        private GrandLivre $livre,
        private CommandeService $commandes,
    ) {}

    /**
     * Solde les commandes reçues d'un vendeur : le séquestre se partage.
     *
     * @return int le nombre de commandes soldées
     */
    public function solderLesCommandesRecues(Vendeur $vendeur): int
    {
        $recues = Commande::where('vendeur_id', $vendeur->id)
            ->where('etat', 'receptionnee')
            ->orderBy('id')
            ->get();

        $soldees = 0;
        foreach ($recues as $commande) {
            if ($this->aUnLitigeOuvert($commande)) {
                continue;
            }

            DB::transaction(function () use ($commande, &$soldees) {
                $this->livre->solder($commande);
                $this->commandes->marquerSoldee($commande);
                $soldees++;
            });
        }

        return $soldees;
    }

    /**
     * Prépare le virement du total dû à un vendeur.
     *
     * @throws RuntimeException si un litige est ouvert, ou si rien n'est dû
     */
    public function preparer(Vendeur $vendeur): Reversement
    {
        if ($this->litigesOuverts($vendeur) > 0) {
            throw new RuntimeException(
                'Un litige est ouvert chez ce vendeur : le reversement est gelé.'
            );
        }

        // Sans destination, pas de virement. L'écriture qui suit éteint la
        // dette envers le vendeur : la passer sans savoir où envoyer l'argent
        // reviendrait à effacer ce qu'on lui doit sans le lui avoir versé, et
        // le grand livre resterait équilibré — la faute serait invisible.
        if (! $vendeur->peutEtreVire()) {
            throw new RuntimeException(
                'Aucun compte de versement enregistré : indiquez où envoyer '
                . 'les fonds avant de demander un virement.'
            );
        }

        $du = $this->livre->solde(GrandLivre::compteVendeur($vendeur->id));

        if ($du <= 0) {
            throw new RuntimeException('Rien n\'est dû à ce vendeur.');
        }

        return DB::transaction(function () use ($vendeur, $du) {
            $reversement = Reversement::create([
                'vendeur_id' => $vendeur->id,
                'montant' => $du,
                'etat' => 'prepare',
            ]);

            // L'écriture part maintenant : la dette est éteinte dès la
            // préparation. Si le virement échoue, on inscrira une écriture
            // inverse — on ne modifiera pas celle-ci.
            $this->livre->reverser($vendeur->id, $du);

            return $reversement;
        });
    }

    /** Le virement est parti : on note sa référence. */
    public function confirmer(Reversement $reversement, string $reference): Reversement
    {
        $reversement->update([
            'etat' => 'envoye',
            'reference_virement' => $reference,
            'envoye_le' => now(),
        ]);

        return $reversement->fresh();
    }

    /**
     * Le virement a échoué : on remet la dette, par une écriture inverse.
     *
     * Corriger en modifiant l'écriture d'origine effacerait la trace de la
     * tentative — et c'est précisément cette trace qu'on demandera si le vendeur
     * conteste.
     */
    public function echouer(Reversement $reversement, string $motif): Reversement
    {
        DB::transaction(function () use ($reversement, $motif) {
            $this->livre->enregistrer('reversement_echoue', [
                ['compte' => 'wave', 'sens' => 'debit', 'montant' => $reversement->montant],
                ['compte' => GrandLivre::compteVendeur($reversement->vendeur_id),
                 'sens' => 'credit', 'montant' => $reversement->montant],
            ], 'Échec du virement : ' . $motif);

            $reversement->update(['etat' => 'echoue']);
        });

        return $reversement->fresh();
    }

    public function litigesOuverts(Vendeur $vendeur): int
    {
        return Litige::where('etat', 'ouvert')
            ->whereHas('commande', fn ($q) => $q->where('vendeur_id', $vendeur->id))
            ->count();
    }

    private function aUnLitigeOuvert(Commande $commande): bool
    {
        return Litige::where('commande_id', $commande->id)->where('etat', 'ouvert')->exists();
    }
}
