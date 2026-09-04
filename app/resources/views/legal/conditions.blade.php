@extends('layouts.app')
@section('titre', 'Conditions générales')
@section('contenu')

@include('partials.entete', [
  'titre' => 'Conditions générales',
  'sous' => "Comment FamFer fonctionne, et ce que chacun s'engage à faire. Dernière mise à jour le "
    . now()->translatedFormat('j F Y') . '.',
  'fil' => [
    ['libelle' => 'Accueil', 'url' => route('accueil')],
    ['libelle' => 'Conditions générales'],
  ],
])

<div class="bloc">
<div class="bloc-corps prose">

<h2>1. Ce qu'est FamFer</h2>
<p>
  FamFer met en relation des boutiques de fer et de quincaillerie avec des
  acheteurs. La plateforme n'est <strong>ni vendeur ni fabricant</strong> :
  elle ne détient aucune marchandise. Le contrat de vente lie l'acheteur et la
  boutique.
</p>

<h2>2. Le paiement à la livraison</h2>
<p style="margin-bottom:8px">
  Le mode par défaut est le règlement <strong>au livreur, en espèces</strong>,
  au moment de recevoir la commande. FamFer ne détient donc jamais votre argent
  avant la livraison, et ne vous demande aucun paiement à la commande.
</p>
<p>
  La contrepartie est un engagement : le colis part avant d'être payé, et une
  tournée coûte à la boutique. <strong>Refuser un colis sans motif</strong> à
  répétition peut entraîner la suspension du compte.
</p>

<h2>3. Les frais de livraison</h2>
<p>
  Un forfait par région, affiché avant la validation de la commande — de
  1 500 F sur Dakar à 5 000 F pour les régions les plus éloignées. La livraison
  est <strong>offerte à partir de 50 000 F</strong> d'achat.
</p>

<h2>4. Annuler, refuser, retourner</h2>
<ul style="margin:0 0 16px 20px">
  <li>Tant que la commande est <strong>en préparation</strong>, vous l'annulez
      vous-même depuis votre espace, sans frais ni justification.</li>
  <li>Une fois expédiée, elle ne s'annule plus en ligne : contactez la boutique.</li>
  <li>Un article non conforme se signale à la livraison ; le stock est rendu au
      vendeur et rien ne vous est facturé.</li>
</ul>

<h2>5. Les boutiques</h2>
<p>
  Aucune boutique n'apparaît au catalogue avant d'avoir été validée par
  l'administration. Une boutique reste responsable de la conformité, de la
  qualité et de la disponibilité de ce qu'elle affiche. Les prix barrés doivent
  correspondre à un prix réellement pratiqué : une remise annoncée sur un prix
  gonflé est un motif de suspension.
</p>

<h2>6. Les avis</h2>
<p>
  Un avis ne peut être laissé que par un client dont la commande a été
  <strong>livrée</strong>, et un produit ne se note qu'une fois par commande.
  Les notes affichées — celle du produit comme celle de la boutique — sont
  recalculées depuis les avis ; elles ne sont jamais saisies à la main, ni par
  le vendeur, ni par la plateforme.
</p>

<h2>7. Vos données</h2>
<p>
  Les adresses de votre carnet servent à livrer et à vous joindre. L'adresse
  d'une commande passée est recopiée au moment de la commande : corriger votre
  carnet ne modifie pas une livraison déjà en cours. Vos données ne sont ni
  vendues ni cédées.
</p>

<h2>8. Le service</h2>
<p>
  FamFer met tout en œuvre pour rester accessible, sans garantir une
  disponibilité ininterrompue. Une commande enregistrée le reste, quelle que
  soit la disponibilité du site.
</p>

<h2>9. Nous joindre</h2>
<p style="margin:0">
  Institut Supérieur d'Informatique, Dakar — projet de fin de cycle de master.
  Pour toute question sur une commande, écrivez depuis l'adresse du compte
  concerné.
</p>

</div>
</div>

@endsection
