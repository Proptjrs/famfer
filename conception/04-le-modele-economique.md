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
