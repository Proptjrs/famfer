@extends('layouts.app')
@section('titre', 'Publier un article')
@section('contenu')

<h1>Publier un article</h1>
<p class="sous">
  Choisissez l'article dans le catalogue national, puis annoncez votre prix et
  votre stock. C'est parce que tout le monde part du même catalogue que
  l'acheteur peut vous comparer — et vous trouver.
</p>

@php $vides = true; @endphp

@foreach($familles as $f)
  @continue($f->articles->isEmpty())
  @php $vides = false; @endphp

  <h2 style="margin-top:26px">{{ $f->nom }}</h2>

  <div class="grille g2">
    @foreach($f->articles as $a)
      @php $unite = $a->uniteParDefaut(); @endphp
      <form method="POST" action="{{ route('vendeur.offre.publier') }}" class="carte"
            style="display:flex;gap:16px;align-items:flex-start">
        @csrf
        <input type="hidden" name="article_id" value="{{ $a->id }}">

        <div class="vignette" style="width:78px;height:78px">
          @include('partials.dessin', [
            'dessin' => $a->caracteristiques['dessin'] ?? 'defaut', 'taille' => 56,
          ])
        </div>

        <div style="flex:1;min-width:0">
          <strong style="display:block;line-height:1.3">{{ $a->designation }}</strong>
          <div style="color:var(--gris);font-size:.82rem;margin-bottom:12px">{{ $a->reference }}</div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div>
              <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:4px">
                Votre prix (F)
              </label>
              <input name="prix_par_unite" type="number" min="1" required
                     style="width:100%;padding:8px 10px;border:1px solid var(--bord);border-radius:var(--r-sm)">
            </div>
            <div>
              <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:4px">
                Par
              </label>
              <select name="unite_affichee"
                      style="width:100%;padding:8px 10px;border:1px solid var(--bord);border-radius:var(--r-sm)">
                @foreach($a->unitesVente as $u)
                  <option value="{{ $u->unite }}" @selected($u->par_defaut)>{{ $u->unite }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:4px">
                Stock de départ
              </label>
              <input name="quantite" value="10" required
                     style="width:100%;padding:8px 10px;border:1px solid var(--bord);border-radius:var(--r-sm)">
            </div>
            <div>
              <label style="font-size:.8rem;font-weight:600;display:block;margin-bottom:4px">
                Prêt en (heures)
              </label>
              <input name="delai_preparation_h" type="number" value="2" min="0" max="168" required
                     style="width:100%;padding:8px 10px;border:1px solid var(--bord);border-radius:var(--r-sm)">
            </div>
          </div>

          <button class="btn btn-sm" style="margin-top:12px">Publier</button>
        </div>
      </form>
    @endforeach
  </div>
@endforeach

@if($vides)
  <div class="carte vide">
    Vous proposez déjà tous les articles du catalogue.
  </div>
@endif

<p style="margin-top:26px"><a href="{{ route('vendeur.offres') }}">← Retour à mes offres</a></p>
@endsection
