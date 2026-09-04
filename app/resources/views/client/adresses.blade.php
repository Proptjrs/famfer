@extends('layouts.app')
@section('titre', 'Mes adresses')
@section('contenu')

@include('partials.entete', [
  'titre' => 'Mes adresses de livraison',
  'sous' => 'La région détermine les frais de livraison — offerts dès 50 000 F d\'achat.',
  'fil' => [
    ['libelle' => 'Mon compte', 'url' => route('compte')],
    ['libelle' => 'Mes adresses'],
  ],
])

<div class="deux-colonnes">
  <div class="pile">
    @forelse($adresses as $a)
      <div class="carte rang" style="align-items:flex-start">
        <div style="flex:1 1 14rem;min-width:0">
          <div class="rang-sm">
            <strong>{{ $a->destinataire }}</strong>
            @if($a->par_defaut)
              <span class="jeton jeton-ok">
                <span class="point" aria-hidden="true"></span>par défaut
              </span>
            @endif
          </div>
          <div class="chiffre petit secondaire">{{ $a->telephone }}</div>
          <div class="petit" style="color:var(--ink-2);margin-top:var(--s1)">
            {{ $a->enUneLigne() }}
          </div>
        </div>

        <form method="POST" action="{{ route('adresses.supprimer', $a) }}" class="pousse">
          @csrf @method('DELETE')
          <button type="submit" class="btn btn-sm btn-fantome">
            @include('partials.symbole', ['nom' => 'croix', 'taille' => 14])
            Supprimer
          </button>
        </form>
      </div>
    @empty
      <div class="bloc">
        @include('partials.vide', [
          'icone' => 'camion',
          'titre' => 'Aucune adresse enregistrée',
          'texte' => 'Ajoutez-en une : vos prochaines commandes se valideront en un clic, sans ressaisir le quartier ni le repère.',
        ])
      </div>
    @endforelse
  </div>

  <form method="POST" action="{{ route('adresses.ajouter') }}" class="bloc colonne-fixe">
    @csrf
    <div class="bloc-tete"><h2>Ajouter une adresse</h2></div>
    <div class="bloc-corps">

      <div class="champ">
        <label for="destinataire">Destinataire</label>
        <input id="destinataire" name="destinataire" required autocomplete="name"
               value="{{ old('destinataire', auth()->user()->name) }}"
               @error('destinataire') aria-invalid="true" @enderror>
        @error('destinataire')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="telephone">Téléphone</label>
        <input id="telephone" name="telephone" type="tel" class="chiffre" required
               autocomplete="tel" value="{{ old('telephone', auth()->user()->telephone) }}"
               @error('telephone') aria-invalid="true" @enderror>
        <div class="aide">Le livreur appellera ce numéro avant de passer.</div>
        @error('telephone')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="region">Région</label>
        <select id="region" name="region" required>
          @foreach($regions as $r)
            <option value="{{ $r }}" @selected(old('region') === $r)>{{ $r }}</option>
          @endforeach
          <option value="Autre" @selected(old('region') === 'Autre')>Autre région</option>
        </select>
        <div class="aide">Elle fixe les frais de livraison.</div>
      </div>

      <div class="champ">
        <label for="ville">Ville</label>
        <input id="ville" name="ville" value="{{ old('ville') }}" required
               @error('ville') aria-invalid="true" @enderror>
        @error('ville')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="quartier">Quartier</label>
        <input id="quartier" name="quartier" value="{{ old('quartier') }}" required
               @error('quartier') aria-invalid="true" @enderror>
        @error('quartier')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="repere">Repère <span class="facultatif">— facultatif</span></label>
        <input id="repere" name="repere" value="{{ old('repere') }}"
               placeholder="En face de la pharmacie, portail bleu…">
        <div class="aide">
          Beaucoup d'adresses se trouvent au repère plutôt qu'au numéro.
        </div>
      </div>

      <label class="case" style="margin-top:var(--s4)">
        <input type="checkbox" name="par_defaut" value="1" @checked(old('par_defaut'))>
        <span>En faire mon adresse par défaut</span>
      </label>
    </div>

    <div class="bloc-pied" style="background:var(--surface)">
      <button type="submit" class="btn btn-bloc">Enregistrer l'adresse</button>
    </div>
  </form>
</div>

@endsection
