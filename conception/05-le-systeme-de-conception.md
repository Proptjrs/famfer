# Le système de conception de FamFer

## Le point de départ : aucune feuille de style

L'application n'en avait pas. Tout tenait dans une balise `<style>` du gabarit,
complétée écran par écran par des attributs `style=` : environ quatre cents
déclarations dispersées dans trente-cinq vues, sans échelle commune.

Deux écrans voisins n'avaient ni la même marge, ni le même gris, ni le même
rayon. Surtout, **rien ne pouvait être corrigé en un seul endroit** : changer la
couleur du texte secondaire aurait demandé quatre-vingt-sept modifications.

## Ce que la mesure a révélé

Les défauts d'accessibilité n'ont pas été jugés à l'œil : ils ont été mesurés
dans le navigateur, sur le rendu réel.

| Défaut | Mesure | Seuil AA | Correction |
|---|---|---|---|
| Bouton principal, blanc sur orange | **2,2:1** | 4,5:1 | texte sombre sur orange — **7,4:1** |
| `--gris` (tout le texte secondaire) | **3,6:1** | 4,5:1 | nouveau plancher à **5,1:1** |
| Bouton du menu compte | **4,09:1** | 4,5:1 | collision de cascade corrigée |
| `:focus-visible` sur boutons et liens | absent | requis | une règle unique, partout |
| Lien d'évitement | absent | requis | premier élément de chaque page |

Le bouton du menu méritait qu'on s'y arrête : `.tiroir a` pèse `0,1,1` et
l'emportait sur `.btn` qui ne pèse que `0,1,0`. Le bouton « Se connecter »
héritait donc de la couleur des liens du menu. **Ce défaut ne se voyait pas** —
il fallait le mesurer.

De même, le choix « acheter ou vendre » de l'inscription s'appuyait sur
`--forge`, `--forge-pale` et `--acier-3` : trois jetons qui n'ont **jamais**
existé dans la feuille de style. La sélection n'était marquée par rien depuis le
premier jour.

## Les trois décisions qui portent le reste

**Les neutres tirent vers le bleu-gris**, pas vers le brun : c'est la couleur du
fer galvanisé, et elle laisse l'orange de la marque seul à porter la chaleur. Un
gris neutre pur aurait l'air de n'avoir pas été choisi.

**Le bouton principal porte du texte sombre sur l'orange vif.** Le blanc plafonne
à 2,2:1. Le texte sombre atteint 7,4:1 sans rien retirer à l'éclat de la marque —
c'est la convention des grandes places de marché, et elle est juste.

**Les figures sont en chasse fixe et en chiffres tabulaires.** Des montants qui
ne s'alignent pas d'une ligne à l'autre se comparent mal, et comparer est
précisément ce qu'un acheteur vient faire sur une place de marché.

## Trois familles, trois rôles

| Police | Rôle | Pourquoi |
|---|---|---|
| Inter | interface et texte courant | lisible aux petites tailles, neutre |
| Archivo | titres et logotype | grotesque un peu condensé, gras francs |
| IBM Plex Mono | prix, références, codes | chiffres tabulaires, allure technique |

## Les tableaux de bord

Ils alignaient des compteurs. Un chiffre sans point de comparaison n'aide à
décider de rien : « 412 000 F » ne dit pas si le mois est bon. **Un tableau de
bord qui ne sert pas à décider est une page d'accueil déguisée.**

Le service `Statistiques` produit désormais des séries mensuelles, des
répartitions et des variations à trente jours. Deux précautions de méthode
comptent plus que le code lui-même :

- **les séries sont complétées par des zéros.** Une courbe qui saute les mois
  creux dessine une progression régulière là où il y a eu un trou, et fait
  prendre une décision sur une forme qui n'existe pas ;
- **les variations se taisent sous cinq observations.** Passer d'une à deux
  ventes est une hausse de cent pour cent qui ne veut rien dire.

Les graphiques sont dessinés en SVG, **sans bibliothèque**. Une série de douze
valeurs ne justifie pas trois cents kilo-octets de dépendance, et une dépendance
servie par un réseau tiers ne se charge pas sur une connexion lente — c'est-à-dire
précisément là où se trouve une partie des acheteurs. L'échelle part toujours de
zéro : une échelle tronquée fait paraître énorme un écart de trois pour cent.

## Deux mensonges de l'interface retirés

Le pied de page annonçait « Wave et Orange Money ». L'application ne les gère
pas.

