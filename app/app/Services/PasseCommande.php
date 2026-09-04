<?php

namespace App\Services;

use App\Models\Adresse;
use App\Models\Commande;
use App\Models\LigneCommande;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Passer une commande, et la faire avancer.
 *
 * Le paiement se fait à la livraison : rien n'est encaissé ici. Ce qui se joue
 * au moment de valider, c'est le stock — et c'est le seul endroit du projet où
 * deux clients peuvent se disputer le même article.
 *
 * D'où la lecture sous verrou. Sans elle, deux commandes simultanées sur le
 * dernier exemplaire passent toutes les deux : chacune lit « stock = 1 », et
 * chacune écrit « stock = 0 ». Un client recevrait un colis, l'autre une
 * excuse — et le vendeur découvrirait le problème à l'emballage.
 */
class PasseCommande
{
    public function __construct(
        private Panier $panier,
        private Livraison $livraison,
        private Avertir $avertir,
    ) {}

    /**
     * Crée la commande depuis le panier en session.
     *
     * @throws RuntimeException panier vide, ou stock parti entre-temps
     */
    public function creer(User $client, Adresse $adresse, string $paiement = 'livraison'): Commande
    {
        $contenu = $this->panier->contenu();

        if ($contenu->isEmpty()) {
            throw new RuntimeException('Votre panier est vide.');
        }

        return DB::transaction(function () use ($client, $adresse, $paiement, $contenu) {
            $lignes = [];
            $sousTotal = 0;
            $commission = 0;

            foreach ($contenu as $article) {
                // On relit sous verrou : le panier a pu être rempli il y a une
                // heure, et le stock lu alors ne vaut plus rien.
                $produit = Produit::whereKey($article['produit']->id)
                    ->lockForUpdate()->firstOrFail();
                $produit->loadMissing('boutique');

                // On compare au demandé, pas au disponible : servir moins
                // sans le dire vaudrait mieux que rien, mais le client doit
                // avoir accepté ce qu'il reçoit.
                if ($produit->stock < $article['quantite']) {
                    throw new RuntimeException(sprintf(
                        '« %s » : il n\'en reste que %d. Ajustez votre panier.',
                        $produit->nom, $produit->stock
                    ));
                }

                $produit->decrement('stock', $article['quantite']);
                $produit->increment('nombre_ventes', $article['quantite']);

                $montant = $produit->prix * $article['quantite'];
                $sousTotal += $montant;

                // La commission se calcule ligne par ligne, au taux de la
                // boutique qui vend. Un panier réparti sur trois enseignes aux
                // taux différents doit produire trois montants différents ; un
                // taux unique appliqué au total serait faux pour toutes.
                $part = $produit->boutique->commissionSur($montant);
                $commission += $part;

                $lignes[] = [
                    'produit_id' => $produit->id,
                    'boutique_id' => $produit->boutique_id,
                    // Figés : le tarif de demain ne réécrit pas la commande d'hier.
                    'nom_produit' => $produit->nom,
                    'prix_unitaire' => $produit->prix,
                    'quantite' => $article['quantite'],
                    'montant' => $montant,
                    // Figée elle aussi, et pour la même raison : un taux
                    // renégocié le mois prochain ne doit pas refacturer une
                    // vente déjà conclue.
                    'commission' => $part,
                ];
            }

            $frais = $this->livraison->frais($adresse->region, $sousTotal);

            $commande = Commande::create([
                'reference' => $this->reference(),
                'utilisateur_id' => $client->id,
                // L'adresse est recopiée : corriger son carnet ne doit pas
                // réécrire l'histoire d'une commande déjà partie.
                'destinataire' => $adresse->destinataire,
                'telephone' => $adresse->telephone,
                'adresse_livraison' => $adresse->enUneLigne(),
                'etat' => 'en_preparation',
                'paiement' => $paiement,
                'paye' => false,
                'sous_total' => $sousTotal,
                'frais_livraison' => $frais,
                'total' => $sousTotal + $frais,
                // La commission ne porte que sur la marchandise : les frais de
                // livraison couvrent une tournée que le vendeur paie de sa
                // poche, en prélever une part reviendrait à taxer son essence.
                'commission' => $commission,
                // Le taux effectif de la commande, déduit et non choisi : sur un
                // panier multi-boutiques il n'existe pas de taux unique, celui-ci
                // est la moyenne réellement appliquée.
                'taux_commission_pour_mille' => $sousTotal > 0
                    ? intdiv($commission * 1000, $sousTotal)
                    : 0,
            ]);

            foreach ($lignes as $ligne) {
                LigneCommande::create(['commande_id' => $commande->id] + $ligne);
            }

            $this->panier->vider();

            $commande = $commande->fresh('lignes');
            $this->avertir->surNouvelleCommande($commande);

            return $commande;
        });
    }

