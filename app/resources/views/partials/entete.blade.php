{{--
  L'en-tête d'une page : fil d'Ariane, titre, sous-titre, actions.

  Chaque écran bricolait le sien, avec des tailles de titre différentes, des
  actions tantôt à gauche tantôt à droite, et pas de fil d'Ariane du tout — dans
  une arborescence à trois niveaux (rayon, sous-rayon, produit), l'acheteur
  n'avait aucun moyen de remonter d'un cran.

  Le sous-titre n'est pas un ornement : il dit à quoi sert l'écran quand le titre
  seul ne suffit pas. Un écran qu'il faut deviner est un écran raté.

  Variables :
    $titre  (string)
    $sous   (?string)
    $fil    (?array)  — [['libelle' => …, 'url' => …], …], le dernier sans url
    $actions(?string) — du HTML déjà échappé par l'appelant
--}}
<div class="entete">
  <div>
    @if(!empty($fil))
      <nav class="fil" aria-label="Fil d'Ariane">
        @foreach($fil as $i => $etape)
          @if($i > 0)<span class="sep" aria-hidden="true">/</span>@endif
          @if(!empty($etape['url']))
            <a href="{{ $etape['url'] }}">{{ $etape['libelle'] }}</a>
          @else
            <span aria-current="page">{{ $etape['libelle'] }}</span>
          @endif
        @endforeach
      </nav>
    @endif

    <h1>{{ $titre }}</h1>

    @if(!empty($sous))
      <p class="sous-titre">{{ $sous }}</p>
    @endif
  </div>

  @if(!empty($actions))
    <div class="entete-actions">{!! $actions !!}</div>
  @endif
</div>
