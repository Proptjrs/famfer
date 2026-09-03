{{--
  La petite icône d'une famille, dans la barre des rayons.

  Les dessins d'articles existants font 96 pixels et portent des dégradés : à
  seize pixels sur fond sombre, ils deviennent une tache grise. Ces tracés-ci
  sont donc dessinés pour cette taille — un seul trait, pas de remplissage, et
  la couleur héritée du lien, de sorte qu'ils s'éclairent avec lui au survol.

  Variable : $famille (string) — le nom de la famille.
--}}
@php
  $d = match (true) {
      str_contains($famille, 'béton')      => 'beton',
      str_contains($famille, 'Tôle')       => 'tole',
      str_contains($famille, 'Tubes')      => 'tube',
      str_contains($famille, 'Treillis')   => 'treillis',
      str_contains($famille, 'Quincaille') => 'quincaillerie',
      str_contains($famille, 'Outillage')  => 'outillage',
      str_contains($famille, 'Pièces')     => 'piece',
      default                              => 'defaut',
  };
@endphp

<svg viewBox="0 0 20 20" width="15" height="15" fill="none" stroke="currentColor"
     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" style="flex:0 0 auto;opacity:.85">
  @switch($d)
    @case('beton')
      {{-- Une barre striée, vue de côté. --}}
      <path d="M2 10h16"/>
      <path d="M5 7.5v5M8 7.5v5M11 7.5v5M14 7.5v5"/>
      @break

    @case('tole')
      {{-- Le profil ondulé d'une tôle bac. --}}
      <path d="M2 13c1.5 0 1.5-6 3-6s1.5 6 3 6 1.5-6 3-6 1.5 6 3 6 1.5-6 3-6"/>
      @break

    @case('tube')
      {{-- Une section carrée en perspective. --}}
      <path d="M3 7h9v9H3z"/>
      <path d="M3 7l4-3h9v9l-4 3"/>
      <path d="M12 7l4-3"/>
      @break

    @case('treillis')
      {{-- Un panneau de treillis soudé. --}}
      <path d="M3 3h14v14H3z"/>
      <path d="M7.7 3v14M12.3 3v14M3 7.7h14M3 12.3h14"/>
      @break

    @case('quincaillerie')
      {{-- Un boulon : tête hexagonale et tige filetée. --}}
      <path d="M7 4.5l3-1.7 3 1.7v3.5l-3 1.7-3-1.7z"/>
      <path d="M10 9.5V17"/>
      <path d="M8.4 12h3.2M8.4 14.2h3.2"/>
      @break

    @case('outillage')
      {{-- Un marteau. --}}
      <path d="M3 16.5L11 8.5"/>
      <path d="M10 5.5l4.5-2.5 3 3-2.5 4.5-2.5-2.5z"/>
      @break

    @case('piece')
      {{-- Un roulement : bague et billes. --}}
      <circle cx="10" cy="10" r="7"/>
      <circle cx="10" cy="10" r="3"/>
      <circle cx="10" cy="4.6" r=".9" fill="currentColor" stroke="none"/>
      <circle cx="10" cy="15.4" r=".9" fill="currentColor" stroke="none"/>
      <circle cx="4.6" cy="10" r=".9" fill="currentColor" stroke="none"/>
      <circle cx="15.4" cy="10" r=".9" fill="currentColor" stroke="none"/>
      @break

    @default
      <path d="M3 3h14v14H3z"/>
  @endswitch
</svg>
