@extends('layouts.app')
@section('titre', 'Le fer, au juste prix')
@section('contenu')

@if(! $filtre)
  {{-- La bannière ne se montre qu'à l'arrivée : une fois qu'on cherche, elle
       vole la place des résultats. --}}
  <section style="background:linear-gradient(135deg,var(--nuit) 0%,var(--acier-2) 100%);
                  border-radius:var(--r);padding:52px 40px;margin-bottom:34px;
                  color:#fff;position:relative;overflow:hidden">
    <div style="position:absolute;right:-70px;top:-60px;width:340px;height:340px;
                background:radial-gradient(circle,rgba(253,126,20,.22),transparent 68%)"></div>
    <div style="position:relative;max-width:660px">
      <span class="etiq etiq-forge" style="margin-bottom:16px">Place de marché nationale</span>
      <h1 style="font-size:3rem;margin:14px 0 16px;color:#fff">
        Le même fer,<br>chez plusieurs quincailleries.
      </h1>
      <p style="color:#C3CCD5;font-size:1.08rem;margin-bottom:26px;max-width:56ch">
        L'un vend à la barre, l'autre au kilo, le troisième à la tonne.
        FamFer ramène tout au même poids et vous montre qui est le moins cher —
        avec la distance et le stock réel.
      </p>
      <form method="GET" action="{{ route('accueil') }}"
            style="display:flex;gap:10px;flex-wrap:wrap;max-width:560px">
        <input name="q" value="{{ $termes }}" autofocus
               placeholder="fer 10, tôle bac, cornière 40, électrode…"
               style="flex:1 1 260px;min-width:0;padding:14px 18px;border:0;
                      border-radius:var(--r-sm);font-size:1rem">
        <button class="btn" style="padding:14px 26px">Rechercher</button>
      </form>
    </div>
  </section>
@else
  {{-- La barre de recherche et les filtres ne font qu'un formulaire : filtrer
       sans reperdre les mots tapés est le minimum attendu. --}}
  <form method="GET" action="{{ route('accueil') }}" class="filtres"
        style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:26px">
    <div style="flex:1 1 240px;min-width:0">
      <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:4px">Recherche</label>
      <input name="q" value="{{ $termes }}" placeholder="fer 10, tôle bac…"
             style="width:100%;padding:11px 14px;border:1px solid var(--bord);
                    border-radius:var(--r-sm)">
    </div>
    <div style="flex:0 1 200px">
      <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:4px">Famille</label>
      <select name="famille" style="width:100%;padding:11px 12px;border:1px solid var(--bord);
                                    border-radius:var(--r-sm)">
        <option value="">Toutes</option>
        @foreach($familles as $f)
          <option value="{{ $f->id }}" @selected($famille === $f->id)>{{ $f->nom }}</option>
        @endforeach
      </select>
    </div>
    <div style="flex:0 1 170px">
      <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:4px">Prix maximum</label>
      <input name="prix_max" value="{{ $prixMax }}" inputmode="numeric" placeholder="F par unité"
             style="width:100%;padding:11px 14px;border:1px solid var(--bord);
                    border-radius:var(--r-sm)">
    </div>
    <button class="btn" style="padding:11px 22px">Filtrer</button>
    @if($famille || $prixMax)
      <a href="{{ route('accueil', ['q' => $termes]) }}" class="btn btn-clair"
         style="padding:11px 18px">Tout effacer</a>
    @endif
  </form>
@endif

@if($filtre)
  <h2>
    {{ $articles->total() }} article{{ $articles->total() > 1 ? 's' : '' }}
    @if($termes !== '') pour « {{ $termes }} » @endif
  </h2>

  <div class="grille g3">
    @forelse($articles as $a)
      @include('partials.produit', ['a' => $a])
    @empty
      <div class="carte vide" style="grid-column:1/-1">
        Rien ne correspond à cette recherche.<br>
        Essayez le vocabulaire du chantier : « fer 10 », « tôle bac », « corniere 40 »,
        ou élargissez le prix maximum.
      </div>
    @endforelse
  </div>

  <div style="margin-top:24px">{{ $articles->links() }}</div>
@else
  @foreach($familles as $f)
    @php $articlesFamille = $f->articles()->where('actif', true)->orderBy('id')->take(4)->get(); @endphp
    @continue($articlesFamille->isEmpty())

    <div style="display:flex;align-items:baseline;gap:14px;margin:34px 0 16px">
      <h2 style="margin:0">{{ $f->nom }}</h2>
      <span style="color:var(--gris);font-size:.9rem">{{ $f->articles()->count() }} références</span>
      <a href="{{ route('accueil', ['q' => $f->nom]) }}" style="margin-left:auto;font-weight:600">
        Tout voir →
      </a>
    </div>

    <div class="grille g4">
      @foreach($articlesFamille as $a)
        @include('partials.produit', ['a' => $a, 'compact' => true])
      @endforeach
    </div>
  @endforeach
@endif

@endsection
