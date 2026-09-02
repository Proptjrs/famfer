# Machine à états de la commande

---

## Les états

```
   PANIER
     │  l'acheteur valide
     ▼
   EN_ATTENTE_PAIEMENT ──── délai 15 min ────► EXPIREE
     │  paiement confirmé par l'opérateur
     ▼
   PAYEE  (argent en séquestre, stock réservé)
     │                                    ┌── refus du vendeur ──┐
     │  le vendeur accepte                │  délai 2 h dépassé   │
     ▼                                    ▼                      │
   ACCEPTEE                            ANNULEE ◄─────────────────┘
     │  le vendeur prépare              (remboursement intégral)
     ▼
   PRETE
     │  remise au livreur, ou retrait
     ▼
   EN_LIVRAISON
     │  l'acheteur confirme (ou délai de 72 h)
     ▼
   RECEPTIONNEE ──── litige ouvert ────► EN_LITIGE
     │  reversement                              │  arbitrage
     ▼                                           ▼
   SOLDEE                            REMBOURSEE  ou  SOLDEE
```

## Ce que déclenche chaque transition

| Transition | Stock | Argent |
|---|---|---|
| Panier → En attente de paiement | **réservation** | — |
| Paiement confirmé | maintenue | encaissement → **séquestre** |
| Expiration du délai | **libération** | — |
| Refus ou délai vendeur | **libération** | remboursement intégral |
| Vendeur accepte | maintenue | — |
| Remise / livraison | **sortie de vente** | — |
| Réception confirmée | — | séquestre → vendeur + **commission** |
| Reversement | — | vendeur → compte de la plateforme |
| Litige tranché pour l'acheteur | **retour** | séquestre → remboursement, aucune commission |

## Les trois délais

**15 minutes pour payer.** Au-delà, la réservation tombe. Sans ce délai, un
panier abandonné bloquerait le stock d'un vendeur indéfiniment.

**2 heures pour que le vendeur accepte.** Au-delà, la commande est annulée et
l'acheteur remboursé automatiquement. C'est ce qui protège l'acheteur d'un
vendeur inactif — et ce qui donne sa valeur à la note du vendeur.

**72 heures pour confirmer la réception.** Sans réponse de l'acheteur, la
réception est réputée acquise et le reversement se déclenche. Sans cette règle,
un acheteur distrait bloquerait l'argent du vendeur pour toujours.

Ces trois délais sont des **tâches planifiées**, pas des vérifications à la
volée. Ils s'exécutent même si personne ne visite le site.

## La règle qui protège tout le monde

Le vendeur n'est jamais payé avant que l'acheteur ait reçu — ou que le délai de
72 h soit écoulé. C'est la raison d'être du séquestre, et la seule réponse
sérieuse à « pourquoi passer par votre plateforme plutôt qu'en direct ? ».

## La réservation de stock

Deux acheteurs, la dernière tonne, le même instant.

La réservation s'écrit dans une transaction, avec un **verrou pessimiste** sur
l'offre :

```php
DB::transaction(function () use ($offre, $quantitePivot) {
    $o = Offre::where('id', $offre->id)->lockForUpdate()->first();
    $disponible = $o->quantite_pivot - $o->quantite_reservee_pivot;
    if ($disponible < $quantitePivot) {
        throw new StockInsuffisant($disponible);
    }
    $o->increment('quantite_reservee_pivot', $quantitePivot);
    MouvementStock::create([...]);
});
```

Le test à écrire : lancer deux réservations concurrentes de la dernière unité et
vérifier qu'**une seule** aboutit. C'est un test que l'on montre à un jury.

## Les états ne se modifient pas à la main

Une transition passe toujours par une méthode du service, jamais par une
affectation directe. Chaque transition vérifie que l'état de départ est
autorisé, écrit l'horodatage, journalise l'auteur, et déclenche les écritures.

Un tableau des transitions autorisées, vérifié au début de chaque méthode,
interdit qu'une commande passe de `PAYEE` à `SOLDEE` sans être passée par la
livraison.
