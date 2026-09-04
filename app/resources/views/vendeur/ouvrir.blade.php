@extends('layouts.app')
@section('titre', 'Ouvrir ma boutique')
@section('contenu')

<div class="page-moyenne">
  <div style="text-align:center;margin-bottom:var(--s6)">
    <h1>Vendez sur FamFer</h1>
    <p class="secondaire" style="margin-top:var(--s1)">
      Gratuit à l'ouverture. Vous ne payez qu'une commission sur ce que vous
      livrez réellement.
    </p>
  </div>

  <div class="grille g3" style="margin-bottom:var(--s6)">
    @foreach([
      ['boutique', 'Une vitrine', 'Vos produits dans les rayons, sans site à construire.'],
      ['argent', 'Vous encaissez', 'Le client vous règle en espèces à la livraison, port compris.'],
      ['balance', 'Rien sur un refus', 'Une commande refusée à la porte ne vous coûte aucune commission.'],
    ] as [$icone, $titre, $texte])
      <div class="carte pile-sm">
        <span style="color:var(--brand-strong)">
          @include('partials.symbole', ['nom' => $icone, 'taille' => 20])
        </span>
        <strong>{{ $titre }}</strong>
        <span class="petit secondaire">{{ $texte }}</span>
      </div>
    @endforeach
  </div>

  <form method="POST" class="bloc">
    @csrf
    <div class="bloc-tete">
      <h2>Votre boutique</h2>
      <span class="sous">examinée avant d'apparaître au catalogue</span>
    </div>

    <div class="bloc-corps">
      <div class="champ">
        <label for="nom">Nom de la boutique</label>
        <input id="nom" name="nom" value="{{ old('nom') }}" required
               placeholder="Quincaillerie Ndiaye & Frères"
               @error('nom') aria-invalid="true" @enderror>
        <div class="aide">C'est ce nom que les acheteurs verront sur chaque produit.</div>
        @error('nom')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="description">
          Ce que vous vendez <span class="facultatif">— facultatif</span>
        </label>
        <textarea id="description" name="description" rows="3"
                  placeholder="Fer à béton, tôles, outillage, plomberie…">{{ old('description') }}</textarea>
      </div>

      <div class="champ">
        <label for="telephone">Téléphone</label>
        <input id="telephone" name="telephone" type="tel" class="chiffre" required
               value="{{ old('telephone', auth()->user()->telephone) }}"
               @error('telephone') aria-invalid="true" @enderror>
        @error('telephone')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="adresse">Adresse du magasin</label>
        <input id="adresse" name="adresse" value="{{ old('adresse') }}" required
               placeholder="Marché central, allée 4"
               @error('adresse') aria-invalid="true" @enderror>
        @error('adresse')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="ville">Ville</label>
        <input id="ville" name="ville" value="{{ old('ville') }}" required
               @error('ville') aria-invalid="true" @enderror>
        @error('ville')<div class="erreur">{{ $message }}</div>@enderror
      </div>
    </div>

    <div class="bloc-pied" style="background:var(--surface)">
      <button type="submit" class="btn btn-lg btn-bloc">Ouvrir ma boutique</button>
      <p class="mini secondaire" style="text-align:center;margin-top:var(--s3)">
        La validation protège les acheteurs — et votre réputation.
      </p>
    </div>
  </form>
</div>

@endsection
