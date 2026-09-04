@extends('layouts.app')
@section('titre', 'Créer un compte')
@section('contenu')

<div class="page-moyenne">
  <div style="text-align:center;margin-bottom:var(--s6)">
    <h1>Créer un compte</h1>
    <p class="secondaire" style="margin-top:var(--s1)">
      Gratuit. Rien n'est prélevé à l'inscription.
    </p>
  </div>

  <form method="POST" class="bloc">
    @csrf
    <div class="bloc-corps">

      {{-- L'acteur se décide ici, et nulle part ailleurs.

           Avant, tout compte naissait acheteur sans qu'on demande rien : une
           quincaillerie devait créer son compte, puis retrouver seule la porte
           « Vendez sur FamFer ». Le rôle n'était déterminé par personne. --}}
      <fieldset class="champ">
        <legend style="font-family:var(--f-ui);font-size:var(--t-xs);font-weight:650;
                       color:var(--ink-2);margin-bottom:var(--s3)">
          Vous venez sur FamFer pour
        </legend>

        <div class="choix">
          <label>
            <input type="radio" name="role" value="client"
                   @checked(old('role', $roleParDefaut) === 'client')>
            <span class="dedans">
              <span class="pastille" aria-hidden="true"></span><strong>Acheter</strong>
              <span class="quoi">
                Comparer les prix entre plusieurs quincailliers, commander, et
                vous faire livrer partout au Sénégal.
              </span>
            </span>
          </label>

          <label>
            <input type="radio" name="role" value="vendeur"
                   @checked(old('role', $roleParDefaut) === 'vendeur')>
            <span class="dedans">
              <span class="pastille" aria-hidden="true"></span><strong>Vendre</strong>
              <span class="quoi">
                Vous tenez une quincaillerie. Votre boutique sera examinée par
                l'administration avant d'apparaître au catalogue.
              </span>
            </span>
          </label>
        </div>

        @error('role')<div class="erreur">{{ $message }}</div>@enderror
      </fieldset>

      <div class="champ">
        <label for="name">Nom complet</label>
        <input id="name" name="name" value="{{ old('name') }}" required
               autocomplete="name" @error('name') aria-invalid="true" @enderror>
        @error('name')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="email">Adresse électronique</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}"
               required autocomplete="email"
               @error('email') aria-invalid="true" @enderror>
        <div class="aide">
          Chaque étape de vos commandes vous y sera envoyée, code de remise compris.
        </div>
        @error('email')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="telephone">Téléphone</label>
        <input id="telephone" name="telephone" type="tel" class="chiffre"
               value="{{ old('telephone') }}" placeholder="+221 77 000 00 00"
               required autocomplete="tel"
               @error('telephone') aria-invalid="true" @enderror>
        <div class="aide">Le livreur vous appellera à ce numéro.</div>
        @error('telephone')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="password">Mot de passe</label>
        <input id="password" type="password" name="password" required minlength="8"
               placeholder="8 caractères minimum" autocomplete="new-password"
               @error('password') aria-invalid="true" @enderror>
        @error('password')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="password_confirmation">Confirmation</label>
        <input id="password_confirmation" type="password" name="password_confirmation"
               required autocomplete="new-password">
      </div>
    </div>

    <div class="bloc-pied" style="background:var(--surface)">
      <button type="submit" class="btn btn-lg btn-bloc">Créer mon compte</button>
      <p class="mini secondaire" style="text-align:center;margin-top:var(--s3)">
        En créant un compte, vous acceptez les
        <a href="{{ route('conditions') }}" class="lien">conditions générales</a>.
      </p>
    </div>
  </form>

  <p class="secondaire" style="text-align:center;margin-top:var(--s5)">
    Déjà un compte ?
    <a href="{{ route('connexion') }}" class="lien">Se connecter</a>
  </p>
</div>

@endsection
