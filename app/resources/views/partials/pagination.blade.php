{{--
  La pagination, écrite à la main.

  Laravel livre des gabarits Tailwind ; ce projet n'a pas Tailwind, et les
  importer pour trois liens aurait ajouté une dépendance entière à la feuille
  de style. Vingt lignes suffisent.
--}}
@if($paginator->hasPages())
  <nav style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;justify-content:center">
    @if($paginator->onFirstPage())
      <span class="btn btn-sm btn-clair" style="opacity:.45">← Précédent</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-sm btn-clair" rel="prev">← Précédent</a>
    @endif

    @foreach($elements as $element)
      @if(is_string($element))
        <span style="padding:0 6px;color:var(--gris)">{{ $element }}</span>
      @endif

      @if(is_array($element))
        @foreach($element as $page => $url)
          @if($page == $paginator->currentPage())
            <span class="btn btn-sm" aria-current="page">{{ $page }}</span>
          @else
            <a href="{{ $url }}" class="btn btn-sm btn-clair">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-sm btn-clair" rel="next">Suivant →</a>
    @else
      <span class="btn btn-sm btn-clair" style="opacity:.45">Suivant →</span>
    @endif
  </nav>
@endif
