@extends('layouts.app')
@section('titre', 'Ma vitrine')
@section('contenu')

<div style="max-width:600px">
  <h1>Ma vitrine</h1>
  <p style="color:var(--gris);margin-bottom:14px">
    Ce que les clients voient avant de vous acheter.
    @if($boutique->estVisible())
      <a href="{{ route('boutique', $boutique) }}" style="color:var(--bleu);font-weight:600">
        Voir ma page publique
      </a>
    @endif
  </p>

  <div class="carte" style="margin-bottom:14px;display:flex;gap:14px;flex-wrap:wrap;
                            align-items:center">
    <div>
      <div style="font-weight:700;font-size:1.05rem">{{ $boutique->nom }}</div>
      <div style="color:var(--gris);font-size:.85rem">
        {{-- Le nom ne se modifie pas ici : il sert d'adresse publique, et le
             changer casserait les liens déjà partagés. --}}
        Le nom de la boutique ne se change pas : il sert d'adresse publique.
      </div>
    </div>
    <div style="margin-left:auto;text-align:right">
      @if($boutique->nombre_avis)
        <div class="etoiles">{{ str_repeat('★', (int) round($boutique->noteSurCinq())) }}</div>
        <div style="color:var(--gris);font-size:.82rem">
          {{ $boutique->noteSurCinq() }} sur 5 · {{ $boutique->nombre_avis }} avis
        </div>
      @else
        <span class="etiq etiq-gris">Pas encore d'avis</span>
      @endif
    </div>
  </div>

  <form method="POST" action="{{ route('vendeur.boutique.maj') }}" class="carte">
    @csrf @method('PUT')

    <div class="champ"><label>Ce que vous vendez</label>
      <textarea name="description" rows="3">{{ old('description', $boutique->description) }}</textarea></div>

    <div class="champ"><label>Téléphone</label>
      <input name="telephone" value="{{ old('telephone', $boutique->telephone) }}" required>
      @error('telephone')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Adresse du magasin</label>
      <input name="adresse" value="{{ old('adresse', $boutique->adresse) }}" required>
      @error('adresse')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Ville</label>
      <input name="ville" value="{{ old('ville', $boutique->ville) }}" required>
      @error('ville')<div class="erreur">{{ $message }}</div>@enderror</div>

    <button class="btn">Enregistrer</button>
  </form>
</div>

@endsection
