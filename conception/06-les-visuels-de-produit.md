# Les visuels de produit

## Le point de départ

Le catalogue comptait 1 854 produits et **aucune photo**. Les fiches s'appuyaient
sur un repli à trois échelons : la photo du vendeur, à défaut l'illustration de
la famille (57 images Wikimedia sous licence libre), à défaut le dessin au trait.

Cinq archives de visuels ont été fournies pour combler l'échelon manquant.

## Ce que contenaient les archives

Les quatre `catalogue_produits_individuels*.zip` sont cumulatives — 425, 527,
665 puis 767 entrées. Après fusion, en conservant à nom égal le fichier le plus
lourd : **767 visuels uniques, 22,2 Mo**.

## Le tri, mesuré

| | |
|---|---|
| Sous 200 px | **665** |
| Rognage vide | 1 |
| Exploitables en taille | **101** |

Un fil de fer fait 117 × 68 pixels. Affiché sur une carte de 196 px de large,
c'est illisible. Ce n'est pas un jugement esthétique : le service `Photos`
impose 200 px depuis le premier jour, et il refuserait ces fichiers s'ils
venaient d'un vendeur.

**Le recadrage ne récupère rien.** Vérifié : après retrait des marges uniformes,
102 dépassent 200 px et 653 restent sous 120. Les petites sont déjà des
rognages serrés, simplement en basse définition.

## Le retrait de la chrome du catalogue

Les visuels exploitables sont des **captures de pages de catalogue** : chacune
porte une barre bleue de titre, un numéro de rayon et une légende, incrustés
dans les pixels. Les afficher tels quels mettrait le mobilier d'un autre
document sur chaque carte produit.

Le traitement se fait en trois passes, répétées jusqu'à stabilité :

1. **la barre bleue** se reconnaît à sa dominante — composante bleue nettement
   au-dessus des deux autres — et se retire ;
2. **la légende** est une bande d'encre entourée de blanc ; on coupe dessous ;
3. les **marges uniformes** restantes sont rognées, puis l'image est posée au
   centre d'un carré de 800 px, **jamais agrandie** — un agrandissement ne crée
   pas de détail, il étale le flou.

### Quatre pièges, et ce qu'ils ont appris

**La barre n'est pas toujours en haut.** Selon le rognage, du blanc la précède.
Supposer qu'elle commence à zéro laissait la barre entière en place sur une
image sur deux. On la cherche désormais dans toute la moitié supérieure.

**Le liseré anticrénelé se faisait passer pour la légende.** Sous la barre
bleue subsistent cinq ou six lignes sombres. Prises pour le texte, elles
faisaient couper trop haut, et la vraie légende survivait en fantôme au-dessus
de l'article. Une bande de moins de dix lignes n'est pas du texte : celui de ces
images en fait une trentaine.

**Une seule passe ne suffit pas.** Vingt-quatre images sur cent deux gardaient
leur légende parce que leur rognage commençait déjà sous la barre. Chaque passe
retire un élément ; on s'arrête quand plus rien ne bouge.

**Six rognages sont irrécupérables.** Ils contiennent le bas de l'article
précédent au-dessus de la légende : la règle ne peut pas les distinguer d'un
produit sans risquer de couper de vrais articles. Plutôt que de sur-ajuster pour
six cas, **le script les rejette lui-même** — il repasse son propre détecteur sur
sa sortie. Une légende sur une carte produit se voit ; une image manquante
retombe proprement sur le dessin.

Résultat : **96 visuels propres**, vérifiés mécaniquement — zéro barre bleue,
zéro bande de texte isolée.

## Le rapprochement avec le catalogue

Les fichiers sont renommés d'après le **nom normalisé du produit** :
`54_Profils_metalliques_08_IPN_(I).png` devient `ipn.webp`. Aucune table de
correspondance à maintenir — le nom du fichier *est* la correspondance.

Sur 96 visuels, **54 trouvent un produit**, et 37 fichiers distincts subsistent
après regroupement (plusieurs visuels visent le même article). Ils illustrent
**122 fiches**, le même article étant vendu par plusieurs boutiques.

Chaque fiche reçoit sa **propre copie** du fichier. C'est un peu de disque contre
beaucoup de simplicité : une photo partagée entre deux fiches se supprimerait
avec la première, `PhotoProduit` effaçant le fichier avec l'enregistrement.

Le PNG passe en **WebP** : 13 Mo deviennent **0,4 Mo**, sans différence visible.

## La couverture reste partielle, et le restera

**37 articles sur 1 854.** Les autres retombent sur l'illustration de leur
sous-rayon puis sur le dessin au trait — c'est précisément à cela que sert le
repli à trois échelons.

La voie durable reste le **téléversement par les vendeurs** : une boutique
photographie son propre stock, et l'image est alors exacte, à jour, et
incontestablement la sienne. Ce chemin est construit et éprouvé.

## Une réserve à connaître avant la mise en ligne

Ces visuels sont des rognages d'un catalogue commercial : la perceuse de
`14_Outillage_electrique_01` est une photographie de marque. Les illustrations
de famille, elles, viennent de Wikimedia Commons sous CC0, CC BY ou CC BY-SA,
avec auteur et licence enregistrés sur `/credits`.

Publier des photographies de fournisseur sans autorisation écrite est un risque
qui appartient à l'exploitant du site, pas au logiciel. Il vaut d'être pesé avant
un déploiement public — et il disparaît dès que les vendeurs téléversent leurs
propres photos.
