<?php

namespace App\Services;

use App\Exceptions\TransitionInterdite;
use App\Models\Acheteur;
use App\Models\Commande;
use App\Models\LigneCommande;
use App\Models\Offre;
use App\Models\TransitionCommande;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Le cycle de vie d'une commande.
 *
 * Aucun état ne se change par affectation : tout passe par ce service, qui
 * vérifie que la transition est permise, l'inscrit au journal, et déclenche ce
 * qu'elle doit déclencher sur le stock. Écrire « $commande->etat = 'soldee' »
 * quelque part dans un contrôleur suffirait à payer un vendeur qui n'a rien
 * livré ; c'est pourquoi la vérification est ici, et nulle part ailleurs.
 */
class CommandeService
{
    /** Délais, en minutes. Ils sont ici et pas dispersés dans le code. */
    public const DELAI_PAIEMENT_MIN = 15;
    public const DELAI_ACCEPTATION_MIN = 120;
    public const DELAI_RECEPTION_MIN = 4320;        // 72 heures

    public function __construct(
        private StockService $stock,
        private ConversionUnites $conversion,
        private LivraisonService $livraison,
        private NotificationService $notifications,
    ) {}

    /**
     * Crée une commande et réserve la marchandise.
     *
     * Le panier reçu ne porte qu'un seul vendeur : c'est à l'appelant de le
     * découper. Un panier réparti sur trois quincailleries donne trois
     * commandes, trois livraisons et trois reversements — le vendeur qui a
     * livré est payé même si un autre fait défaut.
     *
     * @param  array<int, array{offre: Offre, quantite: string|int, unite: string}>  $lignes
     */
    public function creer(
        Acheteur $acheteur,
        array $lignes,
        string $modeRemise = 'retrait',
        ?string $adresse = null,
        ?float $lat = null,
        ?float $lng = null,
    ): Commande
    {
        if ($lignes === []) {
            throw new RuntimeException('Une commande sans ligne n\'a pas de sens.');
        }

        $vendeurs = collect($lignes)->pluck('offre.vendeur_id')->unique();
        if ($vendeurs->count() > 1) {
            throw new RuntimeException('Une commande ne concerne qu\'un seul vendeur.');
        }

        return DB::transaction(function () use ($acheteur, $lignes, $modeRemise, $adresse, $lat, $lng) {
            /** @var Offre $premiere */
            $premiere = $lignes[0]['offre'];
            $vendeur = $premiere->vendeur;

            $preparees = [];
            $total = 0;
            $poids = 0;

            foreach ($lignes as $ligne) {
                /** @var Offre $offre */
                $offre = $ligne['offre'];
                $pivot = $this->conversion->versPivot($offre->article, $ligne['unite'], $ligne['quantite']);

                // La réservation prend le verrou et refuse si le stock manque.
                // Elle est dans la même transaction que la commande : si une
                // ligne échoue, rien n'est réservé et rien n'est créé.
                $this->stock->reserver($offre, $pivot, $acheteur->utilisateur_id, 'Commande en cours');

                $facteur = $offre->article->unitesVente()
                    ->where('unite', $offre->unite_affichee)->value('facteur_vers_pivot');

                // Le montant se calcule sur le pivot, jamais sur la quantité
                // affichée : c'est le seul chiffre qui ne dépend pas de l'unité.
                $montant = intdiv($pivot * $offre->prix_par_unite, (int) $facteur);
                $total += $montant;
                $poids += $pivot;

                $preparees[] = [
                    'offre_id' => $offre->id,
                    'quantite_pivot' => $pivot,
                    'unite_affichee' => $ligne['unite'],
                    'quantite_affichee' => (string) $ligne['quantite'],
                    'prix_unitaire_fige' => $offre->prix_par_unite,
                    'montant' => $montant,
                ];
            }

            $taux = $vendeur->taux_commission_pour_mille;

            // Les frais de livraison dépendent du poids et de la distance : le
            // fer se paie au transport. Ils reviennent au vendeur, qui livre.
            $frais = 0;
            if ($modeRemise === 'livraison') {
                $point = $this->pointDeLivraison($acheteur, $lat, $lng);
                $frais = $this->livraison->fraisVers($vendeur, $point[0], $point[1], $poids);
            }

            $commande = Commande::create([
                'reference' => $this->reference(),
                'acheteur_id' => $acheteur->id,
                'vendeur_id' => $vendeur->id,
                'etat' => 'en_attente_paiement',
                'mode_remise' => $modeRemise,
                'adresse_livraison' => $adresse,
                'montant_articles' => $total,
                'frais_livraison' => $frais,
                'montant_total' => $total + $frais,
                'taux_commission_pour_mille' => $taux,
                // La commission porte sur la marchandise seule, jamais sur les
                // frais de livraison : prélever 8 % du carburant du vendeur ne
                // se défend pas. Elle est figée dès maintenant, mais ne devient
                // un revenu qu'à la réception : voir 04-grand-livre.
                'montant_commission' => intdiv($total * $taux, 1000),
                'expire_le' => now()->addMinutes(self::DELAI_PAIEMENT_MIN),
            ]);

            foreach ($preparees as $ligne) {
                LigneCommande::create(['commande_id' => $commande->id] + $ligne);
            }

            $this->journaliser($commande, '—', 'en_attente_paiement', 'Création');

            return $commande->fresh('lignes');
        });
    }

