@extends('layouts.app')
@section('titre', 'Ma boutique')
@section('contenu')

<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
  <h1 style="font-size:1.35rem">{{ $boutique->nom }}</h1>
  @if($boutique->statut === 'en_attente')
    <span class="etiq etiq-orange">En attente de validation</span>
  @elseif($boutique->statut === 'suspendue')
    <span class="etiq etiq-rouge">Suspendue</span>
  @else
    <span class="etiq etiq-vert">Active</span>
  @endif
  @if($boutique->officielle)<span class="etiq etiq-officielle">Officielle</span>@endif
  <a href="{{ route('vendeur.produit.nouveau') }}" class="btn btn-sm" style="margin-left:auto">
    Ajouter un produit
  </a>
</div>

@if($boutique->statut === 'en_attente')
  <div class="avis" style="background:var(--orange-pale);color:var(--orange-fonce)">
    Votre boutique attend la validation de l'administration. Vous pouvez déjà
    préparer vos produits : ils apparaîtront au catalogue dès la validation,
    sans que vous ayez à les republier.
  </div>
@elseif($boutique->statut === 'suspendue')
  <div class="avis avis-err">Boutique suspendue. {{ $boutique->motif_suspension }}</div>
@endif

<div class="grille g4" style="margin-bottom:16px">
  <div class="carte">
    <div class="mono" style="font-size:1.5rem;font-weight:800">
      {{ number_format($chiffres['chiffre_affaires'], 0, ',', ' ') }} F
    </div>
    <div style="color:var(--gris);font-size:.84rem">Ventes livrées</div>
  </div>
  <div class="carte">
    <div class="mono" style="font-size:1.5rem;font-weight:800">{{ $chiffres['articles_vendus'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">Articles vendus</div>
  </div>
  <div class="carte" style="{{ $chiffres['a_expedier'] ? 'border:1px solid var(--orange)' : '' }}">
    <div style="font-size:1.5rem;font-weight:800">{{ $chiffres['a_expedier'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">À expédier</div>
  </div>
  <div class="carte">
    <div style="font-size:1.5rem;font-weight:800">{{ $chiffres['en_route'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">En route</div>
  </div>
  <div class="carte">
    <div style="font-size:1.5rem;font-weight:800">{{ $chiffres['produits'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">Produits</div>
  </div>
  <div class="carte" style="{{ $chiffres['en_rupture'] ? 'border:1px solid var(--rouge)' : '' }}">
    <div style="font-size:1.5rem;font-weight:800">{{ $chiffres['en_rupture'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">En rupture</div>
  </div>
  <div class="carte">
    <div style="font-size:1.5rem;font-weight:800">{{ $chiffres['refusees'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">
      Refusées à la porte
      {{-- Le paiement à la livraison a ce coût-là, et il faut le voir : chaque
           refus est une tournée payée pour rien. --}}
      @if($chiffres['refusees'])
        <br><span style="color:var(--rouge)">chaque refus coûte une tournée</span>
      @endif
    </div>
  </div>
</div>

<div class="bloc">
  <div class="bloc-tete">
    <h2>Commandes à traiter</h2>
    <a href="{{ route('vendeur.commandes') }}" class="btn btn-sm btn-clair">Toutes mes ventes</a>
  </div>
  <div class="bloc-corps">
    @forelse($aTraiter as $c)
      <div style="display:flex;gap:12px;align-items:center;padding:10px 0;flex-wrap:wrap;
                  border-bottom:1px solid var(--bord)">
        <strong>{{ $c->reference }}</strong>
        @include('partials.etat', ['etat' => $c->etat])
        <span style="color:var(--gris);font-size:.85rem">
          {{ $c->lignes->where('boutique_id', $boutique->id)->count() }} de vos articles
        </span>
        <span class="mono" style="margin-left:auto;font-weight:700">
          {{ number_format($c->lignes->where('boutique_id', $boutique->id)->sum('montant'), 0, ',', ' ') }} F
        </span>
        @if($c->etat === 'en_preparation')
          <form method="POST" action="{{ route('vendeur.expedier', $c) }}">
            @csrf <button class="btn btn-sm">Expédier</button>
          </form>
        @elseif(in_array($c->etat, ['expediee', 'en_livraison']))
          <form method="POST" action="{{ route('vendeur.livrer', $c) }}">
            @csrf <button class="btn btn-sm btn-vert">Marquer livrée</button>
          </form>
        @endif
      </div>
    @empty
      <div class="vide">Aucune commande en attente.</div>
    @endforelse
  </div>
</div>

@endsection
