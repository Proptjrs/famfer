{{--
  La petite icône d'un rayon, dans la barre des catégories.

  Les dessins de produits font 96 pixels et portent des dégradés : à quinze
  pixels ils deviennent une tache grise. Ces tracés-ci sont dessinés pour cette
  taille — un seul trait, pas de remplissage, et la couleur héritée du lien, de
  sorte qu'ils s'éclairent avec lui au survol.

  Variable : $icone (?string) — la clé portée par la catégorie en base.
--}}
@php $d = $icone ?: 'defaut'; @endphp

<svg viewBox="0 0 20 20" width="15" height="15" fill="none" stroke="currentColor"
     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" style="flex:0 0 auto">
  @switch($d)
    @case('beton')
      {{-- Une barre striée, vue de côté. --}}
      <path d="M2 10h16"/><path d="M5 7.5v5M8 7.5v5M11 7.5v5M14 7.5v5"/>
      @break

    @case('tole')
      <path d="M2 13c1.5 0 1.5-6 3-6s1.5 6 3 6 1.5-6 3-6 1.5 6 3 6 1.5-6 3-6"/>
      @break

    @case('tube')
      <path d="M3 7h9v9H3z"/><path d="M3 7l4-3h9v9l-4 3"/><path d="M12 7l4-3"/>
      @break

    @case('treillis')
      <path d="M3 3h14v14H3z"/><path d="M7.7 3v14M12.3 3v14M3 7.7h14M3 12.3h14"/>
      @break

    @case('quincaillerie')
      {{-- Un boulon : tête hexagonale et tige filetée. --}}
      <path d="M7 4.5l3-1.7 3 1.7v3.5l-3 1.7-3-1.7z"/><path d="M10 9.5V17"/>
      <path d="M8.4 12h3.2M8.4 14.2h3.2"/>
      @break

    @case('outillage')
      {{-- Un marteau. --}}
      <path d="M3 16.5L11 8.5"/><path d="M10 5.5l4.5-2.5 3 3-2.5 4.5-2.5-2.5z"/>
      @break

    @case('electrique')
      {{-- Une perceuse : corps, mandrin, poignée. --}}
      <path d="M3 6h8v5H3z"/><path d="M11 8h4l2 .5-2 .5h-4"/>
      <path d="M5.5 11v3.5a1.5 1.5 0 003 0V11"/>
      @break

    @case('soudure')
      {{-- Un arc et ses étincelles. --}}
      <path d="M4 16l7-7"/><path d="M10.5 6.5l3-3"/>
      <path d="M13.5 9.5l3-3"/><path d="M9 4l1 2M16 11l-2 1M15.5 15.5l-1.5-2"/>
      @break

    @case('peinture')
      {{-- Un pinceau et sa trace. --}}
      <path d="M13 3l4 4-6 6-4-4z"/><path d="M7 9l-3 5 5-3"/>
      <path d="M4 17h3"/>
      @break

    @case('plomberie')
      {{-- Un coude de tuyauterie avec ses brides. --}}
      <path d="M4 16V9a5 5 0 015-5h7"/>
      <path d="M2 16h4M14 2v4"/>
      <path d="M2.5 13.5h3M12.5 2.5v3"/>
      @break

    @case('electricite')
      {{-- L'éclair. --}}
      <path d="M11.5 2L5 11h4l-.5 7L15 9h-4z"/>
      @break

    @case('piece')
      {{-- Un roulement : bague et billes. --}}
      <circle cx="10" cy="10" r="7"/><circle cx="10" cy="10" r="3"/>
      <circle cx="10" cy="4.6" r=".9" fill="currentColor" stroke="none"/>
      <circle cx="10" cy="15.4" r=".9" fill="currentColor" stroke="none"/>
      <circle cx="4.6" cy="10" r=".9" fill="currentColor" stroke="none"/>
      <circle cx="15.4" cy="10" r=".9" fill="currentColor" stroke="none"/>
      @break

    @case('agriculture')
      {{-- Une pelle. --}}
      <path d="M10 2v9"/><path d="M8 2h4"/>
      <path d="M7 11h6l-1 5a2 2 0 01-4 0z"/>
      @break

    @case('portail')
      {{-- Deux battants et leurs traverses. --}}
      <path d="M2 16V7l3-3h10l3 3v9"/><path d="M10 4v12"/>
      <path d="M2 10h16M2 13h16"/>
      @break

    @case('epi')
      {{-- Un casque de chantier. --}}
      <path d="M3 14a7 7 0 0114 0"/><path d="M2 14h16"/>
      <path d="M7.5 7.6V4.5h5v3.1"/>
      @break

    @default
      {{-- Un carton, pour tout le reste. --}}
      <path d="M3 6.5L10 3l7 3.5v7L10 17l-7-3.5z"/>
      <path d="M3 6.5L10 10l7-3.5M10 10v7"/>
  @endswitch
</svg>
