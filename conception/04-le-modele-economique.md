# Le modèle économique de FamFer

## Le point de départ : la plateforme ne gagnait rien

À la reprise du projet, la place de marché fonctionnait — catalogue, panier,
commande, livraison, avis — mais **aucune commission n'existait nulle part**. Ni
sur la boutique, ni sur la commande, ni sur la ligne de commande. Vérifié
colonne par colonne : le mot n'apparaissait dans aucune table.

Une place de marché sans revenu n'est pas une place de marché, c'est un
annuaire. C'est ce qui a été rapatrié de la version à séquestre (`v1-sequestre`),
adapté au paiement à la livraison.

## Ce que change le paiement à la livraison

Les deux modèles ne font pas circuler l'argent dans le même sens.

| | Séquestre (v1) | Paiement à la livraison (actuel) |
|---|---|---|
| Qui encaisse le client | la plateforme | **le vendeur** |
| Qui détient l'argent | la plateforme | le vendeur |
| Comment la plateforme se paie | elle **retient** sa part et reverse le solde | elle **facture** le vendeur après coup |
| Nature comptable | une retenue | une **créance** |

La plateforme ne voit jamais l'argent. Elle ne peut donc rien retenir : elle
tient un compte de ce que chaque boutique lui doit, et le lui présente.

C'est exactement ce que fait Jumia sur ses commandes payées à la livraison : le
vendeur encaisse, la réconciliation vient ensuite.

## Les trois règles du calcul

**1. La commission ne porte que sur la marchandise.**
Les frais de livraison couvrent une tournée que le vendeur paie de sa poche. En
prélever une part reviendrait à taxer son carburant. Le port lui revient
entièrement.

**2. Le taux est figé au moment de la commande.**
Comme le prix, comme le nom du produit, comme l'adresse de livraison. Un taux
renégocié le mois prochain ne refacture pas une vente déjà conclue. Une
plateforme qui réécrit ses factures d'hier quand elle change son tarif
d'aujourd'hui n'est pas défendable devant un commerçant.

**3. La commission n'est due qu'à la livraison.**
Une commande refusée à la porte, annulée ou retournée ne coûte **rien** au
vendeur. Il a déjà perdu la tournée ; lui facturer en plus la commission d'une
vente qui n'a pas eu lieu serait le punir deux fois. C'est le coût que la
plateforme accepte pour permettre le paiement à la livraison — et il est chiffré
sur le tableau des revenus, sous « perdue sur refus et retours ».

## Comment sait-on qu'il a vraiment payé ?

C'est la question qui décide de la survie du modèle, et la première version n'y
répondait pas. « Livrée » et « refusée » étaient toutes deux déclarées par le
vendeur, et par lui seul. Il était donc l'unique témoin d'un fait sur lequel il
avait intérêt à mentir : livrer, encaisser les espèces à la porte, puis déclarer
« refusée ». Il gardait l'argent, le stock lui revenait, et la règle « un refus
ne coûte rien » — écrite en faveur du commerçant honnête — lui offrait la
commission par-dessus le marché.

Le paiement à la livraison n'a pas de tiers de confiance : c'est ce qui le rend
accessible, et ce qui le rend fragile. À défaut de séquestre, on fait témoigner
les deux parties.

### 1. Le code de remise

L'expédition tire six chiffres au hasard, communiqués **au client seul** — sur son
suivi de commande et dans le courriel de départ. Le vendeur ne peut pas clôturer
sans ce code, que l'acheteur ne dicte qu'en recevant le colis et en payant.

Le code n'apparaît jamais sur l'écran du vendeur : sinon il le recopierait sans
avoir vu personne, et la preuve ne prouverait plus rien. Un essai le vérifie.

### 2. La confirmation du client

Le client peut déclarer de son côté « j'ai reçu et j'ai payé ». Sa parole vaut
celle du vendeur : si un commerçant a encaissé puis annoncé un refus, l'acheteur
le contredit et la commission redevient due.

C'est aussi utile sans aucune fraude — un vendeur débordé qui ne clôture jamais
laisserait ses clients sans possibilité de noter.

### 3. Le litige, et l'arbitrage

Quand les deux versions divergent, la commande passe en **litige** et
l'administration tranche vers « livrée », « refusée » ou « annulée ». L'état
contesté est conservé, sans quoi l'arbitre ne saurait pas ce qui était affirmé au
départ. Le dossier lui montre l'indice le plus fort : **le code a-t-il été
remis ?** S'il l'a été, la livraison a matériellement eu lieu.

Les deux camps peuvent ouvrir un litige. Sans le recours du vendeur, le dispositif
serait déséquilibré : un client de mauvaise foi garderait la marchandise,
refuserait de dicter le code, puis nierait avoir reçu.

