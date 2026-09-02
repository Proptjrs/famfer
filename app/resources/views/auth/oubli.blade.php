@extends('layouts.app')
@section('titre', 'Mot de passe oublié')
@section('contenu')
<div style="max-width:420px;margin:0 auto">
  <h1>Mot de passe oublié</h1>
  <p class="sous">
    Indiquez l'adresse de votre compte. Vous recevrez un lien valable une heure.
  </p>
  <form method="POST" class="carte">
    @csrf
    <div class="champ">
      <label>Adresse électronique</label>
      <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
      @error('email')<div class="erreur">{{ $message }}</div>@enderror
    </div>
    <button class="btn" style="width:100%;justify-content:center">Envoyer le lien</button>
  </form>
  <p style="text-align:center;margin-top:16px;color:var(--gris)">
    <a href="{{ route('connexion') }}">Retour à la connexion</a>
  </p>
</div>
@endsection
