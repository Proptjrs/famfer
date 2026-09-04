{{--
  L'étiquette d'un état de commande, la même partout.

  Elle porte un mot ET un point coloré. La couleur seule ne distingue pas deux
  états pour qui ne la perçoit pas, et huit pour cent des hommes sont dans ce
  cas : le mot reste donc toujours présent, la couleur ne fait que l'accélérer.

  « Refusée » et « annulée » sont distinctes à dessein : l'une veut dire que le
  colis est parti et que le client l'a repoussé à la porte — une tournée perdue —
  l'autre qu'il s'est ravisé avant l'expédition.

  Variables : $etat (string), $sobre (bool, défaut faux) — sans le point.
--}}
@php
  [$mot, $ton] = match ($etat) {
    'en_preparation' => ['En préparation',        'neutre'],
    'expediee'       => ['Expédiée',              'info'],
    'en_livraison'   => ['En cours de livraison', 'info'],
    'livree'         => ['Livrée',                'ok'],
    'refusee'        => ['Refusée à la livraison','grave'],
    'annulee'        => ['Annulée',               'neutre'],
    'retournee'      => ['Retournée',             'grave'],
    'litige'         => ['Litige en cours',       'alerte'],
    default          => [$etat,                   'neutre'],
  };
@endphp

<span class="jeton jeton-{{ $ton }}">
  @unless($sobre ?? false)<span class="point" aria-hidden="true"></span>@endunless
  {{ $mot }}
</span>
