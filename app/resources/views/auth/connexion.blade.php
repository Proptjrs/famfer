@extends('layouts.app')
@section('titre', 'Se connecter')
@section('contenu')
<div style="max-width:420px;margin:0 auto">
  <h1>Bon retour</h1>
  <p class="sous">Accédez à vos commandes et à votre commerce.</p>
  <form method="POST" class="carte">
    @csrf
    <div class="champ">
      <label>Adresse électronique</label>
      <input type="email" name="email" value="{{ old('email') }}" required>
      @error('email')<div class="erreur">{{ $message }}</div>@enderror
    </div>
    <div class="champ">
      <label style="display:flex;justify-content:space-between;align-items:baseline">
        <span>Mot de passe</span>
        <a href="{{ route('mdp.oubli') }}" style="font-size:.84rem;font-weight:400">Oublié ?</a>
      </label>
      <input type="password" name="password" required>
    </div>
    <label style="display:flex;gap:8px;align-items:center;font-size:.9rem;margin-bottom:16px">
      <input type="checkbox" name="memoriser" value="1" style="width:auto"> Se souvenir de moi
    </label>
    <button class="btn" style="width:100%;justify-content:center">Se connecter</button>
  </form>
  <p style="text-align:center;margin-top:16px;color:var(--gris)">
    Pas encore de compte ? <a href="{{ route('inscription') }}">En créer un</a>
  </p>
</div>
@endsection
