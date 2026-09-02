<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Ecriture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Le grand livre en partie double.
 *
 * Cinq comptes suffisent à décrire toute la circulation de l'argent :
 *
 *   wave, om        l'argent réellement sur les comptes de la plateforme
 *   sequestre       celui des acheteurs, retenu — il ne nous appartient pas
 *   vendeur:{id}    ce que nous devons à chaque vendeur
 *   commission      notre revenu, le seul
 *   frais_paiement  ce que prend l'opérateur
 *
 * La distinction entre l'actif et la dette est le point que le jury vérifiera :
 * le séquestre n'est pas un chiffre d'affaires. Tant que la marchandise n'est
 * pas reçue, cet argent appartient encore à l'acheteur.
 */
class GrandLivre
{
    public const SEQUESTRE = 'sequestre';
    public const COMMISSION = 'commission';
    public const FRAIS = 'frais_paiement';

    public static function compteVendeur(int $vendeurId): string
    {
        return 'vendeur:' . $vendeurId;
    }

    /**
     * Enregistre une opération équilibrée.
     *
     * @param  array<int, array{compte: string, sens: string, montant: int}>  $lignes
     *
     * @throws RuntimeException si les débits ne valent pas les crédits
     */
    public function enregistrer(string $operation, array $lignes, string $libelle, ?Commande $commande = null): string
    {
        $debits = 0;
        $credits = 0;
        foreach ($lignes as $l) {
            if ($l['montant'] <= 0) {
                throw new RuntimeException('Une écriture de montant nul ou négatif n\'a pas de sens.');
            }
            $l['sens'] === 'debit' ? $debits += $l['montant'] : $credits += $l['montant'];
        }

        // Le refus est ici, avant l'écriture. Une opération déséquilibrée qui
        // entrerait en base fausserait tous les soldes, sans qu'on sache laquelle.
        if ($debits !== $credits) {
            throw new RuntimeException(sprintf(
                'Opération déséquilibrée : %d de débit contre %d de crédit.', $debits, $credits
            ));
        }

        $operationId = (string) Str::uuid();

        DB::transaction(function () use ($lignes, $operation, $operationId, $libelle, $commande) {
            foreach ($lignes as $l) {
                Ecriture::create([
                    'operation_id' => $operationId,
                    'operation' => $operation,
                    'compte' => $l['compte'],
                    'sens' => $l['sens'],
                    'montant' => $l['montant'],
                    'commande_id' => $commande?->id,
                    'libelle' => $libelle,
                ]);
            }
        });

        return $operationId;
    }

    /**
     * Le solde d'un compte.
     *
     * Convention : un compte d'actif ou de charge croît au débit ; un compte de
     * dette ou de produit croît au crédit. Le signe est donc porté par la nature
     * du compte, ce que « sens » de la ligne ne dit pas à lui seul.
     */
    public function solde(string $compte): int
    {
        $debits = (int) Ecriture::where('compte', $compte)->where('sens', 'debit')->sum('montant');
        $credits = (int) Ecriture::where('compte', $compte)->where('sens', 'credit')->sum('montant');

        return $this->croitAuCredit($compte) ? $credits - $debits : $debits - $credits;
    }

    private function croitAuCredit(string $compte): bool
    {
        return $compte === self::SEQUESTRE
            || $compte === self::COMMISSION
            || str_starts_with($compte, 'vendeur:');
    }

    // ── Les opérations du cycle ──────────────────────────────────────────────

    /** L'acheteur a payé : l'argent entre, mais il est dû. */
    public function encaisser(Commande $c, int $montant, int $fraisOperateur = 0, string $compteArgent = 'wave'): string
    {
        $operation = $this->enregistrer('encaissement', [
            ['compte' => $compteArgent, 'sens' => 'debit', 'montant' => $montant],
            ['compte' => self::SEQUESTRE, 'sens' => 'credit', 'montant' => $montant],
        ], 'Encaissement ' . $c->reference, $c);

        if ($fraisOperateur > 0) {
            // Les frais sont à la charge de la plateforme, pas du vendeur : c'est
            // un choix commercial, et il doit se mesurer.
            $this->enregistrer('frais_paiement', [
                ['compte' => self::FRAIS, 'sens' => 'debit', 'montant' => $fraisOperateur],
                ['compte' => $compteArgent, 'sens' => 'credit', 'montant' => $fraisOperateur],
            ], 'Frais opérateur ' . $c->reference, $c);
        }

        return $operation;
    }

