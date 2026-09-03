@extends('layouts.app')
@section('titre', 'Mes commandes')
@section('contenu')

<h1>Mes commandes</h1>
<p style="color:var(--gris);margin-bottom:16px">
  Vous réglez à la livraison : rien n'est prélevé avant que le colis n'arrive.
</p>

@forelse($liste as $c)
  <a href="{{ route('mes-commandes.detail', $c) }}" class="carte"
     style="display:block;margin-bottom:12px">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <strong>{{ $c->reference }}</strong>
      @include('partials.etat', ['etat' => $c->etat])
      <span style="color:var(--gris);font-size:.86rem">
        {{ $c->created_at->translatedFormat('j F Y') }} ·
        {{ $c->lignes->count() }} article{{ $c->lignes->count() > 1 ? 's' : '' }}
      </span>
      <strong class="mono" style="margin-left:auto">
        {{ number_format($c->total, 0, ',', ' ') }} F
      </strong>
    </div>
    <div style="color:var(--gris);font-size:.86rem;margin-top:6px">
      {{ $c->lignes->pluck('nom_produit')->take(2)->implode(' · ') }}
      @if($c->lignes->count() > 2) · et {{ $c->lignes->count() - 2 }} autre(s) @endif
    </div>
  </a>
@empty
  <div class="carte vide">
    Aucune commande.<br>
    <a href="{{ route('accueil') }}" class="btn" style="margin-top:14px">Voir le catalogue</a>
  </div>
@endforelse

<div style="margin-top:18px">{{ $liste->links() }}</div>
@endsection
