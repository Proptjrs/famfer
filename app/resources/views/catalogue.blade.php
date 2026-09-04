@extends('layouts.app')
@section('titre', $titre)
@section('contenu')

@php
  $filtresActifs = collect(['min' => 'prix minimum', 'max' => 'prix maximum',
                            'marque' => 'marque', 'stock' => 'en stock'])
    ->filter(fn ($lib, $cle) => request()->filled($cle));
@endphp

@include('partials.entete', [
  'titre' => $titre,
  'sous' => $produits->total() . ' produit' . ($produits->total() > 1 ? 's' : '')
    . ($filtresActifs->isNotEmpty()
        ? ' après ' . $filtresActifs->count() . ' filtre(s)' : ' au catalogue'),
  'fil' => array_values(array_filter([
    ['libelle' => 'Accueil', 'url' => route('accueil')],
    $categorie && $categorie->parente
      ? ['libelle' => $categorie->parente->nom, 'url' => route('rayon', $categorie->parente)]
      : null,
    ['libelle' => $categorie->nom ?? $titre],
  ])),
])

<div style="display:grid;gap:var(--s6);align-items:start;
            grid-template-columns:minmax(0,1fr)" class="catalogue-grille">

  {{-- Les filtres. Ils tiennent la gauche sur grand écran, et passent au-dessus
       de la liste sur petit : une colonne de 232 px sur un téléphone laissait
       moins de place aux produits qu'aux champs qui servent à les trouver. --}}
  <aside class="bloc" id="filtres">
    <div class="bloc-tete">
      <h2>Affiner</h2>
      @if($filtresActifs->isNotEmpty())
        <span class="jeton jeton-marque">{{ $filtresActifs->count() }}</span>
      @endif
    </div>
    <div class="bloc-corps">
      <form method="GET" class="pile">
        @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
        @if(request('tri'))<input type="hidden" name="tri" value="{{ request('tri') }}">@endif

        <fieldset>
          <legend style="font-size:var(--t-xs);font-family:var(--f-ui);
                         font-weight:650;color:var(--ink-2);margin-bottom:var(--s2)">
            Prix, en francs
          </legend>
          <div style="display:flex;gap:var(--s2)">
            <div style="flex:1">
              <label for="min" class="visuellement-cache">Prix minimum</label>
              <input id="min" name="min" value="{{ request('min') }}"
                     placeholder="min" inputmode="numeric" class="chiffre">
            </div>
            <div style="flex:1">
              <label for="max" class="visuellement-cache">Prix maximum</label>
              <input id="max" name="max" value="{{ request('max') }}"
                     placeholder="max" inputmode="numeric" class="chiffre">
            </div>
          </div>
        </fieldset>

        @if($marques)
          <div class="champ">
            <label for="marque">Marque</label>
            <select id="marque" name="marque">
              <option value="">Toutes les marques</option>
              @foreach($marques as $m)
                <option value="{{ $m }}" @selected(request('marque') === $m)>{{ $m }}</option>
              @endforeach
            </select>
          </div>
        @endif

        <label class="case">
          <input type="checkbox" name="stock" value="1" @checked(request('stock'))>
          <span>Masquer les ruptures de stock</span>
        </label>

        <div class="pile-sm">
          <button type="submit" class="btn btn-bloc">Appliquer</button>
          @if($filtresActifs->isNotEmpty())
            <a href="{{ url()->current() }}{{ request('q') ? '?q=' . urlencode(request('q')) : '' }}"
               class="btn btn-clair btn-sm btn-bloc">Tout effacer</a>
          @endif
        </div>
      </form>

      @if($categorie && $categorie->enfants->isNotEmpty())
        <hr style="margin-block:var(--s5)">
        <h3 style="font-size:var(--t-xs);margin-bottom:var(--s2)">
          Dans {{ $categorie->nom }}
        </h3>
        <div class="pile-sm">
          @foreach($categorie->enfants as $sous)
            <a href="{{ route('rayon', $sous) }}" class="rang-serre petit"
               style="color:var(--ink-2)">
              <span class="tronque-1">{{ $sous->nom }}</span>
            </a>
          @endforeach
        </div>
      @endif
    </div>
  </aside>

  <div class="pile">
    <div class="rang" style="justify-content:space-between">
      @if($filtresActifs->isNotEmpty())
        <div class="puces">
          @foreach($filtresActifs as $cle => $libelle)
            <span class="puce active">
              {{ $libelle }}@if($cle !== 'stock') : {{ request($cle) }}@endif
              <a href="{{ request()->fullUrlWithQuery([$cle => null]) }}"
                 aria-label="Retirer le filtre {{ $libelle }}"
                 style="display:flex;color:inherit">
                @include('partials.symbole', ['nom' => 'croix', 'taille' => 12])
              </a>
            </span>
          @endforeach
        </div>
      @else
        <span></span>
      @endif

      <form method="GET" class="rang-serre">
        @foreach(request()->except('tri', 'page') as $cle => $valeur)
          <input type="hidden" name="{{ $cle }}" value="{{ $valeur }}">
        @endforeach
        <label for="tri" class="petit secondaire">Trier par</label>
        <select id="tri" name="tri" onchange="this.form.submit()" style="width:auto">
          @foreach(App\Services\Catalogue::TRIS as $cle => $mot)
            <option value="{{ $cle }}" @selected(request('tri') === $cle)>{{ $mot }}</option>
          @endforeach
        </select>
        <noscript><button class="btn btn-sm btn-clair">Trier</button></noscript>
      </form>
    </div>

    @if($produits->isEmpty())
      <div class="bloc">
        @include('partials.vide', [
          'icone' => 'filtre',
          'titre' => 'Aucun produit ne correspond',
          'texte' => $filtresActifs->isNotEmpty()
            ? 'Vos filtres sont peut-être trop étroits. Élargissez la fourchette de prix, ou retirez-en un.'
            : 'Ce rayon ne contient encore aucun produit en vente.',
          'action' => $filtresActifs->isNotEmpty()
            ? '<a href="' . url()->current() . '" class="btn btn-clair">Effacer les filtres</a>'
              . '<a href="' . route('recherche') . '" class="btn">Tout le catalogue</a>'
            : '<a href="' . route('accueil') . '" class="btn">Retour à l\'accueil</a>',
        ])
      </div>
    @else
      <div class="grille g4">
        @foreach($produits as $p)
          @include('partials.carte', ['p' => $p])
        @endforeach
      </div>

      @if($produits->hasPages())
        <div style="margin-top:var(--s4)">{{ $produits->links() }}</div>
      @endif
    @endif
  </div>
</div>

@push('styles')
<style>
  /* La colonne de filtres n'apparaît qu'à partir de la largeur où elle ne prend
     pas la place des produits. En dessous, elle passe au-dessus de la liste. */
  @media (min-width: 900px) {
    .catalogue-grille { grid-template-columns: 250px minmax(0, 1fr) !important; }
    .catalogue-grille > #filtres { position: sticky; top: var(--s6); }
  }
</style>
@endpush

@endsection
