@extends('layouts.app')
@section('titre', 'Le fer et la quincaillerie, livrés partout au Sénégal')
@section('contenu')

{{-- Le bandeau d'accueil. Il annonce ce qui décide un achat sur une place de
     marché : le prix de la livraison, et le seuil au-dessus duquel elle est
     offerte. --}}
<div style="background:linear-gradient(100deg,#F68B1E,#D9740C);border-radius:var(--r);
            padding:26px 24px;margin-bottom:16px;color:#fff;display:flex;gap:22px;
            flex-wrap:wrap;align-items:center">
  <div style="flex:1 1 320px">
    <div style="font-size:.78rem;font-weight:800;letter-spacing:1px;opacity:.9;
                text-transform:uppercase;margin-bottom:8px">
      Place de marché nationale
    </div>
    <div style="font-size:1.9rem;font-weight:800;line-height:1.15;margin-bottom:10px">
      Le fer, les tôles et la quincaillerie<br>livrés chez vous.
    </div>
    <div style="opacity:.95">
      {{ App\Models\Produit::where('actif', true)->count() }} produits ·
      {{ App\Models\Boutique::where('statut', 'active')->count() }} boutiques ·
      paiement à la livraison
    </div>
  </div>
  <div style="background:rgba(255,255,255,.16);border-radius:var(--r);padding:16px 20px">
    <div style="font-weight:800;font-size:1.05rem">Livraison offerte</div>
    <div style="opacity:.95">dès 50 000 F d'achat</div>
    <div style="opacity:.85;font-size:.84rem;margin-top:6px">
      Sinon à partir de 1 500 F sur Dakar
    </div>
  </div>
</div>

{{-- Les rayons, en grand, pour ceux qui parcourent au lieu de chercher. --}}
<div class="bloc">
  <div class="bloc-tete"><h2>Nos rayons</h2></div>
  <div class="bloc-corps">
    <div class="grille g4">
      @foreach($rayons as $rayon)
        <a href="{{ route('rayon', $rayon) }}"
           style="display:flex;flex-direction:column;align-items:center;gap:8px;
                  padding:16px 10px;border-radius:var(--r);text-align:center;
                  border:1px solid var(--bord)">
          <span style="color:var(--orange);transform:scale(2.1);margin:8px 0 12px">
            @include('partials.icone', ['icone' => $rayon->icone])
          </span>
          <span style="font-weight:600;font-size:.88rem">{{ $rayon->nom }}</span>
          <span style="color:var(--gris);font-size:.78rem">{{ $rayon->produits_count }} produits</span>
        </a>
      @endforeach
    </div>
  </div>
</div>

@if($promotions->isNotEmpty())
  <div class="bloc">
    <div class="bloc-tete" style="background:var(--orange-pale)">
      <h2 style="color:var(--orange-fonce)">Promotions</h2>
      <span style="color:var(--gris-fonce);font-size:.86rem">
        Les plus fortes remises du moment
      </span>
      <a href="{{ route('recherche', ['tri' => 'remise']) }}" class="btn btn-sm">Tout voir</a>
    </div>
    <div class="bloc-corps">
      <div class="grille g4">
        @foreach($promotions as $p)
          @include('partials.carte', ['p' => $p])
        @endforeach
      </div>
    </div>
  </div>
@endif

@if($populaires->isNotEmpty())
  <div class="bloc">
    <div class="bloc-tete">
      <h2>Les plus vendus</h2>
      <a href="{{ route('recherche', ['tri' => 'ventes']) }}" class="btn btn-sm btn-clair">Tout voir</a>
    </div>
    <div class="bloc-corps">
      <div class="grille g4">
        @foreach($populaires as $p)
          @include('partials.carte', ['p' => $p])
        @endforeach
      </div>
    </div>
  </div>
@endif

@endsection
