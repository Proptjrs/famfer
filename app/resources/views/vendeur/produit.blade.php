@extends('layouts.app')
@section('titre', $produit ? $produit->nom : 'Ajouter un produit')
@section('contenu')

<div class="page-moyenne" style="width:min(46rem,100%)">

  @include('partials.entete', [
    'titre' => $produit ? 'Modifier le produit' : 'Ajouter un produit',
    'sous' => $produit
      ? 'Le prix modifié ne réécrit pas les commandes déjà passées : elles portent le leur, figé.'
      : 'Il apparaîtra au catalogue dès la publication, si votre boutique est active.',
    'fil' => [
      ['libelle' => 'Ma boutique', 'url' => route('vendeur.tableau')],
      ['libelle' => 'Mes produits', 'url' => route('vendeur.produits')],
      ['libelle' => $produit ? $produit->nom : 'Nouveau'],
    ],
  ])

  {{-- Les photos d'abord quand le produit existe : c'est ce qui manque le plus
       souvent, et le laisser en bas du formulaire revient à ne jamais y venir. --}}
  @if($produit)
    <div class="bloc" style="margin-bottom:var(--s5)">
      <div class="bloc-tete">
        <h2>Photos</h2>
        <span class="sous">{{ $produit->photos->count() }} sur 8</span>
      </div>
      <div class="bloc-corps pile">
        <p class="secondaire">
          La première photo sert de vignette au catalogue. Sans photo, le produit
          s'affiche avec un dessin — et les clients achètent nettement moins.
        </p>

        @if($produit->photos->isNotEmpty())
          <div class="grille g4">
            @foreach($produit->photos as $photo)
              <div style="position:relative">
                <div style="aspect-ratio:1;background:var(--surface-2);
                            border-radius:var(--r-sm);overflow:hidden;
                            border:{{ $loop->first ? '2px solid var(--brand)' : '1px solid var(--line)' }}">
                  <img src="{{ $photo->url() }}" alt="{{ $produit->nom }}"
                       style="width:100%;height:100%;object-fit:contain">
                </div>
                @if($loop->first)
                  <span class="jeton jeton-plein"
                        style="position:absolute;top:var(--s2);left:var(--s2)">Vignette</span>
                @endif
                <form method="POST" action="{{ route('vendeur.photo.supprimer', $photo) }}"
                      style="position:absolute;top:var(--s2);right:var(--s2)">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-grave btn-icone"
                          aria-label="Supprimer cette photo">
                    @include('partials.symbole', ['nom' => 'croix', 'taille' => 14])
                  </button>
                </form>
              </div>
            @endforeach
          </div>
        @endif

        @if($produit->photos->count() < 8)
          <form method="POST" action="{{ route('vendeur.produit.photos', $produit) }}"
                enctype="multipart/form-data" class="pile">
            @csrf
            <div class="champ">
              <label for="photos">Ajouter des photos</label>
              <input id="photos" type="file" name="photos[]" multiple required
                     accept="image/jpeg,image/png,image/webp"
                     @error('photos') aria-invalid="true" @enderror>
              <div class="aide">
                JPEG, PNG ou WebP · 3 Mo maximum par image · au moins 200 × 200
                pixels · {{ 8 - $produit->photos->count() }} emplacement(s) restant(s).
              </div>
              @error('photos')<div class="erreur">{{ $message }}</div>@enderror
            </div>
            <div><button type="submit" class="btn">Téléverser</button></div>
          </form>
        @else
          <div class="message message-info">
            @include('partials.symbole', ['nom' => 'info', 'taille' => 17])
            <div>Huit photos, c'est le maximum. Supprimez-en une pour en ajouter
            une autre.</div>
          </div>
        @endif
      </div>
    </div>
  @endif

  <form method="POST" class="bloc"
        action="{{ $produit ? route('vendeur.produit.modifier', $produit) : route('vendeur.produit.publier') }}">
    @csrf
    @if($produit) @method('PUT') @endif

    <div class="bloc-tete"><h2>La fiche</h2></div>

    <div class="bloc-corps">
      <div class="champ">
        <label for="nom">Nom du produit</label>
        <input id="nom" name="nom" value="{{ old('nom', $produit?->nom) }}" required
               placeholder="Fer à béton HA T10 — barre de 12 m"
               @error('nom') aria-invalid="true" @enderror>
        <div class="aide">
          Écrivez-le comme un client le chercherait : le type, la dimension,
          la longueur.
        </div>
        @error('nom')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="categorie_id">Catégorie</label>
        <select id="categorie_id" name="categorie_id" required
                @error('categorie_id') aria-invalid="true" @enderror>
          @foreach($categories as $c)
            <option value="{{ $c->id }}"
                    @selected(old('categorie_id', $produit?->categorie_id) == $c->id)>
              {{ $c->parente->nom }} › {{ $c->nom }}
            </option>
          @endforeach
        </select>
        @error('categorie_id')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="grille g2">
        <div class="champ">
          <label for="prix">Prix de vente, en francs</label>
          <input id="prix" name="prix" class="chiffre" inputmode="numeric" required
                 value="{{ old('prix', $produit?->prix) }}"
                 @error('prix') aria-invalid="true" @enderror>
          @error('prix')<div class="erreur">{{ $message }}</div>@enderror
        </div>
        <div class="champ">
          <label for="prix_barre">
            Prix barré <span class="facultatif">— facultatif</span>
          </label>
          <input id="prix_barre" name="prix_barre" class="chiffre" inputmode="numeric"
                 value="{{ old('prix_barre', $produit?->prix_barre) }}"
                 @error('prix_barre') aria-invalid="true" @enderror>
          @error('prix_barre')<div class="erreur">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="message message-info" style="margin-bottom:var(--s4)">
        @include('partials.symbole', ['nom' => 'info', 'taille' => 17])
        <div>
          La remise affichée se calcule des deux prix : elle ne se saisit pas.
          Un prix barré inférieur au prix de vente est refusé — une fausse remise
          se voit, et elle coûte la confiance de tout le monde.
        </div>
      </div>

      <div class="grille g2">
        <div class="champ">
          <label for="stock">Stock</label>
          <input id="stock" name="stock" class="chiffre" inputmode="numeric" required
                 value="{{ old('stock', $produit?->stock ?? 0) }}"
                 @error('stock') aria-invalid="true" @enderror>
          <div class="aide">À zéro, le produit reste visible mais n'est plus commandable.</div>
          @error('stock')<div class="erreur">{{ $message }}</div>@enderror
        </div>
        <div class="champ">
          <label for="marque">Marque <span class="facultatif">— facultatif</span></label>
          <input id="marque" name="marque" value="{{ old('marque', $produit?->marque) }}">
          <div class="aide">Elle sert de filtre au catalogue.</div>
        </div>
      </div>

      <div class="champ">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"
                  placeholder="Dimensions exactes, nuance d'acier, conditionnement…">{{ old('description', $produit?->description) }}</textarea>
      </div>

      <div class="champ">
        <label for="dessin">
          Dessin de repli
          <span class="facultatif">— tant qu'il n'y a pas de photo</span>
        </label>
        <select id="dessin" name="dessin">
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
        </select>
      </div>
    </div>

    <div class="bloc-pied" style="background:var(--surface);display:flex;
         justify-content:space-between;gap:var(--s3);flex-wrap:wrap">
      <a href="{{ route('vendeur.produits') }}" class="btn btn-clair">
        @include('partials.symbole', ['nom' => 'fleche-gauche', 'taille' => 15])
        Retour à mes produits
      </a>
      <button type="submit" class="btn">{{ $produit ? 'Enregistrer' : 'Publier' }}</button>
    </div>
  </form>
</div>

@endsection
