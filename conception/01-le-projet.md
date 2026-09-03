# FamFer — place de marché du fer et de la quincaillerie

## Le projet

Une place de marché grand public pour le fer, les tôles et la quincaillerie au
Sénégal. Des boutiques publient leurs produits, les clients comparent, commandent
et se font livrer. Le règlement se fait **à la livraison**, en espèces, au
livreur.

Le modèle est celui des places de marché grand public de la région : catalogue
par rayons, recherche, filtres, promotions, avis clients, suivi de commande.

## Ce qui a changé, et pourquoi c'est écrit ici

Une première version de FamFer reposait sur un **séquestre** : la plateforme
encaissait le paiement, le retenait, et ne le reversait au vendeur qu'une fois
la réception confirmée. Elle tenait un grand livre en partie double, convertissait
les unités de vente au gramme près pour comparer une barre à une tonne, et
verrouillait le stock contre les commandes concurrentes.

Ce modèle a été **abandonné à la demande**, au profit du modèle grand public
décrit ici. Il reste consultable en entier :

```bash
git checkout v1-sequestre
```

Les deux se défendent, et le choix n'est pas technique :

| | Séquestre | Paiement à la livraison |
|---|---|---|
| Qui détient l'argent | la plateforme, jusqu'à réception | personne : le client paie au livreur |
| Ce qui protège l'acheteur | la rétention des fonds | le droit de refuser le colis |
| Ce qui protège le vendeur | la commission garantie | rien : un refus lui coûte une tournée |
| Ce qu'il faut construire | comptabilité, réconciliation, litiges | suivi de commande, gestion des refus |
| Adoption au Sénégal | à convaincre | déjà l'usage |

Le second est plus simple à faire adopter, et plus fragile à exploiter : chaque
colis refusé est une tournée payée pour rien. C'est pourquoi le taux de refus
figure au tableau de bord de l'administration et à celui de chaque boutique.

## Les acteurs

**Trois rôles humains**, portés par une seule colonne du compte.

| Rôle | Comment il se distingue | Ce qu'il fait |
|---|---|---|
| Client | rôle `client` | cherche, compare, commande, suit, note |
| Vendeur | rôle `vendeur` + une boutique validée | publie ses produits, expédie, livre |
| Administration | rôle `admin` | valide les boutiques, suspend, met en avant |

Le rôle se choisit **à l'inscription** : le formulaire demande si l'on vient
acheter ou vendre. Tout compte peut acheter, y compris celui d'un commerçant.

Aucune boutique n'apparaît au catalogue avant validation. C'est le seul contrôle
a priori du système, et il porte tout le reste : sans lui, n'importe qui
publierait n'importe quoi sous n'importe quel prix.

## Ce que la plateforme garantit

**Le stock affiché est le stock réel.** Il baisse à la commande, sous verrou —
c'est le seul endroit où deux clients peuvent se disputer le même article — et
remonte si la commande est annulée, refusée ou retournée.

**Le prix affiché est le prix facturé.** La ligne de commande recopie le nom et
le prix : un vendeur qui change son tarif le lendemain ne change pas ce que le
client a accepté. De même pour l'adresse de livraison, recopiée au moment de la
commande.

**La remise annoncée est vraie.** Elle se calcule du prix barré et du prix de
vente ; elle ne se saisit pas. Un prix barré inférieur au prix de vente est
refusé à la publication.

**Une note vient d'un achat livré.** Un produit ne se note qu'une fois par
commande, et les moyennes — du produit comme de la boutique — sont recalculées
depuis les avis. Elles ne sont jamais écrites à la main.

**Les frais de livraison se devinent avant de commander.** Un forfait par
région, de 1 500 F sur Dakar à 5 000 F pour les régions éloignées, et la
gratuité au-dessus de 50 000 F.

## Le périmètre

Ce qui est volontairement laissé de côté, et à assumer comme des choix :

- **Le paiement en ligne effectif.** Wave et Orange Money figurent comme modes
  de règlement, mais aucun appel n'est fait à un opérateur : le client est
  rappelé. Un vrai encaissement demande un contrat commercial.
- **Les livreurs indépendants.** Le vendeur livre lui-même. Un réseau de
  livreurs avec suivi en temps réel est un sujet à lui seul.
- **L'application mobile native.** Le web adaptatif suffit.

## Où en est le code

Voir [02-etat-des-lieux.md](02-etat-des-lieux.md).