Tant que le litige dure, **aucune commission n'est due**.

### 4. Le taux de refus par boutique

Le code couvre une commande ; ce taux couvre le commerçant. Un vendeur qui
déclare des refus fictifs le fait monter, seul, pendant que ses concurrents
restent bas. Les boutiques de moins de cinq commandes closes n'y figurent pas :
deux refus sur trois ventes est le lot d'un débutant malchanceux, pas un indice.

### Ce que cela ne couvre pas

Un vendeur et un client **de connivence** peuvent toujours déclarer un faux refus
tous les deux. Aucun mécanisme logiciel ne les départagera : seul le taux de refus
finit par les désigner, et la sanction est alors la suspension de la boutique. Une
place de marché en espèces ne peut pas faire mieux sans encaisser elle-même.

## Le taux se négocie

Il est stocké **par boutique**, en pour mille (`80` = 8 %), et l'administration
peut le changer depuis la page des revenus. Une enseigne démarchée qui apporte du
volume n'a aucune raison de payer comme un nouveau venu.

Taux posés à la démonstration :

| Boutique | Taux |
|---|---|
| Quincaillerie Ndiaye & Frères *(officielle)* | 6 % |
| Établissements Sow Métaux *(officielle)* | 6,5 % |
| Comptoir du Fer Dakarois | 8 % |
| Fer Express Thiaroye | 8 % |

Conséquence : le **taux moyen réellement obtenu** diverge du taux affiché au
contrat. C'est cet écart que mesure le tableau de bord de l'administration — avec
un taux unique, l'indicateur ne dirait rien que le contrat ne dise déjà.

Sur un panier réparti entre plusieurs boutiques, chaque ligne porte le taux de sa
boutique. Le taux inscrit sur la commande est la moyenne effectivement appliquée,
déduite et non choisie.

## Un exemple complet

Un client de Dakar commande pour 40 000 F chez Ndiaye & Frères (6 %).
Sous le seuil de 50 000 F, le port de Dakar s'applique : 1 500 F.

| | Montant |
|---|---|
| Marchandise | 40 000 F |
| Livraison | 1 500 F |
| **Le client paie au livreur** | **41 500 F** |
| Commission FamFer (6 % de 40 000) | 2 400 F |
| **Le vendeur garde** | **39 100 F** |

Si le client refuse le colis à la porte : le vendeur perd sa tournée, le stock
revient en rayon, et il ne doit **rien** à FamFer.

## Ce que gagne chaque acteur

**Le client** ne paie aucune commission. Il paie la marchandise et le port,
en espèces, à la réception — donc après avoir vu le colis. Il gagne la
comparaison des prix entre quatre boutiques sur le même article, la livraison
gratuite au-delà de 50 000 F, et le droit de refuser à la porte sans rien devoir.

**Le vendeur** encaisse la totalité : marchandise et port. Il reverse ensuite la
commission sur ses seules ventes livrées. Il gagne une vitrine, un catalogue
référencé, des clients qu'il n'aurait pas démarchés, et la gestion de ses
commandes — sans site à construire ni paiement en ligne à contracter.

**FamFer** gagne la commission, entre 6 et 8 % de la marchandise livrée. Rien
d'autre : pas de frais d'inscription, pas d'abonnement, pas de marge sur le port.
La plateforme ne gagne que si le vendeur a réellement vendu.

## Où cela se voit dans l'application

| Écran | Qui | Ce qu'il montre |
|---|---|---|
| `/vendeur/commissions` | le vendeur | encaissé, commission, net, et le relevé mois par mois |
| `/administration/revenus` | l'administration | commission acquise, taux moyen obtenu, perte sur refus, et le taux de chaque boutique — modifiable |
| `/administration` | l'administration | la commission acquise en carte de tableau de bord |

## Une réserve honnête

Le port n'est attribué à une boutique que sur les commandes dont elle est le
**seul** fournisseur. Une commande ne porte qu'un seul frais de livraison : le
compter en entier pour chacune des enseignes qu'elle traverse inventerait de
l'argent, la même somme apparaissant deux fois.

Les commandes partagées supposent donc une tournée groupée, c'est-à-dire un
accord entre vendeurs que le logiciel ne peut pas deviner. Mieux vaut ne rien
attribuer que de créditer à tort. C'est une limite assumée, pas un oubli.

## Ce qui reste hors du logiciel

Le **recouvrement** de la commission n'est pas informatisé : l'application dit ce
qui est dû, elle ne l'encaisse pas. Un vrai déploiement exigerait un prélèvement
Wave ou Orange Money, donc un contrat commercial avec l'opérateur — hors de
portée d'un projet académique. La facturation reste, à ce stade, un document que
la plateforme présente et que le vendeur règle par ses propres moyens.
