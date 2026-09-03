@extends('layouts.app')
@section('titre', 'Les boutiques')
@section('contenu')

<h1>Les boutiques</h1>

@php $mots = ['en_attente' => 'En attente', 'active' => 'Actives', 'suspendue' => 'Suspendues']; @endphp

<div style="display:flex;gap:6px;flex-wrap:wrap;margin:14px 0">
  <a href="{{ route('admin.boutiques') }}"
     class="btn btn-sm {{ $statutFiltre ? 'btn-clair' : '' }}">
    Toutes <span style="opacity:.75">{{ $parStatut->sum() }}</span>
  </a>
  @foreach($mots as $cle => $mot)
    @continue(! isset($parStatut[$cle]))
    <a href="{{ route('admin.boutiques', ['statut' => $cle]) }}"
       class="btn btn-sm {{ $statutFiltre === $cle ? '' : 'btn-clair' }}">
      {{ $mot }} <span style="opacity:.75">{{ $parStatut[$cle] }}</span>
    </a>
  @endforeach
</div>

<div class="carte large">
  <table>
    <tr>
      <th>Boutique</th><th>Tenue par</th><th>Ville</th>
      <th>Produits</th><th>Note</th><th>État</th><th></th>
    </tr>
    @forelse($boutiques as $b)
      <tr>
        <td>
          @if($b->estVisible())
            <a href="{{ route('boutique', $b) }}" style="color:var(--bleu);font-weight:600">{{ $b->nom }}</a>
          @else
            <strong>{{ $b->nom }}</strong>
          @endif
          @if($b->officielle)<span class="etiq etiq-officielle">Officielle</span>@endif
        </td>
        <td style="color:var(--gris)">
          {{ $b->utilisateur->name }}<br>
          <span style="font-size:.82rem">{{ $b->telephone }}</span>
        </td>
        <td style="color:var(--gris)">{{ $b->ville }}</td>
        <td class="mono">{{ $b->produits_count }}</td>
        <td>
          @if($b->nombre_avis)
            <span class="etoiles">★</span> {{ $b->noteSurCinq() }}
            <span style="color:var(--gris);font-size:.8rem">({{ $b->nombre_avis }})</span>
          @else
            <span style="color:var(--gris)">—</span>
          @endif
        </td>
        <td>
          @if($b->statut === 'active')<span class="etiq etiq-vert">Active</span>
          @elseif($b->statut === 'en_attente')<span class="etiq etiq-orange">En attente</span>
          @else
            <span class="etiq etiq-rouge">Suspendue</span>
            @if($b->motif_suspension)
              <br><span style="color:var(--gris);font-size:.78rem">{{ $b->motif_suspension }}</span>
            @endif
          @endif
        </td>
        <td style="text-align:right;white-space:nowrap">
          @if($b->statut !== 'active')
            <form method="POST" action="{{ route('admin.activer', $b) }}" style="display:inline">
              @csrf <button class="btn btn-sm btn-vert">Activer</button>
            </form>
          @else
            <details style="display:inline-block">
              <summary class="btn btn-sm btn-clair" style="list-style:none">Suspendre</summary>
              <form method="POST" action="{{ route('admin.suspendre', $b) }}" class="carte"
                    style="margin-top:8px;min-width:250px;text-align:left">
                @csrf
                <div class="champ"><label>Motif</label>
                  <textarea name="motif" rows="2" required minlength="5"></textarea></div>
                <button class="btn btn-sm btn-rouge">Suspendre</button>
              </form>
            </details>
          @endif
          {{-- La mise en avant réservée aux enseignes démarchées. --}}
          <form method="POST" action="{{ route('admin.officielle', $b) }}" style="display:inline">
            @csrf
            <button class="btn btn-sm btn-clair">
              {{ $b->officielle ? 'Retirer « officielle »' : 'Rendre officielle' }}
            </button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="7" style="color:var(--gris)">Aucune boutique.</td></tr>
    @endforelse
  </table>
</div>

<div style="margin-top:18px">{{ $boutiques->links() }}</div>

@endsection
