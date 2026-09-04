{{--
  Une courbe avec son aire, dessinée en SVG.

  Même parti que l'histogramme : aucune bibliothèque, une échelle qui part de
  zéro, des étiquettes qui tiennent dans le cadre, et des couleurs prises aux
  jetons du thème.

  Un point est marqué sur chaque relevé plutôt qu'une ligne lisse seule : sur
  une série mensuelle, l'œil doit pouvoir compter les mois. Le dernier point est
  agrandi — c'est celui qu'on vient regarder.

  Variables :
    $points  (array) — [['libelle' => …, 'valeur' => …], …], dans l'ordre du temps
    $hauteur (?int)  — défaut 200
    $suffixe (?string)
    $titre   (?string)
--}}
@php
  $points = collect($points ?? [])->values();
  $h      = $hauteur ?? 200;
  $suf    = $suffixe ?? '';

  $mg = ['g' => 54, 'd' => 12, 'ht' => 14, 'b' => 32];
  $largeur = 640;
  $aireL = $largeur - $mg['g'] - $mg['d'];
  $aireH = $h - $mg['ht'] - $mg['b'];

  $max = (float) ($points->max('valeur') ?: 0);
  if ($max > 0) {
      $ordre = 10 ** max(0, strlen((string) (int) $max) - 2);
      $max = ceil($max / $ordre) * $ordre;
  } else {
      $max = 1;
  }

  $n = max(1, $points->count());
  // Un seul relevé : on le pose au milieu plutôt que de diviser par zéro.
  $pas = $n > 1 ? $aireL / ($n - 1) : 0;

  $coords = $points->map(function ($p, $i) use ($mg, $pas, $aireH, $aireL, $max, $n) {
      $x = $n > 1 ? $mg['g'] + $i * $pas : $mg['g'] + $aireL / 2;
      $y = $mg['ht'] + $aireH - (((float) ($p['valeur'] ?? 0)) / $max) * $aireH;

      return ['x' => round($x, 1), 'y' => round($y, 1)] + $p;
  });

  $ligne = $coords->map(fn ($c) => $c['x'] . ',' . $c['y'])->implode(' ');
  $bas   = $mg['ht'] + $aireH;
  $aire  = $coords->isEmpty() ? '' :
      $coords->first()['x'] . ',' . $bas . ' ' . $ligne . ' ' .
      $coords->last()['x'] . ',' . $bas;

  $graduations = 4;
  $court = fn ($v) => $v >= 1000000 ? round($v / 1000000, 1) . ' M'
         : ($v >= 1000 ? round($v / 1000) . ' k' : (string) round($v));
@endphp

<figure style="margin:0">
  @if(!empty($titre))
    <figcaption class="visuellement-cache">{{ $titre }}</figcaption>
  @endif

  <svg class="graphe" viewBox="0 0 {{ $largeur }} {{ $h }}"
       role="img" aria-label="{{ $titre ?? 'Courbe' }}"
       preserveAspectRatio="none" style="height:{{ $h }}px">

    @for($i = 0; $i <= $graduations; $i++)
      @php
        $y = $mg['ht'] + $aireH - ($i / $graduations) * $aireH;
        $v = ($i / $graduations) * $max;
      @endphp
      <line class="grille-h" x1="{{ $mg['g'] }}" y1="{{ round($y, 1) }}"
            x2="{{ $largeur - $mg['d'] }}" y2="{{ round($y, 1) }}"/>
      <text class="axe" x="{{ $mg['g'] - 8 }}" y="{{ round($y + 3.5, 1) }}"
            text-anchor="end">{{ $court($v) }}</text>
    @endfor

    @if($coords->count() > 1)
      <polygon class="aire" points="{{ $aire }}"/>
    @endif
    <polyline class="serie" points="{{ $ligne }}"/>

    @foreach($coords as $i => $c)
      <circle class="point" cx="{{ $c['x'] }}" cy="{{ $c['y'] }}"
              r="{{ $i === $coords->count() - 1 ? 4.5 : 2.8 }}">
        <title>{{ $c['libelle'] ?? '' }} : {{ number_format((float) ($c['valeur'] ?? 0), 0, ',', ' ') }}{{ $suf }}</title>
      </circle>

      @if($n <= 8 || $i % 2 === 0 || $i === $n - 1)
        <text class="axe" x="{{ $c['x'] }}" y="{{ $h - 11 }}"
              text-anchor="{{ $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle') }}">
          {{ $c['libelle'] ?? '' }}
        </text>
      @endif
    @endforeach
  </svg>
</figure>
