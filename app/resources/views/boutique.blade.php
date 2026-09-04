@extends('layouts.app')
@section('titre', $boutique->nom)
@section('description', 'Quincaillerie ' . $boutique->nom . ' à ' . $boutique->ville
  . ' — ' . $produits->total() . ' produits livrés partout au Sénégal.')
@section('contenu')

@php $note = $boutique->nombre_avis ? $boutique->noteSurCinq() : null; @endphp

<nav class="fil" aria-label="Fil d'Ariane" style="margin-bottom:var(--s4)">
  <a href="{{ route('accueil') }}">Accueil</a>
  <span class="sep" aria-hidden="true">/</span>
  <span aria-current="page">{{ $boutique->nom }}</span>
</nav>

<div class="carte" style="margin-bottom:var(--s6)">
  <div class="rang" style="gap:var(--s5);align-items:flex-start">

    <span style="flex:none;width:3.5rem;height:3.5rem;border-radius:var(--r-sm);
                 background:var(--brand-soft);color:var(--brand-strong);
                 display:grid;place-items:center">
      @include('partials.symbole', ['nom' => 'boutique', 'taille' => 26])
    </span>

    <div style="flex:1 1 18rem;min-width:0">
      <h1>{{ $boutique->nom }}</h1>
      <div class="rang-sm" style="margin-top:var(--s2)">
        @if($boutique->officielle)
          <span class="jeton jeton-info">Boutique officielle</span>
        @endif
        @if(! $note)
          <span class="jeton jeton-neutre">Nouvelle boutique</span>
        @endif
      </div>
      <p class="petit secondaire" style="margin-top:var(--s2)">
        {{ $boutique->adresse }} · {{ $boutique->ville }}
      </p>
      @if($boutique->description)
        <p style="margin-top:var(--s3);color:var(--ink-2);max-width:68ch">
          {{ $boutique->description }}
        </p>
      @endif
    </div>

    <div class="pile-sm" style="flex:none;text-align:right;min-width:9rem">
      @if($note)
        <div>
          <div class="etoiles" style="font-size:var(--t-md)" aria-hidden="true">
            {{ str_repeat('★', (int) round($note)) }}{{ str_repeat('☆', 5 - (int) round($note)) }}
          </div>
          <div class="petit secondaire">
            <span class="chiffre">{{ number_format($note, 1, ',', ' ') }}</span> sur 5 ·
            {{ $boutique->nombre_avis }} avis
          </div>
        </div>
      @endif
      <div>
        <div class="chiffre" style="font-size:var(--t-xl);font-weight:600">
          {{ $produits->total() }}
        </div>
        <div class="mini secondaire">produits en vente</div>
      </div>
    </div>
  </div>
</div>

@if($produits->isEmpty())
  <div class="bloc">
    @include('partials.vide', [
      'icone' => 'boutique',
      'titre' => 'Aucun produit en vente',
      'texte' => 'Cette boutique n\'a pas encore publié d\'article, ou les a tous retirés.',
      'action' => '<a href="' . route('accueil') . '" class="btn">Voir les autres boutiques</a>',
    ])
  </div>
@else
  <div class="grille g4">
    @foreach($produits as $p)
      @include('partials.carte', ['p' => $p])
    @endforeach
  </div>

  @if($produits->hasPages())
    <div style="margin-top:var(--s6)">{{ $produits->links() }}</div>
  @endif
@endif

@endsection
