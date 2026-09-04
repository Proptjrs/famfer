{{--
  L'état d'une boutique, la même étiquette partout.

  Trois statuts et une distinction : « officielle » n'est pas un statut mais une
  qualité qui s'y ajoute — une enseigne démarchée par la plateforme peut être
  active, en attente ou suspendue comme les autres. Les mélanger dans un même
  jeu d'étiquettes rendait l'écran d'administration illisible.

  Variables : $boutique (Boutique)
--}}
@php
  [$mot, $ton] = match ($boutique->statut) {
    'active'     => ['Active', 'ok'],
    'en_attente' => ['En attente de validation', 'alerte'],
    'suspendue'  => ['Suspendue', 'grave'],
    default      => [$boutique->statut, 'neutre'],
  };
@endphp

<span class="jeton jeton-{{ $ton }}">
  <span class="point" aria-hidden="true"></span>{{ $mot }}
</span>

@if($boutique->officielle)
  <span class="jeton jeton-info" title="Enseigne démarchée par la plateforme">
    Boutique officielle
  </span>
@endif
