{{--
  L'image d'un produit : sa photo si elle en a une, son dessin sinon.

  Le repli n'est pas une facilité : un catalogue se remplit peu à peu, et des
  produits sans photo apparaîtraient comme des cadres vides pendant des mois.
  Le dessin tient la place jusqu'à ce que le vendeur photographie sa
  marchandise.

  Variables : $p (Produit), $taille (int), $classe (?string)
--}}
@php
  $photo = $p->vignette();
@endphp

@if($photo)
  <img src="{{ $photo->url() }}"
       alt="{{ $photo->description ?: $p->nom }}"
       loading="lazy"
       style="width:100%;height:100%;object-fit:contain;{{ $classe ?? '' }}">
@else
  @include('partials.dessin', ['dessin' => $p->dessin, 'taille' => $taille ?? 96])
@endif
