# État des lieux — ce qui est construit

Document de suivi, tenu à jour au fur et à mesure. Il dit ce qui existe
réellement dans `app/`, ce qui l'éprouve, et ce qui reste.

**Au 3 septembre 2026 : 172 tests, 778 assertions, tous verts sur PostgreSQL.**

---

## Le socle métier

| Brique | Où | Ce qui l'éprouve |
|---|---|---|
| Conversion d'unités, exacte à l'entier | `ConversionUnites` | `ConversionUnitesTest` |
| Stock tenu comme un journal | `StockService`, `mouvements_stock` | `OffresEtStockTest` |
| Verrouillage pessimiste, deux connexions réelles | `StockService::reserver` | `ConcurrenceTest` |
| Machine à états à table explicite | `Commande::TRANSITIONS`, `CommandeService::transiter` | `CommandeTest`, `DelaisTest` |
| Grand livre en partie double, quatre invariants | `GrandLivre` | `GrandLivreTest` |
| Idempotence par contrainte d'unicité + réconciliation | `PaiementService` | `PaiementTest` |
| Litiges, gel du reversement, arbitrage | `LitigeService`, `ReversementService` | `LitigeEtReversementTest` |
| Pilotage vendeur et plateforme | `PilotageService` | `PilotageTest` |

## Ce que la comparaison avec les places de marché existantes a fait ajouter

Sept manques relevés en confrontant FamFer à Jumia, Alibaba et Yango. Les sept
sont comblés.

### 1. Les évaluations — `EvaluationService`

La table existait, la note des vendeurs était semée en dur, et **rien ne la
calculait jamais**. Pire : `note_sur_cent` n'ayant jamais figuré dans le
`fillable` du vendeur, les notes semées étaient silencieusement jetées — la
colonne n'avait jamais rien contenu.

Ce qui tient désormais :

- une note n'existe qu'adossée à une **commande reçue** — sans achat, une note
  ne vaut rien, et les places de marché qui l'oublient voient les concurrents se
  descendre entre eux ;
- **une seule note par commande** ;
- la moyenne est **recalculée**, jamais saisie ; les deux colonnes restent hors
  du `fillable`, de sorte qu'aucun formulaire ne puisse jamais écrire sa propre
  réputation.

Éprouvé par `EvaluationTest` (7 tests).

### 2. La fiche publique du vendeur — `/vendeur/{id}`

On demande à l'acheteur de confier son argent à un séquestre avant d'avoir vu la
marchandise. Il doit pouvoir regarder chez qui il commande : note, nombre
d'avis, avis écrits, ancienneté, commune, stock tenu. Une maison non vérifiée
n'a pas de vitrine — la page renvoie 404.

### 3. Filtres et pagination — `RechercheService::paginer`

Famille, prix maximum, pagination écrite à la main (les gabarits de Laravel
supposent Tailwind, absent du projet). Le filtre de prix porte sur la
**meilleure offre visible**, donc sur une donnée calculée : il s'applique après
la requête plutôt que de dupliquer la règle de visibilité en SQL.

Éprouvé par 4 tests dans `RechercheTest`.

### 4. Les frais de livraison — `LivraisonService`

La colonne existait et valait zéro : la plateforme faisait livrer du fer
gratuitement. Le fer n'est pas un colis — c'est le **poids autant que la
distance** qui fait le prix. Trois termes : prise en charge fixe, tarif
kilométrique, tarif à la tonne-kilomètre. Refus au-delà du rayon ou de la charge
utile d'un camion, plutôt qu'un montant fantaisiste.

Deux propriétés comptent plus que le barème, qui se négocie :

- **la commission porte sur la marchandise seule** — prélever 8 % du carburant
  que le vendeur avance ne se défend pas ;
- **le devis affiché au panier est celui qui sera facturé**, au franc près : le
  navigateur ne calcule rien, il demande au même service.

Éprouvé par `LivraisonTest` (8 tests).

### 5. Le compte et le mot de passe oublié

Un commerçant qui perd son mot de passe perd son stock, ses commandes en cours
et l'argent qui l'attend au séquestre : **il n'y a aucune autre porte**. Ce
chemin n'est pas une finition.