Pire : le formulaire de validation les **acceptait**. Une commande ainsi payée
était livrée, `paye` restait faux pour toujours — rien ne le remettait jamais à
vrai — et la commission devenait pourtant exigible. Le vendeur devait donc une
commission sur un argent qu'il n'avait peut-être jamais encaissé.

Les deux moyens restent affichés, **désactivés**, avec la raison : le paiement
mobile suppose un contrat marchand qui n'est pas signé. Une promesse que le
logiciel ne tient pas coûte plus cher qu'une option absente.

## Les pages d'erreur

Il n'y en avait aucune : un 404 affichait « Not Found », en anglais, sans marque,
sans navigation, sans issue.

Elles se répartissent en deux familles, et la distinction n'est pas cosmétique :

- **403, 404 et 419** surviennent alors que tout fonctionne. Elles héritent du
  site complet, parce qu'une page d'erreur dont on ne peut pas repartir est un
  cul-de-sac ;
- **500 et 503** sont autonomes, style compris. Le gabarit interroge la base pour
  composer la barre des rayons — or une erreur 500 est le plus souvent causée par
  une base indisponible. S'y appuyer déclencherait une seconde erreur à
  l'intérieur de la première.

Cela a révélé une fragilité réelle : le gabarit appelait `$errors->any()` sans
garde. Rendu avant le démarrage de la session, il échouait — la page censée
rattraper l'échec échouait elle-même.

## Le garde-fou

`InterfaceTest` vérifie ces règles sur le **HTML réellement produit** des
vingt-trois écrans : un seul `h1`, chaque champ étiqueté, chaque image avec son
`alt`, aucun jeton mort, le lien d'évitement en premier.

C'est nécessaire parce qu'**une régression d'accessibilité ne se voit pas** : un
champ qui perd son étiquette continue de s'afficher normalement, une image sans
alternative textuelle aussi. Seule une vérification automatique les attrape.

## Les polices sont servies par l'application

Elles venaient de Google Fonts. Trois raisons de les rapatrier, dans cet ordre.

**Technique.** Un tiers ajoute une résolution DNS, une poignée de main TLS et un
aller-retour avant que le premier caractère ne s'affiche dans sa vraie fonte. Sur
une connexion lente — c'est-à-dire chez une partie des acheteurs — cela se
compte en secondes.

**Fiabilité.** Le site ne dépend plus d'un service extérieur pour s'afficher
correctement.

**Confidentialité.** Chaque visiteur déclenchait une requête vers un serveur
tiers, qui voyait passer son adresse.

### Ce que cela coûte, mesuré

| | Fontes statiques | **Polices variables** |
|---|---|---|
| Fichiers | 18 | **10** |
| Poids total | 737 Ko | **280 Ko** |
| Réellement chargé sur l'accueil | — | **~110 Ko** |

Inter et Archivo sont embarquées en **polices variables** : une seule fonte
couvre toute la plage de graisse, là où il aurait fallu un fichier par graisse.
IBM Plex Mono ne l'étant pas, ses trois instances sont prises telles quelles.

Seuls `latin` et `latin-ext` sont embarqués. Le français a besoin des deux : le
« œ » de « cœur » vit en U+0153, hors du latin de base. Le navigateur ne
télécharge `latin-ext` que si la page contient un de ses caractères — c'est
pourquoi l'accueil n'en charge que quatre sur dix.

Deux fontes seulement sont **préchargées** : celles du premier écran. Toutes les
précharger ferait concurrence au HTML lui-même.

Les trois familles sont sous licence **SIL Open Font**, qui autorise
explicitement cette redistribution. Leurs auteurs sont crédités sur `/credits`,
comme les illustrations : citer n'est pas une politesse, c'est une condition de
la licence.

### Un piège rencontré

Un téléchargement interrompu a laissé un fichier de **zéro octet** — celui
d'Inter, le plus important. Rien ne l'a signalé : une déclaration `@font-face`
qui pointe vers un fichier absent ou tronqué ne lève aucune erreur, le navigateur
retombe silencieusement sur la police de repli.

`InterfaceTest` vérifie désormais que chaque fonte déclarée existe, dépasse un
kilo-octet et commence bien par la signature `wOF2`.

## Ce qui reste ouvert

Rien côté interface. Les limites qui subsistent sont contractuelles et non
techniques : le paiement mobile suppose un contrat marchand avec l'opérateur, et
l'envoi réel de SMS un contrat opérateur. Les deux mécanismes sont écrits et
essayés ; seul le raccordement manque.
