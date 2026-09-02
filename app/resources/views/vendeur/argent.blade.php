@extends('layouts.app')
@section('titre', 'Mon argent')
@section('contenu')

<h1>Mon argent</h1>
<p class="sous">
  FamFer encaisse pour votre compte et vous reverse. Cette page dit exactement
  où en est chaque franc.
</p>

<div class="grille g3" style="margin-bottom:24px">
  <div class="carte">
    <div class="chiffre mono">{{ number_format($solde, 0, ',', ' ') }} F</div>
    <div class="chiffre-note">Disponible pour virement</div>
  </div>
  <div class="carte">
    <div class="chiffre mono">{{ $aSolder }}</div>
    <div class="chiffre-note">
      Commandes reçues, pas encore soldées
      @if($aSolder > 0)
        <br><span style="color:var(--gris)">Elles basculeront à votre prochaine demande.</span>
      @endif
    </div>
  </div>
  <div class="carte" style="{{ $litiges > 0 ? 'border-color:var(--rouge)' : '' }}">
    <div class="chiffre mono">{{ $litiges }}</div>
    <div class="chiffre-note">
      Litiges ouverts
      @if($litiges > 0)
        <br><span style="color:var(--rouge)">Tout virement est gelé tant qu'un litige n'est pas tranché.</span>
      @endif
    </div>
  </div>
</div>

<form method="POST" action="{{ route('vendeur.reversement') }}" class="carte" style="margin-bottom:26px">
  @csrf
  <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center">
    <button class="btn" @disabled($litiges > 0 || ($solde <= 0 && $aSolder === 0))>
      Demander mon virement
    </button>
    <span style="color:var(--gris);font-size:.88rem">
      Le montant part vers le compte Wave ou Orange Money enregistré à votre nom.
    </span>
  </div>
</form>

<h2>Mes virements</h2>
@php
  $etats = [
    'prepare' => ['Préparé', 'etiq-ambre'],
    'envoye' => ['Envoyé', 'etiq-vert'],
    'confirme' => ['Confirmé', 'etiq-vert'],
    'echoue' => ['Échoué', 'etiq-rouge'],
  ];
@endphp

@if($reversements->isEmpty())
  <div class="carte vide">
    Aucun virement pour l'instant. Le premier partira dès qu'une commande sera
    reçue par votre client.
  </div>
@else
  <div class="carte tableau-large"><table>
    <tr><th>Date</th><th>Montant</th><th>État</th><th>Référence</th></tr>
    @foreach($reversements as $rev)
      @php [$mot, $classe] = $etats[$rev->etat] ?? [$rev->etat, 'etiq-gris']; @endphp
      <tr>
        <td>{{ ($rev->envoye_le ?? $rev->created_at)->translatedFormat('j F Y à H\hi') }}</td>
        <td class="mono" style="font-weight:700">{{ number_format($rev->montant, 0, ',', ' ') }} F</td>
        <td><span class="etiq {{ $classe }}">{{ $mot }}</span></td>
        <td style="color:var(--gris)">{{ $rev->reference_virement ?? '—' }}</td>
      </tr>
    @endforeach
  </table></div>

  <div style="margin-top:18px">{{ $reversements->links() }}</div>
@endif

<p style="color:var(--gris);font-size:.86rem;margin-top:22px;max-width:70ch">
  Le disponible affiché vient du grand livre de la plateforme, pas d'un cumul de
  commandes : c'est exactement la somme qui sera virée. Tant qu'une commande
  n'est pas reçue par l'acheteur, son montant reste au séquestre et ne vous est
  pas dû — c'est la contrepartie de la garantie offerte à vos clients.
</p>

@endsection
