@extends('layouts.app')
@section('titre', 'Mes offres')
@section('contenu')

<div style="display:flex;align-items:baseline;gap:16px;flex-wrap:wrap;margin-bottom:6px">
  <h1 style="margin:0">Mes offres</h1>
  <a href="{{ route('vendeur.offre.nouvelle') }}" class="btn" style="margin-left:auto">
    Publier un article
  </a>
</div>
<p class="sous">
  Le stock entre par un arrivage, jamais par une saisie directe : chaque gramme
  présent doit pouvoir s'expliquer par un mouvement daté.
</p>

@if(! $vendeur->estVisible())
  <div class="avis avis-err" style="border-color:var(--ambre);background:var(--ambre-pale)">
    Votre établissement n'est pas encore vérifié : vos offres n'apparaissent chez
    aucun acheteur. Vous pouvez néanmoins les préparer dès maintenant.
  </div>
@endif

@forelse($offres as $o)
  <div class="carte" style="margin-bottom:14px;{{ $o->actif ? '' : 'opacity:.6' }}">
    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">

      <div class="vignette" style="width:74px;height:74px">
        @include('partials.dessin', [
          'dessin' => $o->article->caracteristiques['dessin'] ?? 'defaut', 'taille' => 52,
        ])
      </div>

      <div style="flex:1 1 220px;min-width:0">
        <strong style="display:block;line-height:1.3">{{ $o->article->designation }}</strong>
        <div style="color:var(--gris);font-size:.83rem;margin-top:2px">{{ $o->article->reference }}</div>
        <div style="margin-top:9px;display:flex;gap:6px;flex-wrap:wrap">
          <span class="etiq {{ $o->disponiblePivot() > 0 ? 'etiq-vert' : 'etiq-rouge' }}">
            @if($o->article->unite_pivot === 'gramme')
              {{ number_format($o->disponiblePivot() / 1000, 0, ',', ' ') }} kg disponibles
            @else
              {{ $o->disponiblePivot() }} disponibles
            @endif
          </span>
          @if($o->quantite_reservee_pivot > 0)
            <span class="etiq etiq-ambre">
              {{ $o->article->unite_pivot === 'gramme'
                  ? number_format($o->quantite_reservee_pivot / 1000, 0, ',', ' ') . ' kg'
                  : $o->quantite_reservee_pivot }} réservés
            </span>
          @endif
          @unless($o->actif)<span class="etiq etiq-gris">retirée de la vente</span>@endunless
        </div>
      </div>

      {{-- Le prix : modifiable à tout moment. Les commandes déjà passées l'ont
           figé, elles ne bougent pas. --}}
      <form method="POST" action="{{ route('vendeur.offre.modifier', $o) }}"
            style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        @csrf @method('PUT')
        <div style="flex:0 0 112px">
          <label style="font-size:.79rem;font-weight:600;display:block;margin-bottom:4px">
            Prix / {{ $o->unite_affichee }}
          </label>
          <input name="prix_par_unite" type="number" min="1" value="{{ $o->prix_par_unite }}"
                 style="width:100%;padding:8px 10px;border:1px solid var(--bord);border-radius:var(--r-sm)">
        </div>
        <div style="flex:0 0 78px">
          <label style="font-size:.79rem;font-weight:600;display:block;margin-bottom:4px">Prêt (h)</label>
          <input name="delai_preparation_h" type="number" min="0" max="168"
                 value="{{ $o->delai_preparation_h }}"
                 style="width:100%;padding:8px 10px;border:1px solid var(--bord);border-radius:var(--r-sm)">
        </div>
        <button class="btn btn-sm btn-clair">Enregistrer</button>
      </form>
    </div>

    <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;
                border-top:1px solid var(--bord);padding-top:14px">
      <form method="POST" action="{{ route('vendeur.stock', $o) }}"
            style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div style="flex:0 0 96px">
          <label style="font-size:.79rem;font-weight:600;display:block;margin-bottom:4px">Arrivage</label>
          <input name="quantite" value="1"
                 style="width:100%;padding:8px 10px;border:1px solid var(--bord);border-radius:var(--r-sm)">
        </div>
        <div style="flex:0 0 132px">
          <label style="font-size:.79rem;font-weight:600;display:block;margin-bottom:4px">Unité</label>
          <select name="unite"
                  style="width:100%;padding:8px 10px;border:1px solid var(--bord);border-radius:var(--r-sm)">
            @foreach($o->article->unitesVente as $u)
              <option value="{{ $u->unite }}" @selected($u->unite === $o->unite_affichee)>{{ $u->unite }}</option>
            @endforeach
          </select>
        </div>
        <button class="btn btn-sm">Entrer en stock</button>
      </form>

      <a href="{{ route('vendeur.journal', $o) }}" class="btn btn-sm btn-clair"
         style="margin-left:auto;align-self:flex-end">Journal de stock</a>

      <form method="POST" action="{{ route('vendeur.offre.bascule', $o) }}"
            style="align-self:flex-end">
        @csrf
        <button class="btn btn-sm btn-clair">
          {{ $o->actif ? 'Retirer de la vente' : 'Remettre en vente' }}
        </button>
      </form>
    </div>
  </div>
@empty
  <div class="carte vide">
    Vous ne proposez encore aucun article.<br>
    <a href="{{ route('vendeur.offre.nouvelle') }}">Publier le premier</a>
  </div>
@endforelse

<p style="margin-top:22px"><a href="{{ route('vendeur.tableau') }}">← Retour au tableau de bord</a></p>
@endsection
