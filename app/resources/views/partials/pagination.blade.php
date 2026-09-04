{{--
  La pagination, écrite à la main.

  Laravel livre des gabarits Tailwind ; ce projet n'a pas Tailwind, et les
  importer pour trois liens aurait ajouté une dépendance entière à la feuille de
  style. Vingt lignes suffisent.

  Elle porte désormais son propre composant plutôt que des boutons détournés :
  un numéro de page n'est pas une action, et un bouton plein pour chaque chiffre
  faisait une rangée de blocs orange qui écrasait la liste au-dessus.
--}}
@if($paginator->hasPages())
  <nav class="pagination" aria-label="Pagination">
    @if($paginator->onFirstPage())
      <span class="inactif" aria-hidden="true">Précédent</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" rel="prev">
        @include('partials.symbole', ['nom' => 'fleche-gauche', 'taille' => 14])
        <span class="visuellement-cache">Page précédente</span>
      </a>
    @endif

    @foreach($elements as $element)
      @if(is_string($element))
        <span class="inactif" aria-hidden="true">{{ $element }}</span>
      @endif

      @if(is_array($element))
        @foreach($element as $page => $url)
          @if($page == $paginator->currentPage())
            <span aria-current="page">{{ $page }}</span>
          @else
            <a href="{{ $url }}">
              {{ $page }}<span class="visuellement-cache"> — page {{ $page }}</span>
            </a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" rel="next">
        @include('partials.symbole', ['nom' => 'fleche-droite', 'taille' => 14])
        <span class="visuellement-cache">Page suivante</span>
      </a>
    @else
      <span class="inactif" aria-hidden="true">Suivant</span>
    @endif
  </nav>
@endif
