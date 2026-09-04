@extends('layouts.app')
@section('titre', 'Se connecter')
@section('contenu')

<div class="page-etroite">
  <div style="text-align:center;margin-bottom:var(--s6)">
    <h1>Bon retour</h1>
    <p class="secondaire" style="margin-top:var(--s1)">
      Accédez à vos commandes et à votre commerce.
    </p>
  </div>

  <form method="POST" class="bloc">
    @csrf
    <div class="bloc-corps">
      <div class="champ">
        <label for="email">Adresse électronique</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}"
               required autocomplete="email" autofocus
               @error('email') aria-invalid="true" @enderror>
        @error('email')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="password" style="display:flex;justify-content:space-between;
               align-items:baseline;gap:var(--s3)">
          <span>Mot de passe</span>
          <a href="{{ route('mdp.oubli') }}" class="lien mini"
             style="font-weight:400">Oublié ?</a>
        </label>
        <input id="password" type="password" name="password" required
               autocomplete="current-password">
      </div>

      <label class="case" style="margin-top:var(--s4)">
        <input type="checkbox" name="memoriser" value="1" @checked(old('memoriser'))>
        <span>Se souvenir de moi sur cet appareil</span>
      </label>
    </div>

    <div class="bloc-pied" style="background:var(--surface)">
      <button type="submit" class="btn btn-lg btn-bloc">Se connecter</button>
    </div>
  </form>

  <p class="secondaire" style="text-align:center;margin-top:var(--s5)">
    Pas encore de compte ?
    <a href="{{ route('inscription') }}" class="lien">En créer un</a>
  </p>
</div>

@endsection
