# Le projet

**Nom :** FamFer
GUEYE Samba · Master Génie logiciel · ISI Dakar

---

## Ce que c'est

Une place de marché nationale du fer et des pièces détachées. Les quincailleries
s'inscrivent et publient leur stock. Les acheteurs — particuliers, chefs de
chantier, entreprises — comparent les prix, commandent et paient sur la
plateforme. L'argent est retenu jusqu'à la livraison, puis reversé au vendeur,
diminué d'une commission.

Le modèle est celui de Yango : la plateforme ne vend rien, elle met en relation
et prélève un pourcentage.

## Ce que ce n'est pas

Ce n'est pas un logiciel de gestion de stock pour un magasin. Ce n'est pas un
annuaire de quincailleries. La différence tient en un point : **la plateforme
encaisse**. C'est ce qui rend la commission incontournable, et c'est ce qui fait
la difficulté technique du sujet.

## Les trois problèmes qui font le niveau master

### 1. Le catalogue partagé contre les offres

Si chaque vendeur saisit son article à sa manière — « fer 10 », « T10 »,
« FA T10 12m » — l'acheteur ne peut rien comparer, et la plateforme n'est qu'un
annuaire.

Il faut donc **deux niveaux** :

- un **référentiel national** : un T10 est un T10, avec son diamètre, sa longueur
  normalisée, sa masse linéique (0,617 kg/m) ;
- des **offres** : le vendeur X propose ce T10 à 4 200 F la barre, il en a 340,
  il est à 3 km de l'acheteur.

Comparer devient possible. C'est la décision de conception la plus structurante.

### 2. Le flux de l'argent

La plateforme encaisse pour le compte d'autrui. L'argent en séquestre **ne lui
appartient pas** : seule la commission est un revenu. Confondre les deux est
l'erreur la plus fréquente, et la plus grave.

Le traitement est un **grand livre en partie double** — voir `04-grand-livre.md`.

### 3. La confiance

Pourquoi un chef de chantier paierait-il un vendeur qu'il ne connaît pas ?

- vérification des vendeurs à l'inscription ;
- notation après livraison ;
- séquestre : le vendeur n'est payé qu'après réception confirmée ;
- procédure de litige avant reversement.

## Les unités, une difficulté propre au métier

Le fer s'achète à la tonne, se stocke à la barre, se vend au kilo ou à la barre.
Une tôle se vend à la feuille ou au mètre carré.

Chaque article du référentiel porte une **unité pivot** et ses facteurs de
conversion. Un T10 de 12 m pèse 7,4 kg ; une tonne fait donc 135 barres. Le
système convertit, l'utilisateur ne calcule jamais.

Les arrondis se traitent une fois pour toutes : les quantités en pivot sont
stockées en entier — en grammes, en millimètres — jamais en flottant.

## La limite à assumer devant le jury

Encaisser pour le compte d'autrui suppose, dans la vie réelle, un accord avec un
opérateur de paiement agréé. Un projet d'étude travaille en **environnement de
test** : Wave, PayDunya et CinetPay en fournissent un.

Cette limite se dit clairement. Elle ne diminue pas le travail : l'intégration,
l'idempotence des rappels et la réconciliation sont identiques en test et en
production.