- profil : coordonnées, adresse de livraison, position relevée ;
- changement de mot de passe **avec l'ancien exigé**, et déconnexion des autres
  appareils ;
- réinitialisation par jeton d'une heure, à usage unique ;
- une adresse inconnue reçoit **la même réponse** qu'une adresse connue, sans
  quoi le formulaire permettrait d'énumérer les inscrits.

Éprouvé par `CompteTest` (10 tests).

### 6. Les notifications — `NotificationService`

Le vendeur a deux heures pour accepter une commande payée. S'il n'est pas
prévenu, ce délai ne veut rien dire et la commande expire toute seule : le
courriel fait partie de la mécanique.

L'accroche est `CommandeService::transiter` — le goulot par où passe déjà
chaque changement d'état et chaque ligne de journal. Deux précautions :

- **rien ne part avant la validation de la transaction** (`DB::afterCommit`) :
  un courriel envoyé depuis une transaction ensuite annulée annonce un fait qui
  n'a pas eu lieu, et l'on ne rattrape pas un courriel parti ;
- **une messagerie en panne ne fait pas échouer une vente** : l'échec est
  journalisé, la commande continue. Arbitrage délibéré — mieux vaut un acheteur
  non prévenu qu'un paiement perdu.

L'administration prévient aussi le demandeur de sa décision d'inscription : une
demande sans réponse est ce qui décourage le plus sûrement une quincaillerie de
revenir.

Éprouvé par `NotificationTest` (5 tests), dont celui du retour arrière.

### 7. L'argent et le stock, vus par le vendeur

- `/commerce/argent` : disponible **lu dans le grand livre** et non recalculé
  depuis les commandes, commandes reçues en attente de solde, litiges gelants,
  historique paginé des virements ;
- `/commerce/offre/{id}/journal` : chaque mouvement, avec le solde recalculé
  ligne à ligne. C'est la contrepartie du choix de ne pas tenir de compteur — si
  le cumul ne tombe pas sur le stock affiché, la page le dit en rouge.

---

## Les données de démonstration

`HistoriqueSeeder` mène six commandes de bout en bout **par les services de
l'application** — création, paiement, acceptation, remise, réception, note. Les
étoiles affichées proviennent donc de vraies ventes : le grand livre reste
équilibré après le semis, et les stocks concernés ont réellement baissé.

```bash
php artisan db:seed
```

---

## Ce qui reste

- **Le terrain.** Deux ou trois quincailliers, deux chefs de chantier, les prix
  réels du marché. Rien de ce qui précède ne remplace cette enquête.
- **La mise en ligne** (étape 10) : Docker, hébergement, sauvegardes.
- **Le mémoire lui-même** : problématique, état de l'art, méthodologie.

Volontairement laissés de côté, et à assumer devant un jury comme des choix :
application mobile native, livreurs indépendants avec suivi temps réel,
facturation fiscale normalisée.

---

## Étape 10 — la mise en ligne

Les fichiers de déploiement existaient déjà. La vérification en a sorti **six
défauts**, tous silencieux : rien n'aurait signalé la panne avant le premier
déploiement, et pour trois d'entre eux, avant plusieurs jours d'exploitation.

**Le planificateur était vide.** `famfer:delais` existait, testée et juste, mais
aucune ligne ne l'inscrivait : le cron lançait `schedule:run` sur du néant. Les
trois délais du cycle — quinze minutes pour payer, deux heures pour accepter,
soixante-douze heures pour confirmer — ne se seraient jamais déclenchés en
ligne. Un panier abandonné aurait retenu le stock d'un vendeur indéfiniment.
`PlanificateurTest` vérifie désormais l'inscription elle-même, pas seulement ce
que la commande fait.

**Le seeder appelé au démarrage n'existait pas.** L'entrypoint lançait
`ReferentielSeeder` ; les seeders s'appellent `CatalogueSeeder`,
`VendeursSeeder`, `HistoriqueSeeder`. L'échec était avalé par un `|| echo` et le
service démarrait sur une base sans un seul article — donc sans qu'aucun vendeur
puisse publier une offre. Le nom est corrigé et l'échec arrête maintenant le
démarrage.

**Le cron n'avait pas d'identifiants.** Ses variables venaient d'un
`fromGroup: famfer-partage` qui n'est défini nulle part. Elles pointent
maintenant sur la même base que le service web.

