@extends('layouts.app')
@section('titre', $titre)
@section('contenu')

<div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">

  {{-- Les filtres, en colonne. Sur une place de marché, ils tiennent la
       gauche : on affine sans perdre la liste de vue. --}}
  <aside class="carte" style="flex:0 0 232px;position:sticky;top:150px">
    <form method="GET">
      @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif

      <h3 style="margin-bottom:12px">Affiner</h3>

      <div class="champ"><label>Prix (F)</label>
        <div style="display:flex;gap:6px">
          <input name="min" value="{{ request('min') }}" placeholder="min" inputmode="numeric">
          <input name="max" value="{{ request('max') }}" placeholder="max" inputmode="numeric">
        </div>
      </div>

      @if($marques)
        <div class="champ"><label>Marque</label>
          <select name="marque">
            <option value="">Toutes</option>
            @foreach($marques as $m)
              <option value="{{ $m }}" @selected(request('marque') === $m)>{{ $m }}</option>
            @endforeach
          </select>
        </div>
      @endif

      <label style="display:flex;gap:8px;align-items:center;font-weight:500;margin-bottom:14px">
        <input type="checkbox" name="stock" value="1" @checked(request('stock'))
               style="width:auto"> En stock seulement
      </label>

      <button class="btn" style="width:100%">Appliquer</button>
      @if(request()->hasAny(['min','max','marque','stock']))
        <a href="{{ url()->current() }}{{ request('q') ? '?q='.urlencode(request('q')) : '' }}"
           class="btn btn-clair btn-sm" style="width:100%;margin-top:8px">Tout effacer</a>
      @endif
    </form>

    @if($categorie && $categorie->enfants->isNotEmpty())
      <hr style="border:0;border-top:1px solid var(--bord);margin:16px 0">
      <h3 style="margin-bottom:8px">Dans {{ $categorie->nom }}</h3>
      @foreach($categorie->enfants as $sous)
        <a href="{{ route('rayon', $sous) }}"
           style="display:block;padding:5px 0;font-size:.88rem">{{ $sous->nom }}</a>
      @endforeach
    @endif
  </aside>

  <div style="flex:1 1 460px;min-width:0">
    <div class="bloc-tete" style="background:var(--blanc);border-radius:var(--r);
                                  box-shadow:var(--ombre);margin-bottom:14px">
      <div>
        <h1 style="font-size:1.25rem">{{ $titre }}</h1>
        <span style="color:var(--gris);font-size:.86rem">
          {{ $produits->total() }} produit{{ $produits->total() > 1 ? 's' : '' }}
        </span>
      </div>
      <form method="GET" style="margin-left:auto">
        @foreach(request()->except('tri', 'page') as $cle => $valeur)
          <input type="hidden" name="{{ $cle }}" value="{{ $valeur }}">
        @endforeach
        <select name="tri" onchange="this.form.submit()" style="width:auto">
          @foreach(App\Services\Catalogue::TRIS as $cle => $mot)
            <option value="{{ $cle }}" @selected(request('tri') === $cle)>{{ $mot }}</option>
          @endforeach
        </select>
      </form>
    </div>

    @if($produits->isEmpty())
      <div class="carte vide">
        Aucun produit ne correspond.<br>
        Essayez d'élargir le prix, ou de retirer un filtre.
      </div>
    @else
      <div class="grille g4">
        @foreach($produits as $p)
          @include('partials.carte', ['p' => $p])
        @endforeach
      </div>
      <div style="margin-top:20px">{{ $produits->links() }}</div>
    @endif
  </div>
</div>

@endsection
