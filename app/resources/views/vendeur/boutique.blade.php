@extends('layouts.app')
@section('titre', 'Ma vitrine')
@section('contenu')

<div class="page-moyenne">

  @include('partials.entete', [
    'titre' => 'Ma vitrine',
    'sous' => 'Ce que les clients voient avant de vous acheter.',
    'fil' => [
      ['libelle' => 'Ma boutique', 'url' => route('vendeur.tableau')],
      ['libelle' => 'Ma vitrine'],
    ],
    'actions' => $boutique->estVisible()
      ? '<a href="' . route('boutique', $boutique) . '" class="btn btn-clair">Voir ma page publique</a>'
      : null,
  ])

  <div class="carte rang" style="margin-bottom:var(--s5);align-items:flex-start">
    <div style="flex:1 1 16rem;min-width:0">
      <div style="font-weight:700;font-size:var(--t-md)">{{ $boutique->nom }}</div>
      <div class="petit secondaire" style="margin-top:var(--s1)">
        {{-- Le nom ne se modifie pas ici : il sert d'adresse publique, et le
             changer casserait les liens déjà partagés. --}}
        Le nom ne se change pas : il sert d'adresse publique, et le modifier
        casserait les liens déjà partagés.
      </div>
      <div class="rang-sm" style="margin-top:var(--s3)">
        @include('partials.etat-boutique', ['boutique' => $boutique])
      </div>
    </div>

    <div class="pousse" style="text-align:right">
      @if($boutique->nombre_avis)
        <div class="etoiles" aria-hidden="true">
          {{ str_repeat('★', (int) round($boutique->noteSurCinq())) }}
        </div>
        <div class="petit secondaire">
          <span class="chiffre">{{ number_format($boutique->noteSurCinq(), 1, ',', ' ') }}</span>
          sur 5 · {{ $boutique->nombre_avis }} avis
        </div>
      @else
        <span class="jeton jeton-neutre">Pas encore d'avis</span>
      @endif
    </div>
  </div>

  <form method="POST" action="{{ route('vendeur.boutique.maj') }}" class="bloc">
    @csrf @method('PUT')

    <div class="bloc-tete"><h2>Vos coordonnées</h2></div>

    <div class="bloc-corps">
      <div class="champ">
        <label for="description">Ce que vous vendez</label>
        <textarea id="description" name="description" rows="4"
                  placeholder="Fer à béton, tôles, outillage, plomberie…">{{ old('description', $boutique->description) }}</textarea>
        <div class="aide">
          Ce texte s'affiche en haut de votre page publique, sous le nom.
        </div>
      </div>

      <div class="champ">
        <label for="telephone">Téléphone</label>
        <input id="telephone" name="telephone" type="tel" class="chiffre" required
               value="{{ old('telephone', $boutique->telephone) }}"
               @error('telephone') aria-invalid="true" @enderror>
        @error('telephone')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="adresse">Adresse du magasin</label>
        <input id="adresse" name="adresse" required
               value="{{ old('adresse', $boutique->adresse) }}"
               @error('adresse') aria-invalid="true" @enderror>
        @error('adresse')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="ville">Ville</label>
        <input id="ville" name="ville" required
               value="{{ old('ville', $boutique->ville) }}"
               @error('ville') aria-invalid="true" @enderror>
        <div class="aide">Elle s'affiche sur chacun de vos produits.</div>
        @error('ville')<div class="erreur">{{ $message }}</div>@enderror
      </div>
    </div>

    <div class="bloc-pied" style="background:var(--surface);display:flex;justify-content:flex-end">
      <button type="submit" class="btn">Enregistrer</button>
    </div>
  </form>
</div>

@endsection