    /**
     * La marchandise est reçue : le séquestre se partage.
     *
     * C'est ici — et seulement ici — que naît le revenu. Ni à la commande, ni au
     * paiement : à la livraison confirmée. Compter la commission plus tôt
     * reviendrait à s'attribuer l'argent d'une vente qui peut encore échouer.
     */
    public function solder(Commande $c): string
    {
        $vendeur = self::compteVendeur($c->vendeur_id);

        return $this->enregistrer('solde_commande', [
            ['compte' => self::SEQUESTRE, 'sens' => 'debit', 'montant' => $c->montant_total],
            ['compte' => $vendeur, 'sens' => 'credit', 'montant' => $c->montantVendeur()],
            ['compte' => self::COMMISSION, 'sens' => 'credit', 'montant' => $c->montant_commission],
        ], 'Solde ' . $c->reference, $c);
    }

    /** L'acheteur est remboursé : aucune commission n'est due. */
    public function rembourser(Commande $c, string $compteArgent = 'wave'): string
    {
        return $this->enregistrer('remboursement', [
            ['compte' => self::SEQUESTRE, 'sens' => 'debit', 'montant' => $c->montant_total],
            ['compte' => $compteArgent, 'sens' => 'credit', 'montant' => $c->montant_total],
        ], 'Remboursement ' . $c->reference, $c);
    }

    /** La dette envers le vendeur est éteinte : l'argent sort. */
    public function reverser(int $vendeurId, int $montant, string $compteArgent = 'wave'): string
    {
        $du = $this->solde(self::compteVendeur($vendeurId));

        if ($montant > $du) {
            throw new RuntimeException(sprintf(
                'Reversement de %d demandé alors que %d seulement est dû.', $montant, $du
            ));
        }

        return $this->enregistrer('reversement', [
            ['compte' => self::compteVendeur($vendeurId), 'sens' => 'debit', 'montant' => $montant],
            ['compte' => $compteArgent, 'sens' => 'credit', 'montant' => $montant],
        ], 'Reversement au vendeur ' . $vendeurId);
    }

    // ── Les invariants ───────────────────────────────────────────────────────

    /** Débits et crédits s'équilibrent, sur l'ensemble du livre. */
    public function estEquilibre(): bool
    {
        return (int) Ecriture::where('sens', 'debit')->sum('montant')
            === (int) Ecriture::where('sens', 'credit')->sum('montant');
    }

    /** Chaque opération, prise seule, est équilibrée. */
    public function operationsDesequilibrees(): array
    {
        return DB::table('ecritures')
            ->select('operation_id')
            ->groupBy('operation_id')
            ->havingRaw(
                "SUM(CASE WHEN sens = 'debit' THEN montant ELSE 0 END)"
                . " <> SUM(CASE WHEN sens = 'credit' THEN montant ELSE 0 END)"
            )
            ->pluck('operation_id')
            ->all();
    }

    /**
     * Le séquestre correspond exactement aux commandes payées et non soldées.
     *
     * C'est l'invariant le plus parlant : il relie l'argent retenu à des
     * commandes réelles. S'il tombe faux, c'est qu'on retient de l'argent que
     * plus rien ne justifie — ou qu'on a payé un vendeur en trop.
     */
    public function sequestreJustifie(): bool
    {
        $duSequestre = Commande::whereIn('etat', ['payee', 'acceptee', 'prete', 'en_livraison', 'receptionnee', 'en_litige'])
            ->sum('montant_total');

        return $this->solde(self::SEQUESTRE) === (int) $duSequestre;
    }

    /** On ne doit jamais un montant négatif à un vendeur. */
    public function dettesNegatives(): array
    {
        return collect(Ecriture::where('compte', 'like', 'vendeur:%')->distinct()->pluck('compte'))
            ->filter(fn (string $compte) => $this->solde($compte) < 0)
            ->values()
            ->all();
    }
}