**Aucun `.dockerignore`.** Le `COPY . .` emportait le `.env` du poste de
développement — `APP_KEY` et mot de passe en clair — dans l'image. Laravel lit ce
fichier avant les variables du service : le conteneur déployé aurait cherché la
base sur `127.0.0.1`, et `config:cache` aurait figé ces valeurs. Il emportait
aussi les 76 Mo de `vendor` construits sous Windows, qui écrasaient ceux que
Composer venait d'installer proprement, dépendances de développement comprises.

**Le port était écrit en dur.** nginx écoutait 8080 quand l'hébergeur impose le
sien par `PORT`. Un serveur qui écoute ailleurs est déclaré en panne et
redémarré en boucle. La configuration est devenue un modèle, et `envsubst` y
pose le port au démarrage — avec la liste de variables explicite, sans quoi les
`$uri` de nginx auraient été effacés eux aussi.

**La sonde de santé interrogeait la base.** `healthCheckPath` valait `/`, la page
de recherche, qui lit le catalogue. Une base momentanément lente aurait fait
déclarer le service en panne. C'est `/up` désormais, la sonde de Laravel.

Enfin, l'entrypoint refuse de démarrer sans `APP_KEY` plutôt que d'en laisser
Laravel fabriquer une : une clé neuve à chaque redémarrage rendrait illisible
tout ce que la précédente avait chiffré.

## Le rappel de l'opérateur de paiement

Il manquait la porte par laquelle l'argent entre. Le service savait traiter un
rappel — idempotence par contrainte d'unicité, réconciliation nocturne — mais
aucune route ne pouvait en recevoir un : l'argent n'entrait que par un bouton de
l'application appelant le service en direct.

`POST /rappel-paiement/{operateur}` est la seule adresse de la plateforme qui
s'ouvre sans session : l'opérateur n'est pas un utilisateur, il ne se connecte
pas. Tout ce qui la protège tient donc dans une signature HMAC-SHA256 portant
sur **l'horodatage et le corps joints**. Signer le corps seul laisserait rejouer
un enregistrement capturé ; signer l'horodatage seul ne protégerait rien.

Trois points méritent d'être défendus devant un jury :

- **Le secret vide ferme la porte au lieu de l'ouvrir.** Dégrader en « pas de
  secret, pas de vérification » ferait accepter tous les rappels du monde à un
  déploiement où l'on aurait oublié la variable.
- **`hash_equals` et non `===`.** Une comparaison ordinaire s'arrête au premier
  caractère qui diffère, et le temps qu'elle met à répondre laisse deviner la
  signature attendue, octet par octet.
- **Le code de réponse fait partie du protocole.** 401 sur signature invalide,
  404 sur commande inconnue, 200 sur rappel accepté — y compris déjà traité,
  sans quoi l'opérateur rappellerait indéfiniment.

`RappelPaiementTest` éprouve la requête hostile plutôt que le chemin heureux :
signature absente, fausse, périmée, corps modifié après signature, rappel
rejoué, montant qui ne correspond pas, commande inconnue, secret non configuré.

**Au 2 septembre 2026 : 138 tests, 681 assertions.**

## Le menu, comme sur les places de marché existantes

Le catalogue ne s'atteignait qu'en tapant le bon mot : il n'y avait pas de
navigation par familles, et la recherche ne vivait que sur l'accueil.

Une barre de rayons court désormais sous l'en-tête — les sept familles avec leur
nombre d'articles, la famille courante soulignée — et la recherche a rejoint
l'en-tête, donc toutes les pages. Les familles viennent d'un *view composer*
plutôt que de chaque contrôleur : c'est ce qui garantit que la barre ne
disparaîtra pas d'un écran par oubli, et la requête est mise en cache une heure
parce que le référentiel change quelques fois par an.

Sur téléphone, la barre défile horizontalement et cesse d'être collante — un
en-tête qui a grandi la ferait chevaucher le menu.

## Construire et faire tourner l'image

`docker compose up -d --build` a fini par démarrer. Le chemin y a coûté **cinq
défauts de plus**, dont aucun n'était visible sans exécuter le conteneur :

