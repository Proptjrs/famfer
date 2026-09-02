@extends('layouts.app')
@section('titre', 'Mon compte')
@section('contenu')
<div style="max-width:640px">
<h1>Mon compte</h1>
<p class="sous">
  L'adresse enregistrée ici sert à chiffrer vos livraisons : le fer se facture
  au poids et à la distance.
</p>

<form method="POST" action="{{ route('compte.maj') }}" class="carte" style="margin-bottom:22px">
  @csrf @method('PUT')
  <h2 style="margin-bottom:14px">Mes informations</h2>

  <div class="champ"><label>Nom ou raison sociale</label>
    <input name="name" value="{{ old('name', $utilisateur->name) }}" required>
    @error('name')<div class="erreur">{{ $message }}</div>@enderror</div>

  <div class="champ"><label>Adresse électronique</label>
    <input type="email" name="email" value="{{ old('email', $utilisateur->email) }}" required>
    @error('email')<div class="erreur">{{ $message }}</div>@enderror</div>

  <div class="grid2">
    <div class="champ"><label>Téléphone</label>
      <input name="telephone" value="{{ old('telephone', $acheteur?->telephone) }}" required>
      @error('telephone')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Vous achetez pour</label>
      <select name="genre" required>
        @foreach(['particulier' => 'Moi-même', 'chantier' => 'Un chantier', 'entreprise' => 'Une entreprise'] as $c => $mot)
          <option value="{{ $c }}" @selected(old('genre', $acheteur?->genre) === $c)>{{ $mot }}</option>
        @endforeach
      </select></div>
  </div>

  <div class="champ"><label>Adresse de livraison habituelle</label>
    <input name="adresse_defaut" value="{{ old('adresse_defaut', $acheteur?->adresse_defaut) }}"
           placeholder="Quartier, repère"></div>

  <div class="grid2">
    <div class="champ"><label>Latitude</label>
      <input name="latitude" id="lat" value="{{ old('latitude', $acheteur?->latitude) }}"></div>
    <div class="champ"><label>Longitude</label>
      <input name="longitude" id="lng" value="{{ old('longitude', $acheteur?->longitude) }}"></div>
  </div>

  <button type="button" class="btn btn-sm btn-clair" id="me-situer" style="margin-bottom:14px">
    Relever ma position
  </button>
  <p style="color:var(--gris);font-size:.86rem;margin-bottom:14px">
    Sans coordonnées, la livraison ne peut pas être chiffrée et seul le retrait
    au magasin reste possible.
  </p>

  <button class="btn">Enregistrer</button>
</form>

<form method="POST" action="{{ route('compte.mdp') }}" class="carte">
  @csrf @method('PUT')
  <h2 style="margin-bottom:14px">Changer mon mot de passe</h2>

  <div class="champ"><label>Mot de passe actuel</label>
    <input type="password" name="actuel" required autocomplete="current-password"></div>
  <div class="champ"><label>Nouveau mot de passe</label>
    <input type="password" name="password" required minlength="8" autocomplete="new-password">
    @error('password')<div class="erreur">{{ $message }}</div>@enderror</div>
  <div class="champ"><label>Répétez-le</label>
    <input type="password" name="password_confirmation" required autocomplete="new-password"></div>

  <button class="btn">Changer</button>
  <p style="color:var(--gris);font-size:.86rem;margin-top:10px">
    Vos autres appareils seront déconnectés.
  </p>
</form>
</div>

<script>
  document.getElementById('me-situer').addEventListener('click', function () {
    if (!navigator.geolocation) { alert("Votre navigateur ne donne pas la position."); return; }
    this.textContent = 'Recherche…';
    navigator.geolocation.getCurrentPosition(
      (p) => {
        document.getElementById('lat').value = p.coords.latitude.toFixed(6);
        document.getElementById('lng').value = p.coords.longitude.toFixed(6);
        this.textContent = 'Position relevée';
      },
      () => { this.textContent = 'Position refusée — saisissez-la à la main'; }
    );
  });
</script>
@endsection
