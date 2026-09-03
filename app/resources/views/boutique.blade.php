@extends('layouts.app')
@section('titre', $boutique->nom)
@section('contenu')

<div class="carte" style="margin-bottom:16px">
  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <div>
      <h1 style="font-size:1.4rem;margin-bottom:4px">
        {{ $boutique->nom }}
        @if($boutique->officielle)<span class="etiq etiq-officielle">Boutique officielle</span>@endif
      </h1>
      <div style="color:var(--gris-fonce)">
        {{ $boutique->adresse }} · {{ $boutique->ville }}
      </div>
    </div>
    <div style="margin-left:auto;text-align:right">
      @if($boutique->nombre_avis)
        <div class="etoiles" style="font-size:1.05rem">
          {{ str_repeat('★', (int) round($boutique->noteSurCinq())) }}{{ str_repeat('☆', 5 - (int) round($boutique->noteSurCinq())) }}
        </div>
        <div style="color:var(--gris);font-size:.84rem">
          {{ $boutique->noteSurCinq() }} sur 5 · {{ $boutique->nombre_avis }} avis
        </div>
      @else
        <span class="etiq etiq-gris">Nouvelle boutique</span>
      @endif
      <div style="color:var(--gris);font-size:.82rem;margin-top:4px">
        {{ $produits->total() }} produits
      </div>
    </div>
  </div>
  @if($boutique->description)
    <p style="margin-top:12px;color:var(--gris-fonce)">{{ $boutique->description }}</p>
  @endif
</div>

@if($produits->isEmpty())
  <div class="carte vide">Cette boutique n'a aucun produit en vente.</div>
@else
  <div class="grille g4">
    @foreach($produits as $p)
      @include('partials.carte', ['p' => $p])
    @endforeach
  </div>
  <div style="margin-top:20px">{{ $produits->links() }}</div>
@endif

@endsection
