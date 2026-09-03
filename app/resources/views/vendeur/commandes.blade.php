@extends('layouts.app')
@section('titre', 'Mes ventes')
@section('contenu')

<h1>Mes ventes</h1>
<p style="color:var(--gris);margin-bottom:14px">
  Tout ce qui est passé par votre boutique, y compris ce qui n'a pas abouti.
</p>

@php
  $libelles = [
    'en_preparation' => 'À expédier', 'expediee' => 'Expédiées',
    'en_livraison' => 'En livraison', 'livree' => 'Livrées',
    'refusee' => 'Refusées', 'annulee' => 'Annulées', 'retournee' => 'Retournées',
  ];
@endphp

<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
  <a href="{{ route('vendeur.commandes') }}"
     class="btn btn-sm {{ $etatFiltre ? 'btn-clair' : '' }}">
    Toutes <span style="opacity:.75">{{ $parEtat->sum() }}</span>
  </a>
  @foreach($libelles as $cle => $mot)
    @continue(! isset($parEtat[$cle]))
    <a href="{{ route('vendeur.commandes', ['etat' => $cle]) }}"
       class="btn btn-sm {{ $etatFiltre === $cle ? '' : 'btn-clair' }}">
      {{ $mot }} <span style="opacity:.75">{{ $parEtat[$cle] }}</span>
    </a>
  @endforeach
</div>

@forelse($liste as $c)
  @php $miennes = $c->lignes->where('boutique_id', $boutique->id); @endphp
  <div class="carte" style="margin-bottom:12px">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <strong>{{ $c->reference }}</strong>
      @include('partials.etat', ['etat' => $c->etat])
      <span style="color:var(--gris);font-size:.85rem">
        {{ $c->utilisateur->name }} · {{ $c->created_at->translatedFormat('j M Y') }}
      </span>
      <span class="mono" style="margin-left:auto;font-weight:700">
        {{ number_format($miennes->sum('montant'), 0, ',', ' ') }} F
      </span>
    </div>

    <div style="color:var(--gris);font-size:.86rem;margin-top:6px">
      @foreach($miennes as $ligne)
        {{ $ligne->quantite }} × {{ $ligne->nom_produit }}<br>
      @endforeach
    </div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px;
                padding-top:10px;border-top:1px solid var(--bord)">
      <span style="color:var(--gris);font-size:.85rem">
        {{ $c->destinataire }} · {{ $c->telephone }} · {{ $c->adresse_livraison }}
      </span>

      @if($c->etat === 'en_preparation')
        <form method="POST" action="{{ route('vendeur.expedier', $c) }}" style="margin-left:auto">
          @csrf <button class="btn btn-sm">Expédier</button>
        </form>
      @elseif(in_array($c->etat, ['expediee', 'en_livraison']))
        <form method="POST" action="{{ route('vendeur.livrer', $c) }}" style="margin-left:auto">
          @csrf <button class="btn btn-sm btn-vert">Marquer livrée</button>
        </form>
      @elseif($c->motif)
        <span style="color:var(--rouge);font-size:.85rem;margin-left:auto">{{ $c->motif }}</span>
      @endif
    </div>
  </div>
@empty
  <div class="carte vide">
    @if($etatFiltre)
      Aucune commande dans cet état.
      <a href="{{ route('vendeur.commandes') }}">Voir toutes</a>
    @else
      Aucune vente pour l'instant.
    @endif
  </div>
@endforelse

<div style="margin-top:18px">{{ $liste->links() }}</div>

@endsection
