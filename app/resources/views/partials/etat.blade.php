{{--
  L'étiquette d'un état de commande, la même partout.

  « Refusée » et « annulée » sont distinctes à dessein : l'une veut dire que le
  colis est parti et que le client l'a repoussé à la porte — une tournée
  perdue — l'autre qu'il s'est ravisé avant l'expédition.

  Variable : $etat (string)
--}}
@php
  [$mot, $classe] = match ($etat) {
    'en_preparation' => ['En préparation', 'etiq-gris'],
    'expediee' => ['Expédiée', 'etiq-orange'],
    'en_livraison' => ['En cours de livraison', 'etiq-orange'],
    'livree' => ['Livrée', 'etiq-vert'],
    'refusee' => ['Refusée à la livraison', 'etiq-rouge'],
    'annulee' => ['Annulée', 'etiq-gris'],
    'retournee' => ['Retournée', 'etiq-rouge'],
    default => [$etat, 'etiq-gris'],
  };
@endphp
<span class="etiq {{ $classe }}">{{ $mot }}</span>