- **PostgreSQL 18 a déplacé son point de montage.** Le volume se pose désormais
  sur `/var/lib/postgresql` et non sur son sous-dossier `data` ; l'image refuse
  de démarrer autrement, pour pouvoir ranger les données par version.
- **Le port 8080 est réservé par Windows.** Il figure dans les plages
  d'exclusion de `netsh` : le démon ne peut pas s'y attacher, et l'erreur parle
  de permissions, pas de port occupé. Le poste expose 8090, le conteneur garde
  8080.
- **Les fins de ligne.** `entrypoint.sh` écrit sous Windows portait des CRLF :
  le shebang devenait `#!/bin/sh` et le noyau répondait
  « no such file or directory » — en désignant l'interpréteur, pas le script,
  ce qui envoie chercher l'erreur au mauvais endroit. Corrigé dans le fichier,
  puis dans le Dockerfile et dans `.gitattributes`, pour que cela ne revienne
  pas.
- **La garde APP_KEY a fonctionné** : le conteneur a refusé de démarrer, comme
  prévu. Une clé de développement, jetable et annoncée comme telle, vit
  maintenant dans le compose.
- **Le journal appartenait à root.** L'entrypoint s'exécute en root et crée
  `laravel.log` ; PHP-FPM tourne ensuite en `www-data` et ne peut plus y écrire.
  La première ligne de journal produisait une erreur 500 — et l'erreur qui
  tentait de se journaliser en produisait une autre. L'entrypoint rend
  maintenant la main à `www-data` avant de lancer les serveurs.

Le conteneur sert `/up`, l'accueil, la recherche, une fiche article et une
boutique en 200. Le rappel de paiement a été éprouvé en HTTP réel contre le
conteneur : signé il encaisse, rejoué il répond « déjà traité » sans recréditer,
corps gonflé après signature il refuse en 401. Après quoi le grand livre est
équilibré, le séquestre justifié, aucune dette négative.

## Gestion de versions

Le projet est désormais un dépôt Git — branche `principale`, 156 fichiers
suivis, un premier enregistrement. Contrôle fait avant : aucun secret n'entre
dans le dépôt, seul `.env.example` y figure, et il est vide de valeurs.

## Ce qui reste

- **Pousser le dépôt** vers GitHub, puis brancher Render dessus : la description
  de l'infrastructure est écrite et validée, mais le service n'existe pas encore.
- **Le terrain.** Deux ou trois quincailliers, deux chefs de chantier, les prix
  réels du marché.
- **Le mémoire lui-même** : problématique, état de l'art, méthodologie.

---

## Le rôle porté par un compte, et ce que la plateforme prélève

Deux manques que la relecture des parcours a fait apparaître.

**Rien n'indiquait à un utilisateur ce qu'il était.** Acheteur, vendeur en
attente de vérification, commerçant vérifié, administration : il fallait le
deviner à la présence d'un lien dans la barre du haut. La page du compte porte
maintenant un bloc « Mon rôle sur FamFer » qui l'annonce, et surtout qui donne
la suite : déposer une demande quand on n'est qu'acheteur, préparer ses offres
pendant la vérification, le motif quand la maison est suspendue.

**Un vendeur ne voyait nulle part ce que FamFer retenait sur ses ventes.** La
plateforme encaisse à sa place et garde une commission ; ne pas la lui montrer,
c'est lui demander une confiance qu'on ne lui rend pas. Le partage est
désormais affiché en trois lignes, sur sa page d'argent comme sur son compte :

| | |
|---|---|
| Vos ventes abouties | le brut, tout compris |
| Commission FamFer, 8 % | **sur la marchandise seule, jamais sur la livraison** |
| Ce qui vous revient | le net, frais de livraison inclus — ils sont à lui |

Avec ce que la page promet, et que le code tient : rien à l'inscription, rien à
la publication d'une offre, et la commission due seulement une fois la commande
reçue — une vente annulée, expirée ou remboursée ne coûte rien.

`RoleEtCommissionTest` n'éprouve pas la mise en page mais le chiffre : la
commission affichée est comparée à celle du grand livre, le brut moins la
commission doit tomber sur le net, une vente annulée doit afficher zéro, et les
deux pages doivent dire la même chose. Une page qui annoncerait une commission
calculée autrement que celle réellement prélevée serait pire que pas de page.

