@extends('layouts.app')
@section('titre', 'Conditions générales')
@section('contenu')
<div style="max-width:74ch">

<h1>Conditions générales</h1>
<p class="sous">
  Ce que FamFer fait de votre argent, et ce qu'elle n'en fait pas.
  Dernière mise à jour : {{ now()->translatedFormat('j F Y') }}.
</p>

<h2>1. Ce qu'est FamFer</h2>
<p>
  FamFer met en relation des quincailleries et des acheteurs de fer et de pièces
  détachées. La plateforme n'est <strong>ni vendeur ni fabricant</strong> : elle
  ne détient aucune marchandise. Le contrat de vente lie l'acheteur et la
  quincaillerie ; FamFer en est le tiers de confiance.
</p>

<h2>2. L'argent est retenu, pas encaissé</h2>
<p>
  Quand un acheteur règle une commande, la somme n'est pas versée au vendeur :
  elle est <strong>retenue par FamFer</strong> jusqu'à ce que l'acheteur
  confirme avoir reçu sa marchandise. C'est ce qu'on appelle un séquestre.
</p>
<p>
  Cette somme n'est pas un revenu de la plateforme. Elle figure dans ses comptes
  comme une <strong>dette</strong> envers l'acheteur puis envers le vendeur, et
  la comptabilité de FamFer doit à tout instant pouvoir le démontrer.
</p>
<p>
  Sans confirmation de l'acheteur, la réception est réputée acquise
  <strong>soixante-douze heures</strong> après la remise : sans cette règle, un
  acheteur distrait retiendrait indéfiniment l'argent d'un vendeur.
</p>

<h2>3. Ce que FamFer prélève</h2>
<p>
  Une commission, dont le taux figure sur l'espace de chaque vendeur — 8 % par
  défaut, négociable. Elle porte sur <strong>la marchandise seule</strong> :
  jamais sur les frais de livraison, qui reviennent entièrement au vendeur.
</p>
<p>
  Rien n'est prélevé à l'inscription, ni à la publication d'une offre. La
  commission n'est due qu'une fois la commande reçue : une vente annulée,
  expirée ou remboursée <strong>ne coûte rien</strong> au vendeur.
</p>

<h2>4. Les délais</h2>
<ul>
  <li><strong>Quinze minutes</strong> pour régler une commande, faute de quoi
      la marchandise réservée est rendue au stock du vendeur.</li>
  <li><strong>Deux heures</strong> pour que le vendeur accepte une commande
      payée ; passé ce délai, l'acheteur est remboursé.</li>
  <li><strong>Soixante-douze heures</strong> après la remise pour signaler un
      problème.</li>
</ul>

<h2>5. Les litiges</h2>
<p>
  Un litige ouvert <strong>gèle la totalité</strong> des virements dus au
  vendeur concerné, et non la seule commande contestée. L'administration
  tranche par une décision motivée. Un remboursement décidé en faveur de
  l'acheteur n'engendre aucune commission.
</p>

<h2>6. Les vendeurs</h2>
<p>
  Aucune quincaillerie n'apparaît sur la place de marché avant vérification de
  son établissement : c'est la contrepartie du séquestre, puisque la plateforme
  encaisse pour son compte. Un vendeur reste responsable de la conformité, de la
  qualité et de la quantité de ce qu'il livre.
</p>

<h2>7. Les avis</h2>
<p>
  Une note ne peut être laissée que par un acheteur ayant <strong>réellement
  reçu</strong> la commande qu'il note, et une commande ne se note qu'une fois.
  La moyenne affichée est recalculée depuis les avis ; elle n'est jamais saisie
  à la main, ni par le vendeur, ni par la plateforme.
</p>

<h2>8. Les données</h2>
<p>
  La position transmise pendant une recherche sert à trouver les structures les
  plus proches et à chiffrer une livraison. Les coordonnées enregistrées sur un
  compte servent à livrer et à joindre son titulaire. Elles ne sont ni vendues
  ni cédées.
</p>

<h2>9. Le service</h2>
<p>
  FamFer met tout en œuvre pour rester accessible, sans garantir une
  disponibilité ininterrompue. En cas d'interruption, les sommes séquestrées
  restent dues à qui de droit : elles sont inscrites en comptabilité et ne
  dépendent pas de l'état du service.
</p>

<h2>10. Nous joindre</h2>
<p>
  Institut Supérieur d'Informatique, Dakar — projet de fin de cycle de master.
  Pour toute question sur une commande ou un virement, écrivez depuis l'adresse
  du compte concerné.
</p>

</div>
@endsection
