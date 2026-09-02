# Modèle de données

---

## Les acteurs

| Table | Ce qu'elle porte |
|---|---|
| `utilisateurs` | identité, rôle, adresse, téléphone, mot de passe |
| `vendeurs` | la quincaillerie : raison sociale, NINEA, adresse, position, statut de vérification, note moyenne |
| `acheteurs` | particulier, chantier ou entreprise ; adresses de livraison |
| `livreurs` | facultatif au début ; le vendeur peut livrer lui-même |

Rôles : `acheteur`, `vendeur`, `livreur`, `administrateur`.

Un vendeur n'est visible des acheteurs qu'une fois **vérifié** par
l'administration — pièce d'identité, NINEA, visite ou justificatif d'activité.

## Le référentiel des articles

C'est le bien commun de la plateforme. Aucun vendeur ne le modifie.

**`familles`** — fer à béton, tôles, tubes, profilés, quincaillerie, pièces
détachées. Arborescence à deux niveaux.

**`articles`** — la référence nationale.

| Colonne | Exemple |
|---|---|
| `designation` | Fer à béton haute adhérence T10 |
| `famille_id` | fer à béton |
| `unite_pivot` | `gramme` |
| `caracteristiques` | `{diametre_mm: 10, longueur_mm: 12000, masse_lineique_g_m: 617}` |
| `photo` | image de référence |

**`unites_vente`** — comment cet article peut se vendre, et le facteur vers le
pivot.

| `article_id` | `unite` | `facteur_vers_pivot` |
|---|---|---|
| T10 | barre | 7 404 (g) |
| T10 | kilogramme | 1 000 |
| T10 | tonne | 1 000 000 |

Toute quantité est stockée **en pivot, en entier**. Aucun flottant : les erreurs
d'arrondi sur des tonnes de fer coûtent cher.

## Les offres

**`offres`** — ce qu'un vendeur propose, pour un article du référentiel.

| Colonne | Rôle |
|---|---|
| `vendeur_id`, `article_id` | la paire est unique |
| `prix_par_unite`, `unite_affichee` | 4 200 F la barre |
| `quantite_pivot` | le stock disponible, en pivot |
| `quantite_reservee_pivot` | retenue par des commandes en cours |
| `actif` | le vendeur peut suspendre une offre |
| `delai_preparation_h` | pour annoncer une date de livraison honnête |

Le **disponible réel** vaut `quantite_pivot − quantite_reservee_pivot`. Il n'est
jamais négatif : c'est une contrainte de base, pas seulement une vérification
applicative.

## Les mouvements de stock

Comme pour l'argent, le stock est un **journal**, pas un compteur.

**`mouvements_stock`** — `offre_id`, `type`, `quantite_pivot` signée, `motif`,
`commande_id`, `auteur_id`, horodatage.

Types : `approvisionnement`, `reservation`, `liberation`, `sortie_vente`,
`retour`, `regularisation_inventaire`.

`offres.quantite_pivot` est un **cache** recalculable : la somme des mouvements
doit toujours l'égaler. Une commande de vérification le contrôle chaque nuit.

## Les commandes

**`commandes`** — `acheteur_id`, `vendeur_id`, `etat`, `mode_remise`
(retrait ou livraison), adresse, montants, horodatages de chaque transition.

Une commande ne concerne **qu'un seul vendeur**. Un panier réparti sur trois
quincailleries produit trois commandes : trois livraisons, trois séquestres,
trois reversements. C'est plus simple et c'est plus juste.

**`lignes_commande`** — `offre_id`, `quantite_pivot`, `unite_affichee`,
`quantite_affichee`, `prix_unitaire_fige`, `montant`.

Le prix est **figé à la commande**. Si le vendeur change son tarif le lendemain,
la commande passée n'en dépend pas.

## L'argent

**`ecritures`** — le grand livre, détaillé dans `04-grand-livre.md`.

**`paiements`** — `commande_id`, `operateur`, `reference_externe`, `montant`,
`etat`, `cle_idempotence`.

**`reversements`** — `vendeur_id`, `periode`, `montant`, `etat`, référence du
virement.

## La confiance

**`evaluations`** — note et commentaire, écrits par l'acheteur **après réception
confirmée** seulement. Une note sans achat n'a aucune valeur.

**`litiges`** — `commande_id`, motif, pièces jointes, état, décision, arbitre.
Un litige **gèle le reversement** tant qu'il n'est pas tranché.

## Ce qui est indexé

La recherche est le premier geste de l'acheteur. Elle doit être immédiate.

- `offres (article_id, actif)` — comparer les prix d'un même article ;
- `vendeurs (latitude, longitude)` — trouver les plus proches ;
- `articles` — recherche plein texte sur la désignation et les synonymes
  (« fer 10 » doit trouver le T10) ;
- `commandes (vendeur_id, etat)` — le tableau de bord du vendeur ;
- `ecritures (compte, cree_le)` — le calcul des soldes.
