@extends('layouts.app')
@section('titre', 'Mes produits')
@section('contenu')

<div style="display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
  <h1 style="font-size:1.3rem">Mes produits</h1>
  <span style="color:var(--gris)">{{ $produits->total() }} au total</span>
  <a href="{{ route('vendeur.produit.nouveau') }}" class="btn btn-sm" style="margin-left:auto">
    Ajouter un produit
  </a>
</div>

@if($produits->isEmpty())
  <div class="carte vide">
    Aucun produit.<br>
    <a href="{{ route('vendeur.produit.nouveau') }}" class="btn" style="margin-top:14px">
      Publier le premier
    </a>
  </div>
@else
  <div class="carte large">
    <table>
      <tr>
        <th>Produit</th><th>Catégorie</th><th style="text-align:right">Prix</th>
        <th style="text-align:right">Stock</th><th>Ventes</th><th></th>
      </tr>
      @foreach($produits as $p)
        <tr>
          <td style="display:flex;gap:10px;align-items:center">
            {{-- La vignette dans la liste : c'est ce qui fait voir d'un coup
                 d'œil quels produits n'ont pas encore de photo. --}}
            <span style="flex:0 0 44px;height:44px;background:var(--fond);border-radius:var(--r);
                         display:flex;align-items:center;justify-content:center;overflow:hidden">
              @include('partials.image', ['p' => $p, 'taille' => 34])
            </span>
            <span>
            <a href="{{ route('vendeur.produit.editer', $p) }}" style="font-weight:600">{{ $p->nom }}</a>
            @unless($p->actif)<span class="etiq etiq-gris">retiré</span>@endunless
            @if($p->remise())<span class="etiq etiq-orange">−{{ $p->remise() }} %</span>@endif
            @if($p->photos->isEmpty())
              <br><span style="color:var(--orange-fonce);font-size:.78rem">sans photo</span>
            @endif
            </span>
          </td>
          <td style="color:var(--gris)">{{ $p->categorie->nom }}</td>
          <td class="mono" style="text-align:right">
            {{ number_format($p->prix, 0, ',', ' ') }} F
            @if($p->prix_barre)
              <br><span style="color:var(--gris);text-decoration:line-through;font-size:.8rem">
                {{ number_format($p->prix_barre, 0, ',', ' ') }} F
              </span>
            @endif
          </td>
          <td class="mono" style="text-align:right;{{ $p->stock === 0 ? 'color:var(--rouge)' : '' }}">
            {{ $p->stock }}
          </td>
          <td class="mono">{{ $p->nombre_ventes }}</td>
          <td style="text-align:right;white-space:nowrap">
            {{-- Retirer plutôt que supprimer : effacer le produit emporterait
                 les lignes de commande qui le désignent, donc l'historique. --}}
            <a href="{{ route('vendeur.produit.editer', $p) }}"
               class="btn btn-sm btn-clair">Modifier</a>
            <form method="POST" action="{{ route('vendeur.produit.bascule', $p) }}"
                  style="display:inline">
              @csrf
              <button class="btn btn-sm btn-clair">{{ $p->actif ? 'Retirer' : 'Remettre' }}</button>
            </form>
          </td>
        </tr>
      @endforeach
    </table>
  </div>
  <div style="margin-top:18px">{{ $produits->links() }}</div>
@endif

@endsection
