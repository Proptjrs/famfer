@extends('layouts.app')
@section('titre', $produit->nom)
@section('description', Str::limit($produit->description ?: $produit->nom, 150))
@section('contenu')

@php
  $note = $produit->nombre_avis ? $produit->noteSurCinq() : null;
  // Le meilleur prix ailleurs : c'est l'information qui décide, et elle était
  // noyée au milieu d'un tableau en bas de page.
  $moinsCher = $ailleurs->where('prix', '<', $produit->prix)->sortBy('prix')->first();
@endphp

<nav class="fil" aria-label="Fil d'Ariane" style="margin-bottom:var(--s4)">
  <a href="{{ route('accueil') }}">Accueil</a>
  @if($produit->categorie->parente)
    <span class="sep" aria-hidden="true">/</span>
    <a href="{{ route('rayon', $produit->categorie->parente) }}">
      {{ $produit->categorie->parente->nom }}</a>
  @endif
  <span class="sep" aria-hidden="true">/</span>
  <a href="{{ route('rayon', $produit->categorie) }}">{{ $produit->categorie->nom }}</a>
  <span class="sep" aria-hidden="true">/</span>
  <span aria-current="page" class="tronque-1">{{ $produit->nom }}</span>
</nav>

<div style="display:grid;gap:var(--s6);align-items:start;
            grid-template-columns:repeat(auto-fit,minmax(min(320px,100%),1fr));
            margin-bottom:var(--s6)">

  <div class="carte" style="padding:var(--s4)">
    <div style="aspect-ratio:1;background:var(--surface-2);border-radius:var(--r-sm);
                display:grid;place-items:center;overflow:hidden">
      @include('partials.image', ['p' => $produit, 'taille' => 260])
    </div>
    @if($produit->photos->count() > 1)
      <div class="rang-sm" style="margin-top:var(--s3)">
        @foreach($produit->photos->take(5) as $photo)
          <img src="{{ $photo->url() }}" alt=""
               style="width:3.5rem;height:3.5rem;object-fit:cover;
                      border:1px solid var(--line);border-radius:var(--r-xs)">
        @endforeach
      </div>
    @endif
  </div>

  <div class="carte pile">
    <div>
      <h1>{{ $produit->nom }}</h1>
      <div class="rang-sm" style="margin-top:var(--s2)">
        @if($produit->marque)
          <span class="petit secondaire">
            Marque <strong style="color:var(--ink-2)">{{ $produit->marque }}</strong>
          </span>
        @endif
        @if($note)
          <span class="rang-serre" style="gap:var(--s1)">
            <span class="etoiles" aria-hidden="true">{{ str_repeat('★', (int) round($note)) }}{{ str_repeat('☆', 5 - (int) round($note)) }}</span>
            <a href="#avis" class="lien petit">{{ $produit->nombre_avis }} avis</a>
            <span class="visuellement-cache">Noté {{ $note }} sur 5</span>
          </span>
        @else
          <span class="mini secondaire">Pas encore d'avis</span>
        @endif
      </div>
    </div>

    <div>
      <div class="rang-sm" style="align-items:baseline">
        <span class="chiffre" style="font-size:var(--t-3xl);font-weight:600;
                     letter-spacing:-.02em">
          {{ number_format($produit->prix, 0, ',', ' ') }} F
        </span>
        @if($remise = $produit->remise())
          <span class="barre chiffre" style="font-size:var(--t-md);color:var(--ink-3);
                       text-decoration:line-through">
            {{ number_format($produit->prix_barre, 0, ',', ' ') }} F
          </span>
          <span class="jeton jeton-grave">−{{ $remise }} %</span>
        @endif
      </div>

      @if($produit->enStock())
        <p class="rang-serre" style="gap:var(--s2);color:var(--ok-ink);
                  font-weight:650;margin-top:var(--s2)">
          @include('partials.symbole', ['nom' => 'coche', 'taille' => 16])
          En stock — {{ $produit->stock }} disponible{{ $produit->stock > 1 ? 's' : '' }}
        </p>
      @else
        <p class="rang-serre" style="gap:var(--s2);color:var(--grave-ink);
                  font-weight:650;margin-top:var(--s2)">
          @include('partials.symbole', ['nom' => 'alerte', 'taille' => 16])
          Rupture de stock chez ce vendeur
        </p>
      @endif
    </div>

    @if($produit->achetable())
      <form method="POST" action="{{ route('panier.ajouter', $produit) }}"
            class="rang" style="align-items:flex-end">
        @csrf
        <div class="champ" style="flex:0 0 6.5rem;margin:0">
          <label for="quantite">Quantité</label>
          <input id="quantite" name="quantite" type="number" value="1" min="1"
                 max="{{ $produit->stock }}" class="chiffre">
        </div>
        <button type="submit" class="btn btn-lg" style="flex:1 1 12rem">
          @include('partials.symbole', ['nom' => 'panier', 'taille' => 18])
          Ajouter au panier
        </button>
      </form>
    @else
      <div class="pile-sm">
        <button class="btn btn-lg btn-bloc" disabled>Indisponible chez ce vendeur</button>
        @if($ailleurs->where('stock', '>', 0)->isNotEmpty())
          <p class="petit secondaire">
            Cet article est en stock chez
            {{ $ailleurs->where('stock', '>', 0)->count() }} autre(s) boutique(s) —
            <a href="#ailleurs" class="lien">voir la comparaison</a>.
          </p>
        @endif
      </div>
    @endif

    {{-- Le meilleur prix ailleurs, remonté ici. Le mettre en bas de page
         revenait à cacher ce que l'acheteur est venu chercher : comparer. --}}
    @if($moinsCher)
      <a href="{{ route('produit', $moinsCher) }}" class="message message-info"
         style="text-decoration:none">
        @include('partials.symbole', ['nom' => 'argent', 'taille' => 18])
        <div>
          <strong>{{ number_format($produit->prix - $moinsCher->prix, 0, ',', ' ') }} F
          moins cher</strong> chez {{ $moinsCher->boutique->nom }} —
          {{ number_format($moinsCher->prix, 0, ',', ' ') }} F.
        </div>
      </a>
    @endif

    <div class="liste" style="border-top:1px solid var(--line);padding-top:var(--s3)">
      <div class="rang-serre">
        <span class="petit secondaire">Vendu par</span>
        <span class="pousse rang-serre" style="gap:var(--s2)">
          <a href="{{ route('boutique', $produit->boutique) }}" class="lien petit">
            {{ $produit->boutique->nom }}
          </a>
          @if($produit->boutique->officielle)
            <span class="jeton jeton-info">Officielle</span>
          @endif
        </span>
      </div>
      <div class="rang-serre">
        <span class="petit secondaire">Expédié depuis</span>
        <span class="petit pousse">{{ $produit->boutique->ville }}</span>
      </div>
      @if($produit->boutique->nombre_avis)
        <div class="rang-serre">
          <span class="petit secondaire">Note du vendeur</span>
          <span class="petit pousse chiffre" style="font-weight:650">
            {{ number_format($produit->boutique->noteSurCinq(), 1, ',', ' ') }} / 5
            <span class="mini secondaire">({{ $produit->boutique->nombre_avis }})</span>
          </span>
        </div>
      @endif
      <div class="rang-serre">
        <span class="petit secondaire">Paiement</span>
        <span class="petit pousse">En espèces, à la livraison</span>
      </div>
      <div class="rang-serre">
        <span class="petit secondaire">Livraison</span>
        <span class="petit pousse">Offerte dès 50 000 F d'achat</span>
      </div>
    </div>
  </div>
</div>

@if($produit->description)
  <div class="bloc">
    <div class="bloc-tete"><h2>Description</h2></div>
    <div class="bloc-corps"><p class="prose">{{ $produit->description }}</p></div>
  </div>
@endif

{{-- Le même article chez d'autres boutiques. C'est ce qui distingue une place
     de marché d'une boutique en ligne : le client compare avant d'acheter. --}}
@if($ailleurs->isNotEmpty())
  <div class="bloc" id="ailleurs">
    <div class="bloc-tete">
      <h2>Le même produit ailleurs</h2>
      <span class="sous">{{ $ailleurs->count() }} autre(s) vendeur(s)</span>
    </div>
    <div class="bloc-corps serre defile-x">
      <table class="tableau">
        <thead>
          <tr>
            <th scope="col">Boutique</th>
            <th scope="col">Ville</th>
            <th scope="col">Stock</th>
            <th scope="col" class="num">Prix</th>
            <th scope="col"><span class="visuellement-cache">Action</span></th>
          </tr>
        </thead>
        <tbody>
          <tr style="background:var(--brand-soft)">
            <td>
              <strong>{{ $produit->boutique->nom }}</strong>
              <span class="jeton jeton-marque">vous êtes ici</span>
            </td>
            <td class="secondaire">{{ $produit->boutique->ville }}</td>
            <td>
              @if($produit->enStock())
                <span class="chiffre" style="color:var(--ok-ink)">{{ $produit->stock }}</span>
              @else
                <span style="color:var(--grave-ink)">rupture</span>
              @endif
            </td>
            <td class="num" style="font-weight:700">
              {{ number_format($produit->prix, 0, ',', ' ') }} F
            </td>
            <td></td>
          </tr>

          @foreach($ailleurs as $autre)
            <tr>
              <td>
                <a href="{{ route('boutique', $autre->boutique) }}" class="lien">
                  {{ $autre->boutique->nom }}</a>
                @if($autre->boutique->officielle)
                  <span class="jeton jeton-info">Officielle</span>
                @endif
              </td>
              <td class="secondaire">{{ $autre->boutique->ville }}</td>
              <td>
                @if($autre->enStock())
                  <span class="chiffre" style="color:var(--ok-ink)">{{ $autre->stock }}</span>
                @else
                  <span style="color:var(--grave-ink)">rupture</span>
                @endif
              </td>
              <td class="num" style="font-weight:700">
                {{ number_format($autre->prix, 0, ',', ' ') }} F
                @if($autre->prix < $produit->prix)
                  <div class="mini" style="color:var(--ok-ink);font-weight:650">
                    −{{ number_format($produit->prix - $autre->prix, 0, ',', ' ') }} F
                  </div>
                @elseif($autre->prix > $produit->prix)
                  <div class="mini secondaire">
                    +{{ number_format($autre->prix - $produit->prix, 0, ',', ' ') }} F
                  </div>
                @endif
              </td>
              <td style="text-align:right">
                <a href="{{ route('produit', $autre) }}" class="btn btn-sm btn-clair">Voir</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

<div class="bloc" id="avis">
  <div class="bloc-tete">
    <h2>Avis des clients</h2>
    @if($note)
      <span class="rang-serre" style="gap:var(--s2)">
        <span class="etoiles" aria-hidden="true">{{ str_repeat('★', (int) round($note)) }}</span>
        <span class="sous">{{ number_format($note, 1, ',', ' ') }} sur 5 ·
          {{ $produit->nombre_avis }} avis</span>
      </span>
    @endif
  </div>
  <div class="bloc-corps">
    @forelse($avis as $a)
      <div class="pile-sm" style="padding-bottom:var(--s4);
           {{ ! $loop->last ? 'margin-bottom:var(--s4);border-bottom:1px solid var(--line)' : '' }}">
        <div class="rang-sm">
          <span class="etoiles" aria-hidden="true">{{ str_repeat('★', $a->note) }}{{ str_repeat('☆', 5 - $a->note) }}</span>
          <span class="visuellement-cache">{{ $a->note }} sur 5</span>
          @if($a->titre)<strong>{{ $a->titre }}</strong>@endif
          <span class="mini secondaire pousse">
            {{ Str::of($a->utilisateur->name)->explode(' ')->first() }} ·
            {{ $a->created_at->translatedFormat('j F Y') }}
          </span>
        </div>
        <p style="color:var(--ink-2)">{{ $a->commentaire }}</p>
      </div>
    @empty
      @include('partials.vide', [
        'icone' => 'etoile',
        'titre' => 'Aucun avis pour l\'instant',
        'texte' => 'Seuls les clients ayant reçu ce produit peuvent en laisser un — c\'est ce qui rend les notes de FamFer fiables.',
      ])
    @endforelse
  </div>
</div>

@if($similaires->isNotEmpty())
  <div class="bloc">
    <div class="bloc-tete">
      <h2>Dans le même rayon</h2>
      <a href="{{ route('rayon', $produit->categorie) }}"
         class="btn btn-sm btn-clair pousse">Tout le rayon</a>
    </div>
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
