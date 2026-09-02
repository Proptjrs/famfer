# Calendrier de développement

L'ordre compte plus que la vitesse. Chaque étape s'appuie sur la précédente.

---

## Étape 1 — Le référentiel et les unités

Familles, articles, unités de vente et facteurs de conversion. Une commande
d'import pour charger un premier jeu réaliste : fers à béton du T6 au T32, tôles,
tubes, cornières.

**Ce qui doit marcher :** convertir 1 tonne de T10 en barres, et l'inverse, sans
perdre un gramme.

**À tester :** les conversions dans les deux sens sur toute la gamme.

## Étape 2 — Vendeurs et offres

Inscription d'une quincaillerie, vérification par l'administration, publication
d'offres sur les articles du référentiel.

**Ce qui doit marcher :** trois vendeurs proposent le même T10 à trois prix ;
l'acheteur voit les trois côte à côte.

## Étape 3 — Recherche et proximité

Recherche plein texte tolérante — « fer 10 » trouve le T10 — puis tri par prix,
par distance, par note. Réutilisez la formule de Haversine et OSRM de MediGuide.

**Ce qui doit marcher :** une recherche répond en moins de 300 ms avec quelques
milliers d'offres.

## Étape 4 — Panier et commande

Un panier réparti sur plusieurs vendeurs produit une commande par vendeur.
Réservation de stock sous verrou. Machine à états.

**À tester en premier :** deux réservations concurrentes de la dernière unité —
une seule aboutit.

## Étape 5 — Le grand livre

Écritures, comptes, soldes. **Avant** toute intégration de paiement : on simule
les encaissements avec une commande d'administration.

**À tester :** les quatre invariants de `04-grand-livre.md`.

C'est l'étape la plus importante du mémoire. Ne la bâclez pas, et ne la
repoussez pas à la fin.

## Étape 6 — Le paiement

Intégration en environnement de test : Wave, ou un agrégateur comme PayDunya.
Rappels idempotents, tâche de réconciliation nocturne.

**À tester :** rejouer deux fois le même rappel ne crédite qu'une fois.

## Étape 7 — Livraison et réception

Bon de livraison, confirmation par l'acheteur, délai de 72 heures, déclenchement
du reversement.

## Étape 8 — Reversements et litiges

Reversement périodique par vendeur. Ouverture d'un litige, gel du reversement,
arbitrage par l'administration.

## Étape 9 — Pilotage

Pour le vendeur : ventes, articles qui tournent, note. Pour la plateforme :
volume, commission, litiges, vendeurs actifs.

## Étape 10 — Mise en ligne

Docker, hébergement, sauvegardes. Vous l'avez déjà fait une fois.

---

## Ce qui est hors sujet pour un mémoire

- Une application mobile native. Le web adaptatif suffit et vous l'avez déjà
  prouvé.
- La livraison par des livreurs indépendants avec suivi en temps réel. Un sujet
  à lui seul.
- La facturation fiscale normalisée. On la cite en perspective.

Un mémoire de master se juge sur la **profondeur** d'un sujet difficile, pas sur
le nombre d'écrans.

## Ce qu'il faut aller chercher sur le terrain

Comme pour MediGuide, l'enquête vaut cher devant un jury.

- Deux ou trois quincailliers : comment tiennent-ils leur stock aujourd'hui ?
  accepteraient-ils une commission, et de quel ordre ?
- Deux chefs de chantier : comment achètent-ils, comment paient-ils, qu'est-ce
  qui les ferait changer ?
- Les prix réels du marché, pour que la démonstration sonne juste.

C'est cette enquête qui transforme un exercice technique en réponse à un besoin.
