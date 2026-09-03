# Mettre FamFer en ligne

Tout ce qui pouvait être fait et vérifié sur le poste l'a été : l'image se
construit, le conteneur démarre, l'application répond, le rappel de paiement
signé encaisse pour de bon et le grand livre reste équilibré. Ce document ne
couvre que ce qui reste, et qui demande **vos** comptes — GitHub et Render.

Trois étapes, dans cet ordre.

---

## 1. Créer le dépôt sur GitHub

Ceci ne peut se faire que depuis votre compte : je n'entre pas de mot de passe
et je ne me connecte pas à vos services.

Sur <https://github.com/new>, créez un dépôt nommé **`famfer`** sous le compte
`Proptjrs`. **Ne cochez rien** — ni README, ni `.gitignore`, ni licence : le
dépôt local en a déjà, et une initialisation côté GitHub obligerait à fusionner
deux histoires sans ancêtre commun.

Laissez-le **privé** tant que le mémoire n'est pas soutenu. Il contient votre
travail avant que vous l'ayez présenté.

## 2. Pousser le code

Le dépôt local est prêt : branche `principale`, premier enregistrement fait,
`origin` déjà réglé sur l'adresse attendue.

Sa racine est `FamFer/` et non `FamFer/app/` : elle porte l'application dans
`app/`, les documents de conception dans `conception/`, et `render.yaml` à la
racine — c'est là que Render va le chercher.

```bash
cd C:/Users/DTS/Desktop/FamFer && git push -u origin principale
```

Windows ouvrira la fenêtre de connexion GitHub au premier envoi.

**Vérification à faire vous-même après le premier envoi :** ouvrez le dépôt sur
GitHub et cherchez `.env`. Il ne doit **pas** y être — seul `.env.example`, qui
ne contient aucune valeur. Le `.gitignore` l'exclut et j'ai contrôlé l'index
avant l'enregistrement, mais c'est le genre de chose qui se vérifie de ses
propres yeux.

## 3. Brancher Render

Sur <https://dashboard.render.com>, choisissez **Blueprints → New Blueprint
Instance**, puis le dépôt `famfer`. Render lit `render.yaml` et propose de créer
deux ressources :

| Ressource | Ce que c'est |
|---|---|
| `famfer-db` | la base PostgreSQL |
| `famfer` | le service web |

Les identifiants de base sont remplis par Render tout seul. **Deux variables
restent à saisir**, parce qu'elles ne doivent jamais figurer dans un dépôt —
c'est le sens du `sync: false` dans `render.yaml`.

### `APP_KEY` — sur le service web

Générez-la une fois :

```bash
cd C:/Users/DTS/Desktop/FamFer/app && php artisan key:generate --show
```

Une seule fois, et gardez-la : elle signe les sessions et les jetons de
réinitialisation de mot de passe. La régénérer déconnecterait tout le monde et
invalider ait les liens en cours.

Ne la rangez pas dans un fichier texte du projet.

### `APP_URL` — sur le service web

L'adresse que Render vous attribue, par exemple `https://famfer.onrender.com`.
Elle sert aux liens des courriels : sans elle, les boutons « Voir la commande »
pointeraient sur `localhost`.


---

## Ce que Render fait ensuite tout seul

Le conteneur démarre, applique les migrations, sème le catalogue, met en cache
la configuration, puis lance nginx, PHP-FPM et le travailleur de file d'attente.
La sonde `/up` confirme le démarrage sans interroger la base.

Chaque envoi vers `principale` déclenche une reconstruction et un remplacement
de la version en ligne. Le certificat HTTPS est délivré automatiquement.

## Pour une démonstration

`DONNEES_DEMO` vaut `false` en production. Le temps d'une présentation, passez-la
à `true` et redéployez : les six clients et leurs commandes livrées et notées
apparaissent. Remettez `false` ensuite.

Le catalogue, lui, est toujours semé : sans produits, aucune boutique ne peut
rien vendre.

Attention : le semis ne s'exécute que sur une base sans commandes. Il se refuse
de lui-même si la place de marché a déjà servi.

## Le plan gratuit, et ce qu'il coûte

L'hébergement gratuit met le service en veille après quinze minutes sans visite,
et le réveil prend de trente secondes à une minute. **Réveillez l'adresse cinq
minutes avant de présenter.**

La base gratuite de Render expire après trente jours. Si la soutenance est plus
loin, prévoyez de la recréer et de relancer le semis — ou de passer au plan
payant le temps de la présentation.

---

## Contrôles déjà passés

Ce qui suit a été vérifié sur le poste, conteneur en marche, et n'est donc pas à
refaire :

- l'image se construit en deux étages, sans Composer ni outils de compilation
  dans l'image finale ;
- le conteneur démarre : migrations, catalogue, caches, trois processus ;
- `/up`, l'accueil, la recherche, une fiche article et une boutique répondent
  toutes en 200 ;
- le travailleur de file d'attente tourne ;
- le catalogue et les commandes de démonstration se sèment au démarrage ;
- 77 tests, 456 assertions.
