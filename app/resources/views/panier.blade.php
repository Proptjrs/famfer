@extends('layouts.app')
@section('titre', 'Mon panier')
@section('contenu')

<h1>Mon panier</h1>

@if($contenu->isEmpty())
  <div class="carte vide">
    Votre panier est vide.<br>
    <a href="{{ route('accueil') }}" class="btn" style="margin-top:14px">Voir le catalogue</a>
  </div>
@else
  <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">

    <div style="flex:1 1 420px;min-width:0">
      @foreach($contenu as $ligne)
        @php $p = $ligne['produit']; @endphp
        <div class="carte" style="display:flex;gap:14px;margin-bottom:12px;flex-wrap:wrap">
          <div style="flex:0 0 88px;height:88px;background:var(--fond);border-radius:var(--r);
                      display:flex;align-items:center;justify-content:center">
            @include('partials.dessin', ['dessin' => $p->dessin, 'taille' => 66])
          </div>

          <div style="flex:1 1 200px;min-width:0">
            <a href="{{ route('produit', $p) }}" style="font-weight:600">{{ $p->nom }}</a>
            <div style="color:var(--gris);font-size:.84rem;margin-top:3px">
              Vendu par {{ $p->boutique->nom }}
            </div>
            @if($ligne['ajuste'])
              {{-- Dit plutôt que tu : le client doit corriger lui-même, sinon
                   il recevrait moins que ce qu'il croit avoir commandé. --}}
              <div style="color:var(--rouge);font-size:.83rem;margin-top:4px">
                Il ne reste que {{ $ligne['disponible'] }} en stock.
                Réduisez la quantité pour continuer.
              </div>
            @endif
            <div style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap">
              <form method="POST" action="{{ route('panier.modifier', $p) }}"
                    style="display:flex;gap:6px;align-items:center">
                @csrf @method('PUT')
                <input name="quantite" type="number" value="{{ $ligne['quantite'] }}"
                       min="1" max="{{ $p->stock }}" style="width:74px;padding:6px 8px"
                       onchange="this.form.submit()">
              </form>
              <form method="POST" action="{{ route('panier.retirer', $p) }}">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-clair">Retirer</button>
              </form>
            </div>
          </div>

          <div style="text-align:right;font-weight:700;font-size:1.05rem">
            {{ number_format($ligne['montant'], 0, ',', ' ') }} F
            <div style="color:var(--gris);font-weight:400;font-size:.82rem">
              {{ number_format($p->prix, 0, ',', ' ') }} F l'unité
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <div class="carte" style="flex:0 0 296px;position:sticky;top:150px">
      <h2 style="margin-bottom:12px">Récapitulatif</h2>

      <div style="display:flex;justify-content:space-between;margin-bottom:8px">
        <span>Sous-total</span>
        <strong class="mono">{{ number_format($sousTotal, 0, ',', ' ') }} F</strong>
      </div>

      @if($resteAvantGratuite === null)
        <div style="background:var(--vert-pale);color:var(--vert);padding:10px;
                    border-radius:var(--r);margin:10px 0;font-weight:600;font-size:.86rem">
          Livraison offerte
        </div>
      @else
        {{-- Le seuil affiché n'est pas un artifice : il pousse à remplir le
             panier, et le client peut décider en connaissance de cause. --}}
        <div style="background:var(--orange-pale);color:var(--orange-fonce);padding:10px;
                    border-radius:var(--r);margin:10px 0;font-size:.86rem">
          Plus que <strong>{{ number_format($resteAvantGratuite, 0, ',', ' ') }} F</strong>
          pour la livraison offerte.
        </div>
      @endif

      <div style="color:var(--gris);font-size:.84rem;margin-bottom:14px">
        Les frais de livraison dépendent de votre région ; ils s'affichent à
        l'étape suivante.
      </div>

      @if($contenu->contains('ajuste', true))
        <button class="btn" disabled style="width:100%">Commander</button>
        <p style="color:var(--rouge);font-size:.83rem;margin-top:8px;text-align:center">
          Ajustez d'abord les quantités signalées en rouge.
        </p>
      @else
        <a href="{{ route('commande') }}" class="btn" style="width:100%">Commander</a>
      @endif
      <a href="{{ route('accueil') }}" class="btn btn-clair btn-sm"
         style="width:100%;margin-top:8px">Continuer mes achats</a>
    </div>
  </div>
@endif

@endsection
