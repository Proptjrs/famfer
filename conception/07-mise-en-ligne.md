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
trois ressources :

| Ressource | Ce que c'est |
|---|---|
| `famfer-db` | la base PostgreSQL |
| `famfer` | le service web |
| `famfer-delais` | la tâche qui applique les délais, toutes les cinq minutes |

Les identifiants de base sont remplis par Render tout seul. **Quatre variables
restent à saisir**, parce qu'elles ne doivent jamais figurer dans un dépôt —
c'est le sens du `sync: false` dans `render.yaml`.

### `APP_KEY` — sur le service web *et* sur la tâche

Générez-la une fois :

```bash
cd C:/Users/DTS/Desktop/FamFer/app && php artisan key:generate --show
```

Collez la même valeur aux deux endroits. **La même**, et une seule fois : c'est
elle qui déchiffre les données des vendeurs et des acheteurs. La régénérer plus
tard les rendrait définitivement illisibles.

Ne la rangez pas dans un fichier texte du projet.

### `APP_URL` — sur le service web

L'adresse que Render vous attribue, par exemple `https://famfer.onrender.com`.
Elle sert aux liens des courriels : sans elle, les boutons « Voir la commande »
pointeraient sur `localhost`.

### `PAIEMENT_CLE_API` et `PAIEMENT_SECRET_RAPPEL` — sur le service web

Elles viennent de l'opérateur de paiement, dans son interface de bac à sable.
Le secret de rappel est celui que vous déclarez chez lui pour qu'il signe ses
appels vers :

```
https://votre-adresse.onrender.com/rappel-paiement/wave
```

**Tant que `PAIEMENT_SECRET_RAPPEL` est vide, tout rappel est refusé.** C'est
délibéré : l'adresse est publique — elle doit l'être, l'opérateur n'ouvre pas de
session — et sans signature, un inconnu pourrait déclarer payées toutes les
commandes de la place de marché.

---

## Ce que Render fait ensuite tout seul

Le conteneur démarre, applique les migrations, sème le catalogue, met en cache
la configuration, puis lance nginx, PHP-FPM et le travailleur de file d'attente.
La sonde `/up` confirme le démarrage sans interroger la base.

Chaque envoi vers `principale` déclenche une reconstruction et un remplacement
de la version en ligne. Le certificat HTTPS est délivré automatiquement.

## Pour une démonstration

`DONNEES_DEMO` vaut `false` en production. Le temps d'une présentation, passez-la
à `true` et redéployez : les quatre quincailleries et leur passé de commandes
notées apparaissent. Remettez `false` ensuite.

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
- `php artisan famfer:delais` s'exécute dans l'image, et le planificateur
  l'annonce toutes les cinq minutes ;
- le travailleur de file d'attente tourne ;
- le rappel de paiement, en HTTP réel : signé il encaisse, rejoué il répond
  « déjà traité » sans recréditer, corps modifié après signature il refuse en
  401, sans secret il refuse ;
- après quoi le grand livre est équilibré, le séquestre justifié, aucune dette
  négative ;
- 138 tests, 681 assertions.
