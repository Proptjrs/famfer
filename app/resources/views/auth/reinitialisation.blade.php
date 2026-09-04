@extends('layouts.app')
@section('titre', 'Nouveau mot de passe')
@section('contenu')

<div class="page-etroite">
  <div style="text-align:center;margin-bottom:var(--s6)">
    <h1>Nouveau mot de passe</h1>
    <p class="secondaire" style="margin-top:var(--s1)">
      Choisissez-en un que vous n'utilisez nulle part ailleurs.
    </p>
  </div>

  <form method="POST" action="{{ route('mdp.reinitialiser') }}" class="bloc">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="bloc-corps">
      <div class="champ">
        <label for="email">Adresse électronique</label>
        <input id="email" type="email" name="email" value="{{ old('email', $email) }}"
               required autocomplete="email"
               @error('email') aria-invalid="true" @enderror>
        @error('email')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="password">Nouveau mot de passe</label>
        <input id="password" type="password" name="password" required minlength="8"
               autocomplete="new-password" autofocus
               @error('password') aria-invalid="true" @enderror>
        <div class="aide">Huit caractères au minimum.</div>
        @error('password')<div class="erreur">{{ $message }}</div>@enderror
      </div>

      <div class="champ">
        <label for="password_confirmation">Répétez-le</label>
        <input id="password_confirmation" type="password" name="password_confirmation"
               required autocomplete="new-password">
      </div>
    </div>

    <div class="bloc-pied" style="background:var(--surface)">
      <button type="submit" class="btn btn-lg btn-bloc">Changer mon mot de passe</button>
    </div>
  </form>
</div>

@endsection
