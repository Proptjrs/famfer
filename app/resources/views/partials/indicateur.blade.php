{{--
  Un indicateur de tableau de bord.

  L'ancienne version posait un chiffre sur une carte, et rien d'autre. Un chiffre
  sans point de comparaison n'aide à décider de rien : « 412 000 F » ne dit pas
  si le mois est bon. Celui-ci porte donc, quand l'appelant peut les fournir,
  une variation par rapport à la période précédente et une note qui explique ce
  que le chiffre recouvre.

  La bande de gravité sur le bord gauche dit d'un coup d'œil ce qui demande une
  action. Elle est préférée à un fond coloré, qui écraserait le chiffre — or
  c'est le chiffre qu'on vient lire.

  Variables :
    $libelle (string)
    $valeur  (string|int)
    $unite   (?string)  — « F », « % », rendu plus petit
    $note    (?string)
    $ecart   (?float)   — variation en points de pourcentage, signe compris
    $sens    (?string)  — « hausse-bonne » (défaut) ou « hausse-mauvaise »
    $ton     (?string)  — « bon », « attention », « tension »
    $lien    (?string)
--}}
@php
  $e = $ecart ?? null;
  $sensBon = ($sens ?? 'hausse-bonne') === 'hausse-bonne';

  // Sous un demi-point, la variation relève du bruit : l'annoncer comme une
  // tendance ferait prendre une décision sur du hasard.
  $classeEcart = $e === null ? null
    : (abs($e) < 0.5 ? 'plat' : (($e > 0) === $sensBon ? 'hausse' : 'baisse'));

  $symboleEcart = $e === null ? null
    : (abs($e) < 0.5 ? 'plat' : ($e > 0 ? 'hausse' : 'baisse'));
@endphp

<{{ !empty($lien) ? 'a' : 'div' }}
   @if(!empty($lien)) href="{{ $lien }}" @endif
   class="indicateur {{ $ton ?? '' }}">

  <span class="libelle">{{ $libelle }}</span>

  <span class="valeur">
    {{ $valeur }}@if(!empty($unite))<span class="unite"> {{ $unite }}</span>@endif
  </span>

  @if($e !== null)
    <span class="ecart {{ $classeEcart }}">
      @include('partials.symbole', ['nom' => $symboleEcart, 'taille' => 13])
      @if(abs($e) < 0.5)
        stable
      @else
        {{ ($e > 0 ? '+' : '−') . number_format(abs($e), 1, ',', ' ') }} %
      @endif
      <span class="secondaire" style="font-weight:400">sur 30 jours</span>
    </span>
  @endif

  @if(!empty($note))
    <span class="note">{{ $note }}</span>
  @endif
</{{ !empty($lien) ? 'a' : 'div' }}>
