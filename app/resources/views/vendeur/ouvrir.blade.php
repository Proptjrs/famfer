@extends('layouts.app')
@section('titre', 'Ouvrir ma boutique')
@section('contenu')

<div style="max-width:560px;margin:0 auto">
  <h1>Vendez sur FamFer</h1>
  <p style="color:var(--gris);margin-bottom:14px">
    Gratuit. Votre boutique est validée par notre équipe avant d'apparaître au
    catalogue — c'est ce qui protège les acheteurs, et votre réputation.
  </p>

  <form method="POST" class="carte">
    @csrf
    <div class="champ"><label>Nom de la boutique</label>
      <input name="nom" value="{{ old('nom') }}" required>
      @error('nom')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ">
      <label>Ce que vous vendez <span style="color:var(--gris)">(facultatif)</span></label>
      <textarea name="description" rows="3">{{ old('description') }}</textarea></div>

    <div class="champ"><label>Téléphone</label>
      <input name="telephone" value="{{ old('telephone', auth()->user()->telephone) }}" required>
      @error('telephone')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Adresse du magasin</label>
      <input name="adresse" value="{{ old('adresse') }}" required>
      @error('adresse')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Ville</label>
      <input name="ville" value="{{ old('ville') }}" required>
      @error('ville')<div class="erreur">{{ $message }}</div>@enderror</div>

    <button class="btn" style="width:100%">Ouvrir ma boutique</button>
  </form>
</div>

@endsection
