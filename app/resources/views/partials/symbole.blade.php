{{--
  Le jeu de symboles de l'interface.

  Les mêmes tracés étaient recopiés à la main dans le gabarit et dans une
  douzaine de vues, chaque fois avec une épaisseur de trait et une taille
  légèrement différentes — le panier de l'en-tête et celui du récapitulatif
  n'étaient pas le même dessin. Ils sont désormais définis une fois.

  Tous partagent la même grille de vingt unités, la même épaisseur relative et
  la couleur héritée du texte : ils s'éclairent avec le libellé qu'ils
  accompagnent, au survol comme au changement de thème.

  Ils sont décoratifs : « aria-hidden » les retire de la lecture, puisque le mot
  qu'ils accompagnent dit déjà la chose. Un symbole seul — la bascule de thème —
  porte son sens dans le « aria-label » du bouton qui le contient.

  Variables : $nom (string), $taille (int, défaut 18)
--}}
@php $t = $taille ?? 18; @endphp

<svg viewBox="0 0 20 20" width="{{ $t }}" height="{{ $t }}" fill="none"
     stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
     stroke-linejoin="round" aria-hidden="true" focusable="false"
     style="flex:0 0 auto">
  @switch($nom)

    @case('loupe')
      <circle cx="8.8" cy="8.8" r="5.3"/><path d="M12.7 12.7L17 17"/>
      @break

    @case('personne')
      <circle cx="10" cy="6.6" r="3.2"/>
      <path d="M3.9 17c.6-3.4 3.2-5.3 6.1-5.3s5.5 1.9 6.1 5.3"/>
      @break

    @case('panier')
      <path d="M2 3h2.2l2.1 10.6h9.3"/><path d="M5.5 6h12l-1.4 5.6H6.5"/>
      <circle cx="7.6" cy="16.8" r="1.2"/><circle cx="15" cy="16.8" r="1.2"/>
      @break

    @case('chevron')
      <path d="M4 7.5l6 5.2 6-5.2" stroke-width="2.2"/>
      @break

    @case('fleche-droite')
      <path d="M3.5 10h13"/><path d="M11.5 5l5 5-5 5"/>
      @break

    @case('fleche-gauche')
      <path d="M16.5 10h-13"/><path d="M8.5 5l-5 5 5 5"/>
      @break

    @case('coche')
      <circle cx="10" cy="10" r="7.6"/><path d="M6.6 10.2l2.4 2.4 4.5-4.9"/>
      @break

    @case('alerte')
      <path d="M10 2.8L18 16.4H2z"/><path d="M10 8.1v3.6"/><path d="M10 14.1v.1"/>
      @break

    @case('info')
      <circle cx="10" cy="10" r="7.6"/><path d="M10 9.2v4.6"/><path d="M10 6.4v.1"/>
      @break

    @case('theme')
      {{-- Un disque à moitié plein : il dit « clair ou sombre » sans choisir. --}}
      <circle cx="10" cy="10" r="6.4"/>
      <path d="M10 3.6a6.4 6.4 0 000 12.8z" fill="currentColor" stroke="none"/>
      @break

    @case('camion')
      <path d="M2 5.4h9.4v8.2H2z"/><path d="M11.4 8.4h3.4L18 11v2.6h-6.6z"/>
      <circle cx="5.6" cy="15.4" r="1.5"/><circle cx="14.4" cy="15.4" r="1.5"/>
      @break

    @case('boite')
      <path d="M10 2.6l7 3.6v7.6l-7 3.6-7-3.6V6.2z"/>
      <path d="M3 6.2l7 3.6 7-3.6"/><path d="M10 9.8v7.6"/>
      @break

    @case('boutique')
      <path d="M3.2 7.6h13.6V17H3.2z"/><path d="M2.4 7.6L4 3.2h12l1.6 4.4"/>
      <path d="M8 17v-4.4h4V17"/>
      @break

    @case('etoile')
      <path d="M10 2.8l2.3 4.7 5.2.8-3.8 3.7.9 5.2-4.6-2.5-4.6 2.5.9-5.2L2.5 8.3l5.2-.8z"/>
      @break

    @case('graphique')
      <path d="M3 17V8"/><path d="M8 17V3.6"/><path d="M13 17v-6.4"/><path d="M17.4 17V6.2"/>
      @break

    @case('hausse')
      <path d="M3 14.2l4.8-5 3.2 3.2 5.6-6"/><path d="M12.4 6.4h4.2v4.2"/>
      @break

    @case('baisse')
      <path d="M3 6.2l4.8 5 3.2-3.2 5.6 6"/><path d="M12.4 14h4.2V9.8"/>
      @break

    @case('plat')
      <path d="M3.5 10h13"/>
      @break

    @case('argent')
      <circle cx="10" cy="10" r="7.4"/><path d="M10 5.6v8.8"/>
      <path d="M12.3 7.6c-.5-.7-1.4-1-2.3-1-1.3 0-2.3.7-2.3 1.7 0 2.4 4.6 1.3 4.6 3.7 0 1-1 1.7-2.3 1.7-.9 0-1.8-.3-2.3-1"/>
      @break

    @case('document')
      <path d="M5 2.6h6l4 4V17.4H5z"/><path d="M11 2.6v4h4"/>
      <path d="M7.6 11h4.8"/><path d="M7.6 13.8h4.8"/>
      @break

    @case('cadenas')
      <path d="M4.6 8.8h10.8v8.2H4.6z"/>
      <path d="M7.2 8.8V6.6a2.8 2.8 0 015.6 0v2.2"/>
      @break

    @case('calendrier')
      <path d="M3.4 5.2h13.2v12H3.4z"/><path d="M3.4 8.8h13.2"/>
      <path d="M7 2.8v3"/><path d="M13 2.8v3"/>
      @break

    @case('horloge')
      <circle cx="10" cy="10" r="7.4"/><path d="M10 5.8V10l2.8 1.8"/>
      @break

    @case('plus')
      <path d="M10 4.2v11.6"/><path d="M4.2 10h11.6"/>
      @break

    @case('croix')
      <path d="M5.2 5.2l9.6 9.6"/><path d="M14.8 5.2l-9.6 9.6"/>
      @break

    @case('filtre')
      <path d="M3 5.4h14"/><path d="M5.6 10h8.8"/><path d="M8.2 14.6h3.6"/>
      @break

    @case('balance')
      {{-- L'arbitrage : deux plateaux, un fléau. --}}
      <path d="M10 3.4v13.2"/><path d="M5.6 16.6h8.8"/><path d="M3.6 6.6h12.8"/>
      <path d="M3.6 6.6L1.6 11.4h4z"/><path d="M16.4 6.6l-2 4.8h4z"/>
      @break

    @default
      <circle cx="10" cy="10" r="7.4"/>
  @endswitch
</svg>