    // ── Le cycle de vie ──────────────────────────────────────────────────────

    /**
     * L'expédition tire le code de remise.
     *
     * Six chiffres au hasard, communiqués au client seul. Le vendeur ne pourra
     * clore la commande sans ce code, que l'acheteur ne dicte qu'en recevant le
     * colis : c'est ce qui transforme la déclaration du vendeur en preuve.
     *
     * « random_int » et non « rand » : un code devinable ne prouverait rien.
     */
    public function expedier(Commande $c): Commande
    {
        return $this->faireAvancer($c, 'expediee', function (Commande $c) {
            $c->expediee_le = now();
            $c->code_livraison = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        });
    }

    public function mettreEnLivraison(Commande $c): Commande
    {
        return $this->faireAvancer($c, 'en_livraison');
    }

    /**
     * Livrée et payée : sur ce mode, les deux vont ensemble.
     *
     * Le vendeur doit produire le code que le client lui a dicté à la remise.
     * Sans lui, il déclarerait seul un fait dont il est le bénéficiaire.
     *
     * Le code n'est exigé que s'il en existe un : une commande confirmée par le
     * client lui-même, ou tranchée par l'administration, se clôt sans.
     *
     * @throws RuntimeException code absent ou faux
     */
    public function livrer(Commande $c, ?string $code = null): Commande
    {
        return $this->faireAvancer($c, 'livree', function (Commande $c) use ($code) {
            if ($c->code_livraison !== null) {
                // Comparaison à temps constant : le code est court, et un
                // vendeur de mauvaise foi le chercherait par essais.
                if ($code === null || ! hash_equals($c->code_livraison, trim($code))) {
                    throw new RuntimeException(
                        'Code de remise incorrect. Demandez au client le code à '
                        . 'six chiffres affiché sur son suivi de commande.'
                    );
                }
                $c->code_remis_le = now();
            }

            $c->livree_le = now();
            $c->cloturee_le = now();
            $c->paye = $c->paiement === 'livraison';
        });
    }

    /**
     * Le client déclare avoir reçu et payé.
     *
     * C'est le contrepoids de tout le dispositif. Un vendeur qui aurait livré,
     * encaissé les espèces, puis déclaré « refusée » pour garder l'argent sans
     * payer de commission se voit contredit par l'acheteur — et la commission
     * redevient due.
     *
     * On ne demande aucun code ici : le client n'a rien à prouver, il témoigne
     * contre son propre intérêt apparent.
     */
    public function confirmerParLeClient(Commande $c): Commande
    {
        return $this->faireAvancer($c, 'livree', function (Commande $c) {
            $c->confirmee_le = now();
            $c->livree_le = $c->livree_le ?? now();
            $c->cloturee_le = now();
            $c->paye = $c->paiement === 'livraison';
        });
    }

    /**
     * Une partie conteste l'état déclaré par l'autre.
     *
     * L'état contesté est conservé : sans lui, l'administration arbitrerait
     * sans savoir ce qui était affirmé au départ.
     */
    public function contester(Commande $c, string $par, string $motif): Commande
    {
        if (! in_array($par, Commande::PARTIES, true)) {
            throw new RuntimeException('Seuls le client et le vendeur ouvrent un litige.');
        }

        return $this->faireAvancer($c, 'litige', function (Commande $c) use ($par, $motif) {
            $c->etat_conteste = $c->etat;
            $c->litige_par = $par;
            $c->litige_motif = $motif;
            $c->litige_le = now();
            $c->cloturee_le = null;
        });
    }

