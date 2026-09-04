{{--
  La légende d'une répartition.

  Le même balisage était recopié dans les deux tableaux de bord, avec la même
  table de correspondance entre le ton et la couleur écrite à la main dans
  chacun. Deux copies d'une correspondance, c'est une divergence en attente.

  Elle rend la barre empilée ET sa légende : les deux ne se lisent pas l'une
  sans l'autre, et les séparer laissait la barre sans clé de lecture.

  Variable : $parts — [['libelle', 'nombre', 'part', 'ton'], …]
--}}
@php
  $couleurs = [
    'ok' => 'var(--ok)', 'grave' => 'var(--grave)', 'alerte' => 'var(--alerte)',
    'info' => 'var(--info)', 'neutre' => 'var(--line-strong)',
  ];
@endphp

@if($parts->isEmpty())
  <p class="petit secondaire">Aucune commande enregistrée pour l'instant.</p>
@else
  <div class="repartition" role="img"
       aria-label="Répartition des commandes : {{ $parts->map(fn ($e) => $e['libelle'] . ' ' . $e['nombre'])->implode(', ') }}">
    @foreach($parts as $e)
      <span style="width:{{ $e['part'] }}%;background:{{ $couleurs[$e['ton']] ?? 'var(--line-strong)' }}"></span>
    @endforeach
  </div>

  <ul class="legende" style="flex-direction:column;gap:var(--s2);list-style:none;
      margin-top:var(--s4)">
    @foreach($parts as $e)
      <li style="display:flex;align-items:center;gap:var(--s2);width:100%">
        <i style="background:{{ $couleurs[$e['ton']] ?? 'var(--line-strong)' }}"
           aria-hidden="true"></i>
        <span>{{ $e['libelle'] }}</span>
        <span class="chiffre pousse">
          {{ $e['nombre'] }}
          <span class="mini">({{ number_format($e['part'], 1, ',', ' ') }} %)</span>
        </span>
      </li>
    @endforeach
  </ul>
@endif
