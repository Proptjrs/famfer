{{--
  Un histogramme, dessiné en SVG.

  Sans bibliothèque : une série de douze valeurs ne justifie pas trois cents
  kilo-octets de dépendance, et une dépendance servie par un réseau tiers ne se
  charge pas sur une connexion de brousse — c'est-à-dire précisément là où se
  trouvent une partie des acheteurs.

  Trois règles gouvernent le tracé.

  L'échelle part de zéro. Une échelle tronquée fait paraître énorme un écart de
  trois pour cent ; sur un tableau de bord qui sert à décider, c'est un mensonge.

  Chaque graduation porte une valeur que le graphique atteint réellement, et la
  boîte de vue réserve la place des étiquettes extérieures — sinon la première et
  la dernière sortent du cadre et disparaissent.

  Les couleurs viennent des jetons du thème, jamais d'une valeur écrite en dur :
  un graphique illisible en thème sombre est un graphique inutile la moitié du
  temps.

  Variables :
    $series  (array) — [['libelle' => …, 'valeur' => …, 'ton' => …], …]
    $hauteur (?int)  — défaut 180
    $suffixe (?string)
    $titre   (?string) — description accessible du graphique
--}}
@php
  $series = collect($series ?? [])->values();
  $h      = $hauteur ?? 180;
  $suf    = $suffixe ?? '';

  // Marges : la place des étiquettes, pas un ornement. Sans elles, l'axe des
  // ordonnées se superpose à la première barre.
  $mg = ['g' => 54, 'd' => 8, 'ht' => 12, 'b' => 34];
  $largeur = 640;
  $aireL = $largeur - $mg['g'] - $mg['d'];
  $aireH = $h - $mg['ht'] - $mg['b'];

  $max = (float) ($series->max('valeur') ?: 0);

  // Un maximum arrondi au rang supérieur : des graduations à « 4 173 » ne se
  // lisent pas, à « 5 000 » si.
  if ($max > 0) {
      $ordre = 10 ** max(0, strlen((string) (int) $max) - 2);
      $max = ceil($max / $ordre) * $ordre;
  } else {
      $max = 1;
  }

  $n = max(1, $series->count());
  $pas = $aireL / $n;
  $larg = min(46, $pas * 0.62);

  $graduations = 4;

  $court = fn ($v) => $v >= 1000000 ? round($v / 1000000, 1) . ' M'
         : ($v >= 1000 ? round($v / 1000) . ' k' : (string) round($v));
@endphp

<figure style="margin:0">
  @if(!empty($titre))
    <figcaption class="visuellement-cache">{{ $titre }}</figcaption>
  @endif

  <svg class="graphe" viewBox="0 0 {{ $largeur }} {{ $h }}"
       role="img" aria-label="{{ $titre ?? 'Histogramme' }}"
       preserveAspectRatio="none" style="height:{{ $h }}px">

    {{-- Les lignes de niveau, sous les barres. --}}
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

    @foreach($series as $i => $item)
      @php
        $val = (float) ($item['valeur'] ?? 0);
        $hb  = $max > 0 ? ($val / $max) * $aireH : 0;
        $x   = $mg['g'] + $i * $pas + ($pas - $larg) / 2;
        $y   = $mg['ht'] + $aireH - $hb;
        $ton = $item['ton'] ?? '';
      @endphp

      <rect class="barre {{ $ton }}" x="{{ round($x, 1) }}" y="{{ round($y, 1) }}"
            width="{{ round($larg, 1) }}" height="{{ round(max($hb, $val > 0 ? 2 : 0), 1) }}"
            rx="3">
        <title>{{ $item['libelle'] ?? '' }} : {{ number_format($val, 0, ',', ' ') }}{{ $suf }}</title>
      </rect>

      {{-- Une étiquette sur deux au-delà de huit colonnes : elles se
           chevaucheraient, et deux libellés superposés valent moins qu'un. --}}
      @if($n <= 8 || $i % 2 === 0)
        <text class="axe" x="{{ round($mg['g'] + $i * $pas + $pas / 2, 1) }}"
              y="{{ $h - 12 }}" text-anchor="middle">{{ $item['libelle'] ?? '' }}</text>
      @endif
    @endforeach
  </svg>
</figure>