    /**
     * L'administration tranche le litige.
     *
     * Le stock suit la décision : rendu si la vente n'a pas eu lieu, laissé
     * sorti si elle a eu lieu. Comme le litige naît après une déclaration qui a
     * pu déjà rendre le stock, on ne le rend qu'une fois — d'où la comparaison
     * avec l'état contesté.
     *
     * @throws RuntimeException décision hors des trois issues possibles
     */
    public function trancher(Commande $c, string $vers, string $motif): Commande
    {
        if (! in_array($vers, ['livree', 'refusee', 'annulee'], true)) {
            throw new RuntimeException('Un litige se tranche en livrée, refusée ou annulée.');
        }

        $stockDejaRendu = in_array($c->etat_conteste, ['refusee', 'annulee', 'retournee'], true);

        return $this->faireAvancer($c, $vers, function (Commande $c) use ($vers, $motif, $stockDejaRendu) {
            $c->motif = $motif;
            $c->cloturee_le = now();
            $c->paye = $vers === 'livree' && $c->paiement === 'livraison';

            if ($vers === 'livree') {
                $c->livree_le = $c->livree_le ?? now();

                // La vente a bien eu lieu : le stock rendu à tort ressort.
                if ($stockDejaRendu) {
                    $this->reprendreLeStock($c);
                }
            } elseif (! $stockDejaRendu) {
                $this->rendreLeStock($c);
            }
        });
    }

    /**
     * Le client a refusé le colis à la porte.
     *
     * Distinct d'une annulation : la tournée a eu lieu, elle a coûté. Le stock
     * revient en revanche, puisque la marchandise rentre.
     */
    public function refuser(Commande $c, string $motif): Commande
    {
        return $this->faireAvancer($c, 'refusee', function (Commande $c) use ($motif) {
            $c->motif = $motif;
            $c->cloturee_le = now();
            $this->rendreLeStock($c);
        });
    }

    public function annuler(Commande $c, string $motif): Commande
    {
        return $this->faireAvancer($c, 'annulee', function (Commande $c) use ($motif) {
            $c->motif = $motif;
            $c->cloturee_le = now();
            $this->rendreLeStock($c);
        });
    }

    public function retourner(Commande $c, string $motif): Commande
    {
        return $this->faireAvancer($c, 'retournee', function (Commande $c) use ($motif) {
            $c->motif = $motif;
            $this->rendreLeStock($c);
        });
    }

    /**
     * Le passage d'un état à l'autre, en un seul endroit.
     *
     * La commande est relue sous verrou : entre le moment où l'appelant l'a
     * chargée et maintenant, le vendeur a pu l'expédier depuis un autre écran.
     */
    private function faireAvancer(Commande $c, string $vers, ?callable $effet = null): Commande
    {
        return DB::transaction(function () use ($c, $vers, $effet) {
            $commande = Commande::whereKey($c->id)->lockForUpdate()->firstOrFail();

            if (! $commande->peutAllerVers($vers)) {
                throw new RuntimeException(sprintf(
                    'Une commande « %s » ne peut pas passer à « %s ».',
                    $commande->etat, $vers
                ));
            }

            $commande->setRelation('lignes', $c->lignes()->get());

            if ($effet) {
                $effet($commande);
            }

            $commande->etat = $vers;
            $commande->save();

            $commande = $commande->fresh('lignes');

            // Prevenir passe par le meme goulot que la transition : tout
            // changement d'etat, d'ou qu'il vienne, declenche le courriel qui
            // lui correspond. Rien ne part avant la validation.
            $this->avertir->surEtat($commande, $vers);

            return $commande;
        });
    }

    /** La marchandise rentre : on la remet en vente. */
    private function rendreLeStock(Commande $c): void
    {
        foreach ($c->lignes as $ligne) {
            Produit::whereKey($ligne->produit_id)->increment('stock', $ligne->quantite);
            Produit::whereKey($ligne->produit_id)->decrement('nombre_ventes', $ligne->quantite);
        }
    }

    /** Le contraire : l'arbitrage a jugé que la vente avait bien eu lieu. */
    private function reprendreLeStock(Commande $c): void
    {
        foreach ($c->lignes as $ligne) {
            Produit::whereKey($ligne->produit_id)->decrement('stock', $ligne->quantite);
            Produit::whereKey($ligne->produit_id)->increment('nombre_ventes', $ligne->quantite);
        }
    }

    /** « FF-2026-000123 » : lisible au téléphone, et unique. */
    private function reference(): string
    {
        $suivant = (Commande::max('id') ?? 0) + 1;

        return sprintf('FF-%s-%06d', now()->year, $suivant);
    }
}
