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

    public function expedier(Commande $c): Commande
    {
        return $this->faireAvancer($c, 'expediee', fn (Commande $c) => $c->expediee_le = now());
    }

    public function mettreEnLivraison(Commande $c): Commande
    {
        return $this->faireAvancer($c, 'en_livraison');
    }

    /** Livrée et payée : sur ce mode, les deux vont ensemble. */
    public function livrer(Commande $c): Commande
    {
        return $this->faireAvancer($c, 'livree', function (Commande $c) {
            $c->livree_le = now();
            $c->cloturee_le = now();
            $c->paye = $c->paiement === 'livraison';
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

    /** « FF-2026-000123 » : lisible au téléphone, et unique. */
    private function reference(): string
    {
        $suivant = (Commande::max('id') ?? 0) + 1;

        return sprintf('FF-%s-%06d', now()->year, $suivant);
    }
}
