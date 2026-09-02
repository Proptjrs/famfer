@extends('layouts.app')
@section('titre', $article->designation)
@section('contenu')

<h1>{{ $article->designation }}</h1>
<p class="sous">
  {{ $article->reference }}
  @if($m = $article->caracteristiques['masse_lineique_g_m'] ?? null)
    · {{ number_format($m / 1000, 3, ',', ' ') }} kg par mètre
  @endif
</p>

<div style="margin-bottom:18px;display:flex;gap:8px;flex-wrap:wrap">
  @foreach(['prix' => 'Le moins cher', 'distance' => 'Le plus proche', 'note' => 'Le mieux noté'] as $cle => $mot)
    <a href="{{ route('article', ['article' => $article, 'tri' => $cle, 'lat' => $lat, 'lng' => $lng]) }}"
       class="btn btn-sm {{ $tri === $cle ? '' : 'btn-clair' }}">{{ $mot }}</a>
  @endforeach
</div>

@forelse($offres as $o)
  <div class="carte" style="margin-bottom:12px">
    <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start">
      <div style="flex:1 1 240px;min-width:0">
        <strong>{{ $o->vendeur->raison_sociale }}</strong>
        <div style="color:var(--gris);font-size:.88rem">
          {{ $o->vendeur->commune }}
          @isset($o->distance_km) · {{ $o->distance_km }} km · {{ $o->duree_min }} min en voiture @endisset
        </div>
        <div style="margin-top:5px">
          @include('partials.note', ['vendeur' => $o->vendeur, 'lien' => true])
        </div>
        <div style="margin-top:8px">
          <span class="etiq etiq-vert">
            {{ number_format($o->disponiblePivot() / 1000, 0, ',', ' ') }} kg en stock
          </span>
          <span class="etiq etiq-gris">prêt en {{ $o->delai_preparation_h }} h</span>
        </div>
      </div>

      <div style="text-align:right">
        <div class="chiffre mono">{{ number_format($o->prix_par_unite, 0, ',', ' ') }} F</div>
        <div class="chiffre-note">
          {{ \App\Support\Unites::avecDeterminant($o->unite_affichee) }} ·
          {{ number_format($o->prixParPivot() * 1000, 0, ',', ' ') }} F le kg
        </div>
      </div>
    </div>

    @auth
      <form method="POST" action="{{ route('panier.ajouter', $o) }}"
            style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;align-items:flex-end">
        @csrf
        <div style="flex:0 0 110px"><label style="font-size:.82rem;font-weight:600">Quantité</label>
          <input name="quantite" value="1" style="width:100%;padding:8px 10px;border:1px solid var(--bord);border-radius:8px"></div>
        <div style="flex:0 0 150px"><label style="font-size:.82rem;font-weight:600">Unité</label>
          <select name="unite" style="width:100%;padding:8px 10px;border:1px solid var(--bord);border-radius:8px">
            @foreach($article->unitesVente as $u)
              <option value="{{ $u->unite }}" @selected($u->unite === $o->unite_affichee)>{{ $u->unite }}</option>
            @endforeach
          </select></div>
        <button class="btn">Ajouter au panier</button>
      </form>
    @else
      <div style="margin-top:14px;color:var(--gris);font-size:.9rem">
        <a href="{{ route('connexion') }}">Connectez-vous</a> pour commander.
      </div>
    @endauth
  </div>
@empty
  <div class="carte vide">Aucun vendeur vérifié ne propose cet article en stock aujourd'hui.</div>
@endforelse

@endsection
