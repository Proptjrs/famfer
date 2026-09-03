@extends('layouts.app')
@section('titre', $produit->nom)
@section('contenu')

<div style="color:var(--gris);font-size:.84rem;margin-bottom:12px">
  <a href="{{ route('accueil') }}">Accueil</a> ›
  @if($produit->categorie->parente)
    <a href="{{ route('rayon', $produit->categorie->parente) }}">{{ $produit->categorie->parente->nom }}</a> ›
  @endif
  <a href="{{ route('rayon', $produit->categorie) }}">{{ $produit->categorie->nom }}</a>
</div>

<div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;margin-bottom:16px">

  <div class="carte" style="flex:0 0 340px">
    <div style="aspect-ratio:1;background:var(--fond);border-radius:var(--r);
                display:flex;align-items:center;justify-content:center">
      @include('partials.dessin', ['dessin' => $produit->dessin, 'taille' => 220])
    </div>
  </div>

  <div class="carte" style="flex:1 1 340px;min-width:0">
    <h1 style="font-size:1.35rem;margin-bottom:8px">{{ $produit->nom }}</h1>

    <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap">
      @if($produit->marque)
        <span style="color:var(--gris);font-size:.86rem">Marque : <strong>{{ $produit->marque }}</strong></span>
      @endif
      @if($produit->nombre_avis)
        <span class="etoiles">{{ str_repeat('★', (int) round($produit->noteSurCinq())) }}{{ str_repeat('☆', 5 - (int) round($produit->noteSurCinq())) }}</span>
        <span style="color:var(--bleu);font-size:.84rem">{{ $produit->nombre_avis }} avis</span>
      @endif
    </div>

    <div style="display:flex;gap:12px;align-items:baseline;margin-bottom:6px;flex-wrap:wrap">
      <span style="font-size:1.9rem;font-weight:800">
        {{ number_format($produit->prix, 0, ',', ' ') }} F
      </span>
      @if($remise = $produit->remise())
        <span class="barre" style="font-size:1rem;color:var(--gris);text-decoration:line-through">
          {{ number_format($produit->prix_barre, 0, ',', ' ') }} F
        </span>
        <span class="etiq etiq-orange">−{{ $remise }} %</span>
      @endif
    </div>

    <div style="margin-bottom:14px">
      @if($produit->enStock())
        <span style="color:var(--vert);font-weight:600">
          En stock — {{ $produit->stock }} disponible{{ $produit->stock > 1 ? 's' : '' }}
        </span>
      @else
        <span style="color:var(--rouge);font-weight:600">Rupture de stock</span>
      @endif
    </div>

    @if($produit->achetable())
      <form method="POST" action="{{ route('panier.ajouter', $produit) }}"
            style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px">
        @csrf
        <div style="flex:0 0 96px">
          <label>Quantité</label>
          <input name="quantite" type="number" value="1" min="1" max="{{ $produit->stock }}">
        </div>
        <button class="btn" style="flex:1 1 180px">Ajouter au panier</button>
      </form>
    @else
      <button class="btn" disabled style="width:100%;margin-bottom:14px">Indisponible</button>
    @endif

    <div style="border-top:1px solid var(--bord);padding-top:12px;font-size:.88rem">
      <div style="margin-bottom:6px">
        <strong>Vendu par</strong>
        <a href="{{ route('boutique', $produit->boutique) }}"
           style="color:var(--bleu);font-weight:600">{{ $produit->boutique->nom }}</a>
        @if($produit->boutique->officielle)
          <span class="etiq etiq-officielle">Officielle</span>
        @endif
      </div>
      <div style="color:var(--gris-fonce)">
        {{ $produit->boutique->ville }}
        @if($produit->boutique->nombre_avis)
          · <span class="etoiles">★</span> {{ $produit->boutique->noteSurCinq() }} sur 5
        @endif
      </div>
      <div style="margin-top:10px;color:var(--gris-fonce)">
        Paiement à la livraison · Livraison offerte dès 50 000 F
      </div>
    </div>
  </div>
</div>

@if($produit->description)
  <div class="bloc">
    <div class="bloc-tete"><h2>Description</h2></div>
    <div class="bloc-corps">{{ $produit->description }}</div>
  </div>
@endif

{{-- Le même article chez d'autres boutiques. C'est ce qui distingue une place
     de marché d'une boutique en ligne : le client compare avant d'acheter. --}}
@if($ailleurs->isNotEmpty())
  <div class="bloc">
    <div class="bloc-tete"><h2>Le même produit ailleurs</h2></div>
    <div class="bloc-corps large">
      <table>
        <tr><th>Boutique</th><th>Ville</th><th>Stock</th><th style="text-align:right">Prix</th><th></th></tr>
        @foreach($ailleurs as $autre)
          <tr>
            <td>
              <a href="{{ route('boutique', $autre->boutique) }}"
                 style="color:var(--bleu)">{{ $autre->boutique->nom }}</a>
              @if($autre->boutique->officielle)<span class="etiq etiq-officielle">Officielle</span>@endif
            </td>
            <td style="color:var(--gris)">{{ $autre->boutique->ville }}</td>
            <td>
              @if($autre->enStock())
                <span style="color:var(--vert)">{{ $autre->stock }}</span>
              @else
                <span style="color:var(--rouge)">rupture</span>
              @endif
            </td>
            <td class="mono" style="text-align:right;font-weight:700">
              {{ number_format($autre->prix, 0, ',', ' ') }} F
              @if($autre->prix < $produit->prix)
                <br><span style="color:var(--vert);font-size:.78rem;font-weight:600">
                  −{{ number_format($produit->prix - $autre->prix, 0, ',', ' ') }} F
                </span>
              @endif
            </td>
            <td style="text-align:right">
              <a href="{{ route('produit', $autre) }}" class="btn btn-sm btn-clair">Voir</a>
            </td>
          </tr>
        @endforeach
      </table>
    </div>
  </div>
@endif

<div class="bloc">
  <div class="bloc-tete">
    <h2>Avis des clients</h2>
    @if($produit->nombre_avis)
      <span class="etoiles">{{ str_repeat('★', (int) round($produit->noteSurCinq())) }}</span>
      <span style="color:var(--gris)">{{ $produit->noteSurCinq() }} sur 5 · {{ $produit->nombre_avis }} avis</span>
    @endif
  </div>
  <div class="bloc-corps">
    @forelse($avis as $a)
      <div style="padding-bottom:12px;margin-bottom:12px;border-bottom:1px solid var(--bord)">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <span class="etoiles">{{ str_repeat('★', $a->note) }}{{ str_repeat('☆', 5 - $a->note) }}</span>
          @if($a->titre)<strong>{{ $a->titre }}</strong>@endif
          <span style="color:var(--gris);font-size:.82rem;margin-left:auto">
            {{ Str::of($a->utilisateur->name)->explode(' ')->first() }} ·
            {{ $a->created_at->translatedFormat('j F Y') }}
          </span>
        </div>
        <p style="margin-top:6px">{{ $a->commentaire }}</p>
      </div>
    @empty
      <div class="vide">
        Aucun avis pour l'instant. Seuls les clients ayant reçu ce produit
        peuvent en laisser un.
      </div>
    @endforelse
  </div>
</div>

@if($similaires->isNotEmpty())
  <div class="bloc">
    <div class="bloc-tete"><h2>Vous aimerez aussi</h2></div>
    <div class="bloc-corps">
      <div class="grille g4">
        @foreach($similaires as $p)
          @include('partials.carte', ['p' => $p])
        @endforeach
      </div>
    </div>
  </div>
@endif

@endsection
