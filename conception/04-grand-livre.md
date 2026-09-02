# Le grand livre

C'est le cœur du mémoire. Tout le reste peut se raconter ; ceci se démontre.

---

## Le principe

Aucune colonne `solde` que l'on modifie. Des **écritures immuables**, dont on
fait la somme.

```
ecritures
  id            identifiant
  operation_id  ce qui regroupe les lignes d'un même mouvement
  compte        sequestre | vendeur:12 | commission | frais_paiement | wave
  sens          debit | credit
  montant       en francs CFA, entier — jamais de flottant
  commande_id   la commande concernée, si elle existe
  libelle       « encaissement commande #4192 »
  cree_le       horodatage, jamais modifié
```

Rien n'est jamais mis à jour ni supprimé. Une erreur se corrige par une écriture
inverse, qui laisse la trace des deux.

## Les cinq comptes

| Compte | Nature | Ce qu'il veut dire |
|---|---|---|
| `wave`, `om` | actif | l'argent réellement sur les comptes de la plateforme |
| `sequestre` | **dette** | l'argent des acheteurs, retenu — il ne vous appartient pas |
| `vendeur:{id}` | **dette** | ce que vous devez à ce vendeur |
| `commission` | produit | votre revenu, le seul |
| `frais_paiement` | charge | ce que prend l'opérateur |

La distinction actif / dette est ce que le jury vérifiera. **Le séquestre n'est
pas un revenu.**

## Les écritures, cas par cas

Exemple : commande de 100 000 F, commission de 8 %, frais opérateur de 1 500 F.

### 1. L'acheteur paie

```
débit   wave          100 000
crédit  sequestre     100 000
```

L'argent est arrivé, mais il est dû à quelqu'un. Le bilan ne bouge pas.

### 2. Les frais de l'opérateur

```
débit   frais_paiement  1 500
crédit  wave            1 500
```

À la charge de la plateforme, pas du vendeur — c'est un choix commercial à
assumer et à écrire.

### 3. La réception est confirmée

```
débit   sequestre      100 000
crédit  vendeur:12      92 000
crédit  commission       8 000
```

C'est **ici seulement** que naît le revenu. Pas à la commande, pas au paiement :
à la livraison confirmée.

### 4. Le reversement au vendeur

```
débit   vendeur:12      92 000
crédit  wave            92 000
```

La dette est éteinte. L'argent sort.

### 5. Un litige tranché en faveur de l'acheteur

```
débit   sequestre      100 000
crédit  wave           100 000
```

Aucune commission. La plateforme perd les frais d'opérateur : c'est le coût du
service, et il faut le mesurer.

## Les invariants — ce qui se teste

**Invariant 1 — toute opération est équilibrée.**
Pour chaque `operation_id`, somme des débits = somme des crédits.

**Invariant 2 — le grand livre est équilibré.**
Somme de tous les débits = somme de tous les crédits, à tout instant.

**Invariant 3 — le séquestre est justifié.**
Le solde du compte `sequestre` doit égaler, au franc près, la somme des montants
des commandes payées et non encore réceptionnées ni remboursées.

**Invariant 4 — aucune dette négative.**
Le solde d'un compte `vendeur:{id}` n'est jamais négatif : on ne doit pas de
l'argent négatif à quelqu'un.

Ces quatre règles sont des tests automatisés. Les exécuter devant le jury vaut
mieux que n'importe quelle explication.

## L'idempotence des rappels

L'opérateur de paiement rappelle la plateforme pour confirmer un encaissement.
Ce rappel peut arriver **deux fois**, ou **jamais**.

Chaque rappel porte une référence externe. La plateforme la conserve dans une
colonne **unique** :

```php
// La contrainte d'unicité est la garde, pas le « if » qui la précède :
// entre la vérification et l'écriture, un second rappel peut passer.
try {
    Paiement::create(['cle_idempotence' => $ref, ...]);
} catch (UniqueConstraintViolationException) {
    return response()->noContent();   // déjà traité, on ne recrédite pas
}
```

Sans cela, un rappel dupliqué crédite deux fois le séquestre, et le grand livre
ment.

## La réconciliation

Chaque nuit, une tâche compare le relevé de l'opérateur aux écritures du compte
`wave`. Trois cas :

- **présent des deux côtés** : rien à faire ;
- **chez l'opérateur, absent du grand livre** : un rappel s'est perdu — la
  commande est débloquée automatiquement ;
- **dans le grand livre, absent chez l'opérateur** : anomalie grave, signalée à
  l'administration.

C'est ce travail, invisible de l'utilisateur, qui sépare un prototype d'un
système qui manipule de l'argent.

## Ce qu'il ne faut pas faire

- Stocker les montants en flottant. Des francs CFA en entier, toujours.
- Calculer la commission à la commande. Elle naît à la réception.
- Recalculer un solde en modifiant une ligne. On ajoute une écriture inverse.
- Reverser sans vérifier qu'aucun litige n'est ouvert.