**Au 3 septembre 2026 : 146 tests, 707 assertions.**

## Le dépôt est en ligne

<https://github.com/Proptjrs/famfer> — branche `principale`, suivi réglé, local
et distant identiques. Reste à brancher Render dessus : voir
[07-mise-en-ligne.md](07-mise-en-ligne.md).

---

## La revue « comme les autres places de marché »

Sept points repris d'un coup, à la demande. Le premier est de loin le plus
grave, et il ne se voyait nulle part.

### L'administration était ouverte à tout compte connecté

Le groupe `administration/` n'était gardé que par `auth`. La colonne `est_admin`
existait depuis le début du projet, et **rien ne la lisait jamais**. N'importe
quel utilisateur connecté — un acheteur, un vendeur concurrent — pouvait donc :

- vérifier sa propre inscription sans que personne ne regarde son dossier ;
- suspendre une autre quincaillerie ;
- fixer sa propre commission à zéro ;
- **trancher un litige en sa faveur**, ce qui déplace de l'argent réel du
  séquestre vers un compte.

Un middleware `EstAdministrateur` ferme désormais le groupe, et journalise les
tentatives : sur une plateforme qui détient des fonds, savoir qui a essayé
d'entrer vaut autant que de l'en empêcher. `AdministrationFermeeTest` pousse
chaque porte séparément — une seule oubliée suffirait — et vérifie les
conséquences, pas seulement le code de retour : après une tentative
d'arbitrage, le séquestre et la dette envers le vendeur doivent être au franc
près ce qu'ils étaient.

Ce garde-fou a immédiatement débusqué une erreur dans un test antérieur, qui
écrivait `est_administrateur` au lieu de `est_admin` : il passait précisément
parce que rien ne vérifiait le rôle.

### La page d'argent promettait ce que la base ne pouvait pas tenir

« Le montant part vers le compte Wave ou Orange Money enregistré à votre nom » —
aucun champ ne portait ce compte. Quatre colonnes l'accueillent maintenant, et
`ReversementService::preparer` **refuse** sans destination : l'écriture qui
suit éteint la dette envers le vendeur, et la passer sans savoir où envoyer les
fonds effacerait ce qu'on lui doit sans le lui avoir versé — le grand livre
resterait équilibré, donc la faute serait invisible.

Tout changement de ce compte est horodaté et signalé par courriel, l'ancien
compte rappelé dans le message : c'est le premier geste d'un intrus, et le
vendeur doit l'apprendre le jour même plutôt que le jour où l'argent n'arrive
pas.

### La connexion n'était pas limitée en tentatives

Cinq essais par minute et par adresse sur la connexion comme sur l'inscription.
Sans cela, un robot essaie des mots de passe indéfiniment sur une plateforme qui
détient l'argent des gens.

### Le vendeur ne voyait pas son passé

Le tableau de bord ne montrait que « payée, acceptée, prête » : une commande
remise disparaissait de son écran, et rien n'expliquait une annulation. Une page
paginée montre tout, filtrable par état, avec le motif d'annulation, la
commission prélevée et la note reçue sur chaque ligne.

### Le taux de commission n'était pas négociable

La colonne existait par vendeur ; tout le monde payait 8 % faute de moyen d'y
toucher. L'administration le fixe désormais entre 0 et 20 %. Le nouveau taux ne
vaut que pour l'avenir : chaque commande fige le sien à sa création, et
recalculer les anciennes changerait après coup ce que le vendeur a déjà encaissé.

### Il n'y avait pas de conditions générales

Une place de marché qui séquestre des fonds doit dire ce qu'elle en fait. Dix
articles : le séquestre et sa nature de dette, les trois délais, ce que la
commission prélève et ce qu'elle ne prélève pas, le gel en cas de litige, la
règle des avis, les données.

### Le paiement se faisait passer pour réel

Le bouton « Régler » appelle le service interne : aucun franc ne bouge. Tant
qu'aucune clé d'opérateur n'est configurée, l'écran le dit maintenant en toutes
lettres. C'était le seul mensonge de l'application.

**Au 3 septembre 2026 : 172 tests, 778 assertions.**
