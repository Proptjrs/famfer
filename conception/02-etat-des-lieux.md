# État des lieux — ce qui est construit

Document de suivi. Il dit ce qui existe réellement dans `app/`, ce qui
l'éprouve, et ce qui reste.

**Au 3 septembre 2026 : 77 tests, 456 assertions, tous verts sur PostgreSQL.**

---

## Ce que le projet est devenu

Le modèle à séquestre a été remplacé par une place de marché grand public à
paiement à la livraison. L'ancienne version reste entière sous l'étiquette
`v1-sequestre` — voir [01-le-projet.md](01-le-projet.md) pour la comparaison des
deux modèles.

Ce qui a été supprimé : le grand livre en partie double, la conversion d'unités
au gramme, le service de paiement idempotent et sa réconciliation, les
reversements, les litiges arbitrés, le balayage planifié des délais. Environ
2 500 lignes et 193 tests.

Ce qui a été gardé, parce que cela valait pour les deux modèles : les 25 dessins
de produits, la pagination écrite à la main, le middleware d'administration, le
principe du prix figé sur la ligne de commande, et la lecture du stock sous
verrou.

## Le domaine

| Brique | Où | Ce qui l'éprouve |
|---|---|---|
| Catalogue, recherche, filtres, tris | `Catalogue` | `CatalogueTest` |
| Panier en session, plafonné au stock | `Panier` | `ParcoursTest` |
| Commande et machine à états | `PasseCommande` | `CommandeTest` |
| Frais de livraison par région | `Livraison` | `AvisEtLivraisonTest` |
| Avis adossés à une livraison | `Notation` | `AvisEtLivraisonTest` |
| Cloisonnement des trois rôles | `EstAdministrateur`, contrôleurs | `RolesTest` |

Sept tables : `boutiques`, `categories`, `produits`, `adresses`, `commandes`,
`lignes_commande`, `avis`. Deux niveaux de catégories.

## Les décisions qui méritent d'être défendues

**Le stock sous verrou.** C'est le seul endroit où deux clients peuvent se
disputer le même article. Sans `lockForUpdate`, deux commandes simultanées sur
le dernier exemplaire passent toutes les deux : chacune lit « stock = 1 » et
chacune écrit « stock = 0 ». Un client reçoit un colis, l'autre une excuse, et
le vendeur découvre le problème à l'emballage.

**Le panier ne rabote pas en silence.** Un premier jet plafonnait la quantité au
stock disponible sans le dire : le client croyait commander deux barres et en
recevait une. C'est un test qui l'a montré. Désormais l'écart s'affiche en rouge
sur la page du panier, le bouton « Commander » se désactive, et la commande
refuse tant que l'écart subsiste. Servir moins sans le dire vaut moins bien que
faire corriger.

**« Refusée » n'est pas « annulée ».** Annuler, c'est se raviser avant que rien
ne parte. Refuser, c'est repousser le colis à la porte : la tournée a eu lieu,
elle a coûté. Les deux rendent le stock, mais une seule mérite d'être comptée —
le taux de refus est l'indicateur qui dit si le paiement à la livraison tient,
et il figure au tableau de bord de l'administration comme à celui de chaque
boutique.

**Le prix et l'adresse sont recopiés.** La ligne de commande porte son propre
nom de produit et son propre prix ; la commande porte sa propre adresse. Un
vendeur qui augmente son tarif, un client qui déménage : ni l'un ni l'autre ne
réécrit une commande déjà passée.

**La remise ne se saisit pas.** Elle se déduit du prix barré et du prix de
vente, et un prix barré inférieur est refusé à la publication. Annoncer « −40 % »
sur des chiffres qui ne le disent pas est la première tricherie d'une place de
marché, et la plus facile à empêcher.

**Les notes sont recalculées.** Celle du produit comme celle de la boutique.
Elles restent hors du `fillable` : une note affectable en masse est une note
qu'un formulaire finira par écrire lui-même.

**L'administration est fermée au rôle.** Pas seulement à l'authentification.
`RolesTest` pousse chaque porte séparément, avec un client puis avec un vendeur :
sans ce garde-fou, tout compte connecté validerait sa propre boutique et se
décernerait le badge « officielle ».

## L'interface

Trois étages, sur le modèle des places de marché grand public : un bandeau de
service pour ce qui ne s'achète pas, un en-tête avec la recherche et le menu du
compte, puis la barre des rayons avec une icône par catégorie.

Le menu du compte est un `<details>` natif : le clavier l'ouvre sans script, et
il fonctionne si le JavaScript ne se charge pas. Il groupe « Mon compte », « Ma
boutique » et « Plateforme » sous leurs titres, au lieu d'aligner des liens.

La fiche produit porte un tableau **« Le même produit ailleurs »** : le prix chez
les autres boutiques, avec l'écart. C'est ce qui distingue une place de marché
d'une boutique en ligne, et c'est le seul héritage de conception de la version
précédente.

## Les données de démonstration

`CatalogueSeeder` pose 7 rayons, 16 sous-rayons, 4 boutiques et 137 produits, aux
prix du marché dakarois. `ClientsSeeder` mène six commandes de bout en bout **par
les services de l'application** : les stocks baissent réellement, les compteurs
de vente montent, et les étoiles affichées viennent d'achats livrés.

```bash
php artisan migrate:fresh --seed
```

Comptes de démonstration, mot de passe `password` :

| Compte | Rôle |
|---|---|
| `admin@famfer.sn` | administration |
| `vendeur1@famfer.sn` … `vendeur3@famfer.sn` | boutiques actives |
| `vendeur4@famfer.sn` | boutique en attente de validation |
| `ndiaye.btp@chantier.sn` et cinq autres | clients ayant commandé et noté |

## Le déploiement

Image Docker en deux étages, `docker compose` de développement, description
Render de l'infrastructure. Vérifié : l'image se construit, le conteneur
démarre, migre, sème, et sert `/up`, l'accueil, un rayon, une fiche produit et
le panier en 200, sans une erreur au journal.

La tâche planifiée du modèle précédent a été retirée : avec le paiement à la
livraison, rien ne se périme tout seul.

## Ce qui reste

- **Le terrain.** Deux ou trois quincailliers, deux chefs de chantier, les prix
  réels. Rien de ce qui précède ne remplace cette enquête.
- **Brancher Render** sur le dépôt : voir [03-mise-en-ligne.md](03-mise-en-ligne.md).
- **Le mémoire lui-même** : problématique, état de l'art, méthodologie.
