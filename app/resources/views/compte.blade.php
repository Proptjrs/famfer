@extends('layouts.app')
@section('titre', 'Mon compte')
@section('contenu')

<div style="max-width:640px">
<h1>Mon compte</h1>

<div class="carte" style="margin:14px 0">
  <h2 style="margin-bottom:10px">Mon rôle</h2>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
    <span class="etiq etiq-vert">Client</span>
    @if($boutique)
      @php
        [$mot, $classe] = match($boutique->statut) {
          'active' => ['Vendeur — boutique active', 'etiq-vert'],
          'en_attente' => ['Vendeur — en attente de validation', 'etiq-orange'],
          default => ['Boutique suspendue', 'etiq-rouge'],
        };
      @endphp
      <span class="etiq {{ $classe }}">{{ $mot }}</span>
    @endif
    @if($utilisateur->estAdmin())<span class="etiq etiq-gris">Administration</span>@endif
  </div>

  @if(! $boutique)
    <p style="color:var(--gris-fonce);margin-bottom:12px">
      Vous tenez une quincaillerie ? Ouvrez votre boutique : elle sera validée
      avant d'apparaître au catalogue.
    </p>
    <a href="{{ route('vendeur.ouvrir') }}" class="btn">Vendez sur FamFer</a>
  @else
    <p style="color:var(--gris-fonce);margin-bottom:12px">
      <strong>{{ $boutique->nom }}</strong> — {{ $boutique->ville }}
      @if($boutique->statut === 'suspendue' && $boutique->motif_suspension)
        <br><span style="color:var(--rouge)">Motif : {{ $boutique->motif_suspension }}</span>
      @endif
    </p>
    <a href="{{ route('vendeur.tableau') }}" class="btn btn-clair">Ma boutique</a>
  @endif
</div>

<form method="POST" action="{{ route('compte.maj') }}" class="carte" style="margin-bottom:14px">
  @csrf @method('PUT')
  <h2 style="margin-bottom:12px">Mes informations</h2>
  <div class="champ"><label>Nom complet</label>
    <input name="name" value="{{ old('name', $utilisateur->name) }}" required>
    @error('name')<div class="erreur">{{ $message }}</div>@enderror</div>
  <div class="champ"><label>Adresse électronique</label>
    <input type="email" name="email" value="{{ old('email', $utilisateur->email) }}" required>
    @error('email')<div class="erreur">{{ $message }}</div>@enderror</div>
  <div class="champ"><label>Téléphone</label>
    <input name="telephone" value="{{ old('telephone', $utilisateur->telephone) }}" required>
    @error('telephone')<div class="erreur">{{ $message }}</div>@enderror</div>
  <button class="btn">Enregistrer</button>
</form>

<form method="POST" action="{{ route('compte.mdp') }}" class="carte">
  @csrf @method('PUT')
  <h2 style="margin-bottom:12px">Changer mon mot de passe</h2>
  <div class="champ"><label>Mot de passe actuel</label>
    <input type="password" name="actuel" required autocomplete="current-password"></div>
  <div class="champ"><label>Nouveau mot de passe</label>
    <input type="password" name="password" required minlength="8" autocomplete="new-password">
    @error('password')<div class="erreur">{{ $message }}</div>@enderror</div>
  <div class="champ"><label>Confirmation</label>
    <input type="password" name="password_confirmation" required autocomplete="new-password"></div>
  <button class="btn">Changer</button>
  <p style="color:var(--gris);font-size:.84rem;margin-top:10px">
    Vos autres appareils seront déconnectés.
  </p>
</form>
</div>
@endsection