    /**
     * Où livrer : le point donné à la commande, sinon celui du compte.
     *
     * Sans coordonnées, on ne peut pas chiffrer une livraison — et facturer un
     * forfait au hasard reviendrait à faire payer Rufisque au prix de Pikine.
     */
    private function pointDeLivraison(Acheteur $acheteur, ?float $lat, ?float $lng): array
    {
        $lat ??= $acheteur->latitude;
        $lng ??= $acheteur->longitude;

        if ($lat === null || $lng === null) {
            throw new RuntimeException(
                'Indiquez où livrer : sans adresse repérée, les frais ne peuvent pas être calculés.'
            );
        }

        return [(float) $lat, (float) $lng];
    }

    public function marquerPayee(Commande $c, ?int $auteurId = null): Commande
    {
        return $this->transiter($c, 'payee', function (Commande $c) {
            $c->payee_le = now();
            $c->acceptation_due_le = now()->addMinutes(self::DELAI_ACCEPTATION_MIN);
        }, auteurId: $auteurId);
    }

    public function accepter(Commande $c, ?int $auteurId = null): Commande
    {
        return $this->transiter($c, 'acceptee', fn (Commande $c) => $c->acceptee_le = now(), auteurId: $auteurId);
    }

    public function marquerPrete(Commande $c, ?int $auteurId = null): Commande
    {
        return $this->transiter($c, 'prete', fn (Commande $c) => $c->prete_le = now(), auteurId: $auteurId);
    }

    /**
     * La marchandise quitte le magasin.
     *
     * C'est ici que la réservation devient une sortie définitive : jusque-là, le
     * fer était encore chez le vendeur, seulement promis.
     */
    public function remettre(Commande $c, ?int $auteurId = null): Commande
    {
        $suivant = $c->mode_remise === 'livraison' ? 'en_livraison' : 'receptionnee';

        return $this->transiter($c, $suivant, function (Commande $c) use ($auteurId) {
            foreach ($c->lignes as $ligne) {
                $this->stock->sortir($ligne->offre, $ligne->quantite_pivot, $auteurId, 'Remise ' . $c->reference);
            }
            $c->livree_le = now();
            if ($c->mode_remise !== 'livraison') {
                $c->receptionnee_le = now();
            } else {
                $c->reception_due_le = now()->addMinutes(self::DELAI_RECEPTION_MIN);
            }
        }, auteurId: $auteurId);
    }

    public function confirmerReception(Commande $c, ?int $auteurId = null): Commande
    {
        return $this->transiter($c, 'receptionnee', fn (Commande $c) => $c->receptionnee_le = now(), auteurId: $auteurId);
    }

