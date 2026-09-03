{{--
  L'image d'un produit, en trois échelons du plus précis au plus général.

  1. La photo téléversée par le vendeur.
  2. À défaut, l'illustration de sa famille — des barres d'acier pour un fer à
     béton, une clé plate pour un jeu de clés.
  3. À défaut encore, le dessin au trait.

  Le dernier échelon ne manque jamais, donc aucun produit n'apparaît comme un
  cadre vide. Un catalogue se remplit peu à peu ; sans ce repli, la moitié des
  fiches seraient blanches pendant des mois.

  Variables : $p (Produit), $taille (int)
--}}
@php
  $photo = $p->vignette();
  $illustration = $photo ? null : $p->imageDeCategorie();
@endphp

@if($photo)
  <img src="{{ $photo->url() }}" alt="{{ $photo->description ?: $p->nom }}"
       loading="lazy" style="width:100%;height:100%;object-fit:contain">

@elseif($illustration)
  {{-- L'illustration de famille est recadrée et légèrement estompée : elle
       situe le rayon sans se faire passer pour une photo du produit. --}}
  <img src="{{ $illustration }}" alt="{{ $p->categorie->nom }}"
       loading="lazy"
       style="width:100%;height:100%;object-fit:cover;opacity:.88">

@else
  @include('partials.dessin', ['dessin' => $p->dessin, 'taille' => $taille ?? 96])
@endif
