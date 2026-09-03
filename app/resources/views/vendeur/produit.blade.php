@extends('layouts.app')
@section('titre', 'Ajouter un produit')
@section('contenu')

<div style="max-width:620px;margin:0 auto">
  <h1>Ajouter un produit</h1>

  <form method="POST" action="{{ route('vendeur.produit.publier') }}" class="carte"
        style="margin-top:14px">
    @csrf

    <div class="champ"><label>Nom du produit</label>
      <input name="nom" value="{{ old('nom') }}" required
             placeholder="Fer à béton HA T10 — barre de 12 m">
      @error('nom')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Catégorie</label>
      <select name="categorie_id" required>
        @foreach($categories as $c)
          <option value="{{ $c->id }}" @selected(old('categorie_id') == $c->id)>
            {{ $c->parente->nom }} › {{ $c->nom }}
          </option>
        @endforeach
      </select>
      @error('categorie_id')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="grille g2">
      <div class="champ"><label>Prix de vente (F)</label>
        <input name="prix" value="{{ old('prix') }}" required inputmode="numeric">
        @error('prix')<div class="erreur">{{ $message }}</div>@enderror</div>
      <div class="champ">
        <label>Prix barré <span style="color:var(--gris)">(facultatif)</span></label>
        <input name="prix_barre" value="{{ old('prix_barre') }}" inputmode="numeric">
        @error('prix_barre')<div class="erreur">{{ $message }}</div>@enderror</div>
    </div>

    <p style="color:var(--gris);font-size:.83rem;margin:-6px 0 14px">
      La remise affichée se calcule des deux prix, elle ne se saisit pas. Un prix
      barré inférieur au prix de vente est refusé : une fausse remise se voit.
    </p>

    <div class="grille g2">
      <div class="champ"><label>Stock</label>
        <input name="stock" value="{{ old('stock', 0) }}" required inputmode="numeric">
        @error('stock')<div class="erreur">{{ $message }}</div>@enderror</div>
      <div class="champ">
        <label>Marque <span style="color:var(--gris)">(facultatif)</span></label>
        <input name="marque" value="{{ old('marque') }}"></div>
    </div>

    <div class="champ"><label>Description</label>
      <textarea name="description" rows="3">{{ old('description') }}</textarea></div>

    <div class="champ"><label>Illustration</label>
      <select name="dessin">
        @foreach(['rond-strie' => 'Fer à béton strié', 'rond-lisse' => 'Rond lisse',
                  'tole-bac' => 'Tôle bac', 'tole-ondulee' => 'Tôle ondulée',
                  'tole-plane' => 'Tôle plane', 'corniere' => 'Cornière',
                  'tube-carre' => 'Tube carré', 'tube-rond' => 'Tube rond',
                  'fer-plat' => 'Fer plat', 'treillis' => 'Treillis', 'fil' => 'Fil',
                  'vis' => 'Visserie', 'boulon' => 'Boulon', 'marteau' => 'Outil',
                  'electrode' => 'Soudure', 'roulement' => 'Roulement',
                  'defaut' => 'Autre'] as $cle => $mot)
          <option value="{{ $cle }}" @selected(old('dessin') === $cle)>{{ $mot }}</option>
        @endforeach
      </select></div>

    <button class="btn" style="width:100%">Publier</button>
  </form>
</div>

@endsection
