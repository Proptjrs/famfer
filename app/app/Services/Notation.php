<?php

namespace App\Services;

use App\Models\Avis;
use App\Models\Boutique;
use App\Models\Commande;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Les avis sur les produits, et la note qui en découle.
 *
 * Une note ne vaut que si elle est adossée à un achat livré. Partout où l'on
 * peut noter sans avoir reçu, les notes finissent par ne plus rien dire : les
 * concurrents se descendent, les amis se remontent.
 *
 * Les moyennes — celle du produit et celle de la boutique — sont recalculées
 * depuis les avis, jamais saisies. Un champ qu'on peut écrire à la main est un
 * champ qui finit par mentir.
 */
class Notation
{
    public function noter(
        Commande $commande, Produit $produit, int $note,
        ?string $titre = null, ?string $commentaire = null
    ): Avis {
        if ($note < 1 || $note > 5) {
            throw new RuntimeException('La note va de 1 à 5.');
        }

        if ($commande->etat !== 'livree') {
            throw new RuntimeException(
                'On ne note qu\'une commande livrée : sans réception, une note ne vaut rien.'
            );
        }

        if (! $commande->lignes->contains('produit_id', $produit->id)) {
            throw new RuntimeException('Ce produit ne figure pas dans cette commande.');
        }

        if (Avis::where('commande_id', $commande->id)->where('produit_id', $produit->id)->exists()) {
            throw new RuntimeException('Vous avez déjà noté ce produit pour cette commande.');
        }

        return DB::transaction(function () use ($commande, $produit, $note, $titre, $commentaire) {
            $avis = Avis::create([
                'utilisateur_id' => $commande->utilisateur_id,
                'produit_id' => $produit->id,
                'commande_id' => $commande->id,
                'note' => $note,
                'titre' => $titre,
                'commentaire' => $commentaire,
            ]);

            $this->recalculerLeProduit($produit);
            $this->recalculerLaBoutique($produit->boutique);

            return $avis;
        });
    }

    public function recalculerLeProduit(Produit $produit): void
    {
        $avis = Avis::where('produit_id', $produit->id);
        $nombre = $avis->count();

        // Écriture directe : ces colonnes sont hors du « fillable » du produit.
        // Une note affectable en masse est une note qu'un formulaire finira par
        // écrire lui-même.
        $produit->nombre_avis = $nombre;
        $produit->note_sur_cent = $nombre > 0 ? (int) round($avis->avg('note') * 20) : null;
        $produit->save();
    }

    public function recalculerLaBoutique(Boutique $boutique): void
    {
        $avis = Avis::whereIn('produit_id', $boutique->produits()->select('id'));
        $nombre = $avis->count();

        $boutique->nombre_avis = $nombre;
        $boutique->note_sur_cent = $nombre > 0 ? (int) round($avis->avg('note') * 20) : null;
        $boutique->save();
    }

    /** Les produits d'une commande livrée qu'il reste à noter. */
    public function aNoter(Commande $commande)
    {
        if ($commande->etat !== 'livree') {
            return collect();
        }

        $dejaNotes = Avis::where('commande_id', $commande->id)->pluck('produit_id');

        return $commande->lignes->reject(fn ($l) => $dejaNotes->contains($l->produit_id));
    }
}
