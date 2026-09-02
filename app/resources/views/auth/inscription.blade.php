@extends('layouts.app')
@section('titre', 'Créer un compte')
@section('contenu')
<div style="max-width:480px;margin:0 auto">
  <h1>Créer un compte</h1>
  <p class="sous">Gratuit. Vous pourrez commander dans la minute.</p>
  <form method="POST" class="carte">
    @csrf
    <div class="champ"><label>Nom complet</label>
      <input name="name" value="{{ old('name') }}" required>
      @error('name')<div class="erreur">{{ $message }}</div>@enderror</div>
    <div class="champ"><label>Adresse électronique</label>
      <input type="email" name="email" value="{{ old('email') }}" required>
      @error('email')<div class="erreur">{{ $message }}</div>@enderror</div>
    <div class="champ"><label>Téléphone</label>
      <input name="telephone" value="{{ old('telephone') }}" placeholder="+221 77 000 00 00" required>
      @error('telephone')<div class="erreur">{{ $message }}</div>@enderror</div>
    <div class="champ"><label>Vous achetez en tant que</label>
      <select name="genre" required>
        <option value="particulier">Particulier</option>
        <option value="chantier">Chantier</option>
        <option value="entreprise">Entreprise</option>
      </select></div>
    <div class="champ"><label>Mot de passe</label>
      <input type="password" name="password" required placeholder="8 caractères minimum">
      @error('password')<div class="erreur">{{ $message }}</div>@enderror</div>
    <div class="champ"><label>Confirmation</label>
      <input type="password" name="password_confirmation" required></div>
    <button class="btn" style="width:100%;justify-content:center">Créer mon compte</button>
  </form>
</div>
@endsection
