@extends('layouts.app')
@section('titre', 'Nouveau mot de passe')
@section('contenu')
<div style="max-width:420px;margin:0 auto">
  <h1>Nouveau mot de passe</h1>
  <p class="sous">Choisissez-en un que vous n'utilisez nulle part ailleurs.</p>
  <form method="POST" action="{{ route('mdp.reinitialiser') }}" class="carte">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="champ">
      <label>Adresse électronique</label>
      <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email">
      @error('email')<div class="erreur">{{ $message }}</div>@enderror
    </div>
    <div class="champ">
      <label>Nouveau mot de passe</label>
      <input type="password" name="password" required minlength="8" autocomplete="new-password">
      @error('password')<div class="erreur">{{ $message }}</div>@enderror
    </div>
    <div class="champ">
      <label>Répétez-le</label>
      <input type="password" name="password_confirmation" required autocomplete="new-password">
    </div>
    <button class="btn" style="width:100%;justify-content:center">Changer mon mot de passe</button>
  </form>
</div>
@endsection
