@extends('layouts.app')
@section('titre', 'Journal — ' . $offre->article->designation)
@section('contenu')

@php
  $libelles = [
    'entree' => ['Arrivage', 'etiq-vert'],
    'reservation' => ['Réservé pour une commande', 'etiq-ambre'],
    'liberation' => ['Réservation libérée', 'etiq-gris'],
    'sortie' => ['Remis au client', 'etiq-gris'],
    'retour' => ['Retour en stock', 'etiq-vert'],
  ];
@endphp

<h1>{{ $offre->article->designation }}</h1>
<p class="sous">
  {{ $offre->article->reference }} · journal de stock —
  chaque gramme présent s'explique par une ligne ci-dessous.
</p>

<div class="grille g3" style="margin-bottom:24px">
  <div class="carte">
    <div class="chiffre mono">{{ number_format($cumul / 1000, 1, ',', ' ') }} kg</div>
    <div class="chiffre-note">Somme du journal</div>
  </div>
  <div class="carte">
    <div class="chiffre mono">{{ number_format($offre->quantite_pivot / 1000, 1, ',', ' ') }} kg</div>
    <div class="chiffre-note">Stock enregistré sur l'offre</div>
  </div>
  <div class="carte" style="{{ $cumul === $offre->quantite_pivot ? '' : 'border-color:var(--rouge)' }}">
    @if($cumul === $offre->quantite_pivot)
      <span class="etiq etiq-vert">Concordant</span>
      <div class="chiffre-note" style="margin-top:8px">
        Le compteur affiché est bien la somme des mouvements.
      </div>
    @else
      <span class="etiq etiq-rouge">Écart de
        {{ number_format(abs($cumul - $offre->quantite_pivot) / 1000, 1, ',', ' ') }} kg</span>
      <div class="chiffre-note" style="margin-top:8px">
        Signalez-le : le compteur ne peut pas s'écarter du journal.
      </div>
    @endif
  </div>
</div>

<div class="carte tableau-large"><table>
  <tr>
    <th>Date</th><th>Mouvement</th><th style="text-align:right">Quantité</th>
    <th style="text-align:right">Stock après</th><th>Motif</th>
  </tr>
  @foreach($mouvements as $m)
    @php [$mot, $classe] = $libelles[$m->type] ?? [$m->type, 'etiq-gris']; @endphp
    <tr>
      <td style="white-space:nowrap">{{ $m->created_at->translatedFormat('j M Y H:i') }}</td>
      <td><span class="etiq {{ $classe }}">{{ $mot }}</span></td>
      <td class="mono" style="text-align:right;color:{{ $m->quantite_pivot < 0 ? 'var(--rouge)' : 'var(--vert)' }}">
        {{ $m->quantite_pivot > 0 ? '+' : '' }}{{ number_format($m->quantite_pivot / 1000, 1, ',', ' ') }} kg
      </td>
      <td class="mono" style="text-align:right">{{ number_format($m->cumul / 1000, 1, ',', ' ') }} kg</td>
      <td style="color:var(--gris);font-size:.88rem">{{ $m->motif }}</td>
    </tr>
  @endforeach
</table></div>

<p style="color:var(--gris);font-size:.86rem;margin-top:20px;max-width:70ch">
  Aucune ligne n'est jamais modifiée ni effacée. Une erreur se corrige par un
  mouvement inverse, qui laisse les deux traces : c'est ce qui permet de
  reconstituer l'état du stock à n'importe quelle date passée.
</p>

<p style="margin-top:18px"><a href="{{ route('vendeur.offres') }}">← Retour à mes offres</a></p>

@endsection
