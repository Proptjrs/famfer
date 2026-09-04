@extends('layouts.app')
@section('titre', 'Mon compte')
@section('contenu')

@include('partials.entete', [
  'titre' => 'Mon compte',
  'sous' => 'Vos informations, votre rôle sur la place de marché, et votre mot de passe.',
  'fil' => [
    ['libelle' => 'Accueil', 'url' => route('accueil')],
    ['libelle' => 'Mon compte'],
  ],
])

<div class="deux-colonnes">
  <div class="pile-lg">

    <form method="POST" action="{{ route('compte.maj') }}" class="bloc">
      @csrf @method('PUT')
      <div class="bloc-tete">
        <h2>Mes informations</h2>
        <span class="sous">le livreur utilise ce téléphone</span>
      </div>
      <div class="bloc-corps">
        <div class="champ">
          <label for="name">Nom complet</label>
          <input id="name" name="name" value="{{ old('name', $utilisateur->name) }}"
                 required autocomplete="name" @error('name') aria-invalid="true" @enderror>
          @error('name')<div class="erreur">{{ $message }}</div>@enderror
        </div>

        <div class="champ">
          <label for="email">Adresse électronique</label>
          <input id="email" type="email" name="email" required autocomplete="email"
                 value="{{ old('email', $utilisateur->email) }}"
                 @error('email') aria-invalid="true" @enderror>
          <div class="aide">
            Chaque étape de vos commandes vous y est envoyée, code de remise compris.
          </div>
          @error('email')<div class="erreur">{{ $message }}</div>@enderror
        </div>

        <div class="champ">
          <label for="telephone">Téléphone</label>
          <input id="telephone" name="telephone" type="tel" class="chiffre" required
                 autocomplete="tel" value="{{ old('telephone', $utilisateur->telephone) }}"
                 @error('telephone') aria-invalid="true" @enderror>
          @error('telephone')<div class="erreur">{{ $message }}</div>@enderror
        </div>
      </div>
      <div class="bloc-pied" style="display:flex;justify-content:flex-end;background:var(--surface)">
        <button type="submit" class="btn">Enregistrer</button>
      </div>
    </form>

    <form method="POST" action="{{ route('compte.mdp') }}" class="bloc">
      @csrf @method('PUT')
      <div class="bloc-tete">
        <h2>Changer mon mot de passe</h2>
        <span class="sous">vos autres appareils seront déconnectés</span>
      </div>
      <div class="bloc-corps">
        <div class="champ">
          <label for="actuel">Mot de passe actuel</label>
          <input id="actuel" type="password" name="actuel" required
                 autocomplete="current-password">
        </div>

        <div class="champ">
          <label for="password">Nouveau mot de passe</label>
          <input id="password" type="password" name="password" required minlength="8"
                 autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
          <div class="aide">Huit caractères au minimum.</div>
          @error('password')<div class="erreur">{{ $message }}</div>@enderror
        </div>

        <div class="champ">
          <label for="password_confirmation">Confirmation</label>
          <input id="password_confirmation" type="password" name="password_confirmation"
                 required autocomplete="new-password">
        </div>
      </div>
      <div class="bloc-pied" style="display:flex;justify-content:flex-end;background:var(--surface)">
        <button type="submit" class="btn">Changer le mot de passe</button>
      </div>
    </form>
  </div>

  <div class="pile-lg colonne-fixe">

    <div class="bloc">
      <div class="bloc-tete"><h2>Mon rôle</h2></div>
      <div class="bloc-corps pile">
        <div class="rang-sm">
          <span class="jeton jeton-ok"><span class="point" aria-hidden="true"></span>Client</span>
          @if($boutique)
            @include('partials.etat-boutique', ['boutique' => $boutique])
          @endif
          @if($utilisateur->estAdmin())
            <span class="jeton jeton-info">Administration</span>
          @endif
        </div>

        @if(! $boutique)
          <p class="petit secondaire">
            Vous tenez une quincaillerie ? Ouvrez votre boutique : elle sera
            examinée par l'administration avant d'apparaître au catalogue.
          </p>
          <a href="{{ route('vendeur.ouvrir') }}" class="btn btn-bloc">Vendez sur FamFer</a>
        @else
          <div class="pile-sm">
            <div>
              <div style="font-weight:650">{{ $boutique->nom }}</div>
              <div class="petit secondaire">{{ $boutique->ville }}</div>
            </div>
            @if($boutique->statut === 'suspendue' && $boutique->motif_suspension)
              <div class="message message-grave petit">
                @include('partials.symbole', ['nom' => 'alerte', 'taille' => 15])
                <div><strong>Motif de la suspension :</strong> {{ $boutique->motif_suspension }}</div>
              </div>
            @endif
          </div>
          <a href="{{ route('vendeur.tableau') }}" class="btn btn-clair btn-bloc">Ma boutique</a>
        @endif
      </div>
    </div>

    <div class="bloc">
      <div class="bloc-tete"><h2>Raccourcis</h2></div>
      <div class="bloc-corps pile-sm">
        <a href="{{ route('mes-commandes') }}" class="rang-serre" style="color:var(--ink-2)">
          @include('partials.symbole', ['nom' => 'boite', 'taille' => 16])
          <span>Mes commandes</span>
          <span class="pousse secondaire">
            @include('partials.symbole', ['nom' => 'fleche-droite', 'taille' => 14])
          </span>
        </a>
        <a href="{{ route('adresses') }}" class="rang-serre" style="color:var(--ink-2)">
          @include('partials.symbole', ['nom' => 'camion', 'taille' => 16])
          <span>Mes adresses de livraison</span>
          <span class="pousse secondaire">
            @include('partials.symbole', ['nom' => 'fleche-droite', 'taille' => 14])
          </span>
        </a>
        <a href="{{ route('conditions') }}" class="rang-serre" style="color:var(--ink-2)">
          @include('partials.symbole', ['nom' => 'document', 'taille' => 16])
          <span>Conditions générales</span>
          <span class="pousse secondaire">
            @include('partials.symbole', ['nom' => 'fleche-droite', 'taille' => 14])
          </span>
        </a>
      </div>
    </div>
  </div>
</div>

@endsection
