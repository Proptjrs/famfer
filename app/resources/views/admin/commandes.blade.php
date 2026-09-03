@extends('layouts.app')
@section('titre', 'Les commandes')
@section('contenu')

<h1>Les commandes</h1>

@php
  $mots = [
    'en_preparation' => 'En préparation', 'expediee' => 'Expédiées',
    'en_livraison' => 'En livraison', 'livree' => 'Livrées',
    'refusee' => 'Refusées', 'annulee' => 'Annulées', 'retournee' => 'Retournées',
  ];
@endphp

<div style="display:flex;gap:6px;flex-wrap:wrap;margin:14px 0">
  <a href="{{ route('admin.commandes') }}"
     class="btn btn-sm {{ $etatFiltre ? 'btn-clair' : '' }}">
    Toutes <span style="opacity:.75">{{ $parEtat->sum() }}</span>
  </a>
  @foreach($mots as $cle => $mot)
    @continue(! isset($parEtat[$cle]))
    <a href="{{ route('admin.commandes', ['etat' => $cle]) }}"
       class="btn btn-sm {{ $etatFiltre === $cle ? '' : 'btn-clair' }}">
      {{ $mot }} <span style="opacity:.75">{{ $parEtat[$cle] }}</span>
    </a>
  @endforeach
</div>

<div class="carte large">
  <table>
    <tr>
      <th>Référence</th><th>Client</th><th>Livraison</th>
      <th>Articles</th><th style="text-align:right">Total</th><th>État</th>
    </tr>
    @forelse($liste as $c)
      <tr>
        <td>
          <strong>{{ $c->reference }}</strong><br>
          <span style="color:var(--gris);font-size:.8rem">
            {{ $c->created_at->translatedFormat('j M Y') }}
          </span>
        </td>
        <td>
          {{ $c->utilisateur->name }}<br>
          <span style="color:var(--gris);font-size:.82rem">{{ $c->telephone }}</span>
        </td>
        <td style="color:var(--gris);font-size:.84rem">{{ $c->adresse_livraison }}</td>
        <td class="mono">{{ $c->lignes->sum('quantite') }}</td>
        <td class="mono" style="text-align:right;font-weight:700">
          {{ number_format($c->total, 0, ',', ' ') }} F
          <br><span style="color:var(--gris);font-size:.78rem;font-weight:400">
            @if($c->paiement === 'livraison') à la livraison
            @else {{ strtoupper($c->paiement) }} @endif
          </span>
        </td>
        <td>
          @include('partials.etat', ['etat' => $c->etat])
          @if($c->motif)
            <br><span style="color:var(--gris);font-size:.78rem">{{ $c->motif }}</span>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="6" style="color:var(--gris)">Aucune commande.</td></tr>
    @endforelse
  </table>
</div>

<div style="margin-top:18px">{{ $liste->links() }}</div>

@endsection
