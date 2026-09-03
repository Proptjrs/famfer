@extends('layouts.app')
@section('titre', $produit ? $produit->nom : 'Ajouter un produit')
@section('contenu')

<div style="max-width:660px;margin:0 auto">
  <h1>{{ $produit ? 'Modifier le produit' : 'Ajouter un produit' }}</h1>

  {{-- Les photos d'abord quand le produit existe : c'est ce qui manque le plus
       souvent, et le laisser en bas du formulaire revient à ne jamais y venir. --}}
  @if($produit)
    <div class="carte" style="margin:14px 0">
      <h2 style="margin-bottom:4px">Photos</h2>
      <p style="color:var(--gris);font-size:.86rem;margin-bottom:12px">
        La première photo sert de vignette au catalogue. Sans photo, le produit
        s'affiche avec un dessin — les clients achètent nettement moins.
      </p>

      @if($produit->photos->isNotEmpty())
        <div class="grille g4" style="margin-bottom:14px">
          @foreach($produit->photos as $photo)
            <div style="position:relative">
              <div style="aspect-ratio:1;background:var(--fond);border-radius:var(--r);
                          overflow:hidden;border:{{ $loop->first ? '2px solid var(--orange)' : '1px solid var(--bord)' }}">
                <img src="{{ $photo->url() }}" alt="{{ $produit->nom }}"
                     style="width:100%;height:100%;object-fit:contain">
              </div>
              @if($loop->first)
                <span class="etiq etiq-orange"
                      style="position:absolute;top:6px;left:6px">Vignette</span>
              @endif
              <form method="POST" action="{{ route('vendeur.photo.supprimer', $photo) }}"
                    style="position:absolute;top:6px;right:6px">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-rouge" style="padding:3px 8px"
                        title="Supprimer">×</button>
              </form>
            </div>
          @endforeach
        </div>
      @endif

      @if($produit->photos->count() < 8)
        <form method="POST" action="{{ route('vendeur.produit.photos', $produit) }}"
              enctype="multipart/form-data">
          @csrf
          <div class="champ">
            <label>Ajouter des photos</label>
            <input type="file" name="photos[]" multiple required
                   accept="image/jpeg,image/png,image/webp">
            @error('photos')<div class="erreur">{{ $message }}</div>@enderror
            <p style="color:var(--gris);font-size:.83rem;margin-top:6px">
              JPEG, PNG ou WebP · 3 Mo maximum par image · au moins 200 × 200
              pixels · {{ 8 - $produit->photos->count() }} emplacement(s) restant(s).
            </p>
          </div>
          <button class="btn">Téléverser</button>
        </form>
      @else
        <p style="color:var(--gris)">
          Huit photos, c'est le maximum. Supprimez-en une pour en ajouter une autre.
        </p>
      @endif
    </div>
  @endif

  <form method="POST"
        action="{{ $produit ? route('vendeur.produit.modifier', $produit) : route('vendeur.produit.publier') }}"
        class="carte">
    @csrf
    @if($produit) @method('PUT') @endif

    <div class="champ"><label>Nom du produit</label>
      <input name="nom" value="{{ old('nom', $produit?->nom) }}" required
             placeholder="Fer à béton HA T10 — barre de 12 m">
      @error('nom')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Catégorie</label>
      <select name="categorie_id" required>
        @foreach($categories as $c)
          <option value="{{ $c->id }}"
                  @selected(old('categorie_id', $produit?->categorie_id) == $c->id)>
            {{ $c->parente->nom }} › {{ $c->nom }}
          </option>
        @endforeach
      </select>
      @error('categorie_id')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="grille g2">
      <div class="champ"><label>Prix de vente (F)</label>
        <input name="prix" value="{{ old('prix', $produit?->prix) }}" required inputmode="numeric">
        @error('prix')<div class="erreur">{{ $message }}</div>@enderror</div>
      <div class="champ">
        <label>Prix barré <span style="color:var(--gris)">(facultatif)</span></label>
        <input name="prix_barre" value="{{ old('prix_barre', $produit?->prix_barre) }}"
               inputmode="numeric">
        @error('prix_barre')<div class="erreur">{{ $message }}</div>@enderror</div>
    </div>

    <p style="color:var(--gris);font-size:.83rem;margin:-6px 0 14px">
      La remise affichée se calcule des deux prix, elle ne se saisit pas. Un prix
      barré inférieur au prix de vente est refusé : une fausse remise se voit.
    </p>

    <div class="grille g2">
      <div class="champ"><label>Stock</label>
        <input name="stock" value="{{ old('stock', $produit?->stock ?? 0) }}" required
               inputmode="numeric">
        @error('stock')<div class="erreur">{{ $message }}</div>@enderror</div>
      <div class="champ">
        <label>Marque <span style="color:var(--gris)">(facultatif)</span></label>
        <input name="marque" value="{{ old('marque', $produit?->marque) }}"></div>
    </div>

    <div class="champ"><label>Description</label>
      <textarea name="description" rows="3">{{ old('description', $produit?->description) }}</textarea></div>

    <div class="champ">
      <label>Dessin de repli <span style="color:var(--gris)">(tant qu'il n'y a pas de photo)</span></label>
      <select name="dessin">
        @foreach(['rond-strie' => 'Fer à béton strié', 'rond-lisse' => 'Rond lisse',
                  'tole-bac' => 'Tôle bac', 'tole-ondulee' => 'Tôle ondulée',
                  'tole-plane' => 'Tôle plane', 'corniere' => 'Cornière',
                  'tube-carre' => 'Tube carré', 'tube-rond' => 'Tube rond',
                  'fer-plat' => 'Fer plat', 'treillis' => 'Treillis', 'fil' => 'Fil',
                  'vis' => 'Visserie', 'boulon' => 'Boulon', 'marteau' => 'Outil',
                  'electrode' => 'Soudure', 'roulement' => 'Roulement',
                  'defaut' => 'Autre'] as $cle => $mot)
          <option value="{{ $cle }}"
                  @selected(old('dessin', $produit?->dessin) === $cle)>{{ $mot }}</option>
        @endforeach
      </select></div>

    <button class="btn" style="width:100%">
      {{ $produit ? 'Enregistrer' : 'Publier' }}
    </button>
  </form>

  <p style="margin-top:14px">
    <a href="{{ route('vendeur.produits') }}">← Retour à mes produits</a>
  </p>
</div>

@endsection