    /**
     * La commande est soldée : le séquestre a été partagé au grand livre.
     *
     * Cette transition et l'écriture comptable vont de pair. Sans elle, une
     * commande dont l'argent est déjà réparti resterait comptée parmi celles
     * qui retiennent du séquestre, et l'invariant tomberait faux — ce qui est
     * exactement ce qu'il doit signaler.
     */
    public function marquerSoldee(Commande $c, ?int $auteurId = null): Commande
    {
        return $this->transiter($c, 'soldee', fn (Commande $c) => $c->soldee_le = now(), auteurId: $auteurId);
    }

    /** La commande entre en litige : plus rien ne se solde tant qu'il n'est pas tranché. */
    public function passerEnLitige(Commande $c, ?int $auteurId = null): Commande
    {
        return $this->transiter($c, 'en_litige', null, 'Litige ouvert', $auteurId);
    }

    /** Le litige est tranché en faveur de l'acheteur : la commande est remboursée. */
    public function rembourser(Commande $c, string $motif, ?int $auteurId = null): Commande
    {
        return $this->transiter($c, 'remboursee', function (Commande $c) use ($motif) {
            $c->annulee_le = now();
            $c->motif_annulation = $motif;
        }, $motif, $auteurId);
    }

    /**
     * Annule et rend la marchandise au stock.
     *
     * Tant que la commande n'est pas remise, la réservation tient : l'annuler
     * doit la libérer, sans quoi le stock resterait bloqué pour toujours.
     */
    public function annuler(Commande $c, string $motif, ?int $auteurId = null): Commande
    {
        return $this->transiter($c, 'annulee', function (Commande $c) use ($motif, $auteurId) {
            if (in_array($c->etat, Commande::ETATS_RESERVES, true)) {
                foreach ($c->lignes as $ligne) {
                    $this->stock->liberer($ligne->offre, $ligne->quantite_pivot, $auteurId, $motif);
                }
            }
            $c->annulee_le = now();
            $c->motif_annulation = $motif;
        }, $motif, $auteurId);
    }

    /** Le délai de paiement est passé : la réservation tombe. */
    public function expirer(Commande $c): Commande
    {
        return $this->transiter($c, 'expiree', function (Commande $c) {
            foreach ($c->lignes as $ligne) {
                $this->stock->liberer($ligne->offre, $ligne->quantite_pivot, null, 'Délai de paiement dépassé');
            }
            $c->annulee_le = now();
            $c->motif_annulation = 'Délai de paiement dépassé';
        });
    }

    /**
     * Le passage d'un état à l'autre, en un seul endroit.
     *
     * @throws TransitionInterdite
     */
    private function transiter(Commande $c, string $vers, ?callable $effet = null, ?string $motif = null, ?int $auteurId = null): Commande
    {
        return DB::transaction(function () use ($c, $vers, $effet, $motif, $auteurId) {
            // On relit sous verrou : entre le moment où l'appelant a chargé la
            // commande et maintenant, un autre acteur a pu la faire changer.
            $commande = Commande::whereKey($c->id)->lockForUpdate()->firstOrFail();
            $depart = $commande->etat;

            if (! $commande->peutAllerVers($vers)) {
                throw new TransitionInterdite($depart, $vers);
            }

            $commande->setRelation('lignes', $c->lignes()->with('offre')->get());
            if ($effet) {
                $effet($commande);
            }

            $commande->etat = $vers;
            $commande->save();

            $this->journaliser($commande, $depart, $vers, $motif, $auteurId);

            // Prévenir passe par le même goulot que journaliser : toute
            // transition, d'où qu'elle vienne, déclenche le courriel qui lui
            // correspond. Rien ne part avant la validation de la transaction.
            $this->notifications->surTransition($commande, $vers);

            return $commande->fresh('lignes');
        });
    }

    private function journaliser(Commande $c, string $depart, string $arrivee, ?string $motif = null, ?int $auteurId = null): void
    {
        TransitionCommande::create([
            'commande_id' => $c->id,
            'etat_depart' => $depart,
            'etat_arrivee' => $arrivee,
            'motif' => $motif,
            'auteur_id' => $auteurId,
        ]);
    }

    private function reference(): string
    {
        return sprintf('FF-%s-%06d', now()->year, Commande::max('id') + 1);
    }
}
