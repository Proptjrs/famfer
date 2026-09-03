# État des lieux — ce qui est construit

Document de suivi. Il dit ce qui existe réellement dans `app/`, ce qui
l'éprouve, et ce qui reste.

**Au 3 septembre 2026 : 93 tests, 589 assertions, tous verts sur PostgreSQL.**

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

---

## Les photos des produits

Un produit ne portait qu'une clé de dessin — un tracé vectoriel choisi dans une
liste. C'est net et léger, mais ce n'est pas la marchandise : un client qui
achète une tôle veut voir la tôle, pas son schéma.

Les vendeurs téléversent désormais jusqu'à huit photos par produit, la première
servant de vignette. **Le dessin reste en repli** : un catalogue se remplit peu
à peu, et sans lui les produits non encore photographiés apparaîtraient comme
des cadres vides pendant des mois.

C'est la seule porte du site par laquelle un fichier entre, donc la plus
dangereuse. Trois précautions la gardent :

- **Le type est déduit du contenu, pas du nom.** `getimagesize` lit les
  dimensions ; ce qui n'est pas une image échoue là, avant d'atteindre le
  disque. Un fichier appelé `photo.jpg` peut être n'importe quoi.
- **Le nom est réécrit.** Ni le dossier ni l'extension ne viennent du fichier
  reçu : un nom fourni par l'extérieur permet de remonter l'arborescence, et une
  extension fournie par l'extérieur permet de déposer un `.php` dans un dossier
  servi par le serveur web. `PhotosTest` téléverse `../../evasion.php.jpg` et
  vérifie que rien de tout cela ne survit au passage.
- **Le dossier n'exécute rien.** Les fichiers vont dans le disque public de
  Laravel, servi en statique.

Une image refusée n'empêche pas les autres de passer : le vendeur voit ce qui
n'est pas passé et pourquoi, plutôt que de recommencer tout le lot.

**Une limite à connaître avant la mise en ligne.** Le disque d'un conteneur est
éphémère : sur le plan gratuit de Render, les photos téléversées disparaissent
au redéploiement suivant. Trois issues, par ordre de coût : un disque persistant
Render (payant), un stockage objet type S3 (le disque `public` se remplace par
`s3` sans toucher au code), ou accepter la perte le temps du mémoire et
re-téléverser avant la soutenance.

`PhotosTest` : 16 tests.


---

## Le catalogue complet, et de vraies images

### 689 références

Le catalogue vient du document fourni par le client — 14 rayons, 57
sous-rayons, 689 références, du fer à béton aux pièces détachées automobiles en
passant par la plomberie et les EPI. Il est extrait du PDF vers un fichier de
données PHP, puis semé : **1 854 fiches produit**, chaque article étant tenu par
deux boutiques sur trois pour que la comparaison des prix ait un sens.

Les prix sont dérivés du nom de l'article, dans une fourchette propre à chaque
sous-rayon. Deux conséquences voulues : ils sont **stables** d'un semis à
l'autre — le même article vaut toujours la même chose, donc une démonstration
se rejoue à l'identique — et ils sont **indicatifs**, à remplacer par de vrais
relevés avant toute mise en service. C'est écrit en tête du fichier engendré.

### Des photos, sous licence libre

Les photos de produits des places de marché et des fabricants sont protégées :
les reprendre exposerait le projet. Les illustrations viennent donc de
**Wikimedia Commons**, et uniquement de fichiers sous CC0, domaine public, CC BY
ou CC BY-SA. L'auteur, la licence et la page d'origine de chacune sont
enregistrés en base et publiés sur `/credits` — c'est une obligation de ces
licences, pas une politesse.

**Comment elles sont choisies, et pourquoi c'est le point intéressant.** La
recherche plein texte de Commons ramenait n'importe quoi : un avion pour
« tube », un train pour « portail », un intérieur de musée pour « électrique ».
Son score porte sur le texte de la page, pas sur ce que montre l'image.

Deuxième essai par les **catégories** de Commons, tenues à la main par des
contributeurs : plus juste, mais les catégories sont rangées par ordre
alphabétique et leur premier fichier n'a aucune raison d'être représentatif —
« Category:Hinges » commence par une photo de break américain de 1974.

Troisième essai, celui qui marche : parmi les soixante premiers fichiers d'une
catégorie, retenir celui dont **le nom** contient le mot de la catégorie.
« padlock » dans « - Padlock -.jpg ». Signal grossier, mais il écarte
l'essentiel du hors-sujet : 48 des 55 illustrations sont retenues à ce
troisième passage.

### Trois échelons d'affichage

1. La photo téléversée par le vendeur.
2. À défaut, l'illustration de la famille du produit.
3. À défaut encore, le dessin au trait.

Le dernier ne manque jamais, donc aucune fiche n'apparaît vide. Un catalogue de
1 854 produits ne se photographie pas en un jour ; sans ces échelons, la
quasi-totalité des fiches serait blanche.

**Un défaut attrapé au passage** : le disque public de Laravel construisait ses
adresses sur `APP_URL`, qui ne porte pas le port du serveur de développement.
Les images pointaient donc vers le port 80 et ne s'affichaient pas. L'adresse
est maintenant relative, et suit l'hôte réellement servi.

Les illustrations sont versionnées et voyagent dans l'image Docker — le disque
d'un conteneur ne survit pas à un redéploiement. Les photos des vendeurs, elles,
restent hors du dépôt.
