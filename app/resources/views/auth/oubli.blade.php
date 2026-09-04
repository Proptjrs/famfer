@extends('layouts.app')
@section('titre', 'Mot de passe oublié')
@section('contenu')

<div class="page-etroite">
  <div style="text-align:center;margin-bottom:var(--s6)">
    <h1>Mot de passe oublié</h1>
    <p class="secondaire" style="margin-top:var(--s1)">
      Indiquez l'adresse de votre compte. Vous recevrez un lien valable une heure.
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
    </div>
    <div class="bloc-pied" style="background:var(--surface)">
      <button type="submit" class="btn btn-lg btn-bloc">Envoyer le lien</button>
    </div>
  </form>

  <p class="secondaire" style="text-align:center;margin-top:var(--s5)">
    <a href="{{ route('connexion') }}" class="lien">Retour à la connexion</a>
  </p>
</div>

@endsection
