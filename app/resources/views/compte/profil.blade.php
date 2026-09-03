@extends('layouts.app')
@section('titre', 'Mon compte')
@section('contenu')
<div style="max-width:640px">
<h1>Mon compte</h1>
<p class="sous">
  L'adresse enregistrée ici sert à chiffrer vos livraisons : le fer se facture
  au poids et à la distance.
</p>

{{-- Ce que ce compte est, et ce qu'il peut devenir.
     Sans ce bloc, rien n'indiquait à un utilisateur s'il était simple acheteur,
     vendeur en attente de vérification ou commerçant vérifié : il fallait le
     deviner à la présence d'un lien dans la barre du haut. --}}
<div class="carte" style="margin-bottom:22px">
  <h2 style="margin-bottom:14px">Mon rôle sur FamFer</h2>

  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
    <span class="etiq etiq-vert">Acheteur</span>
    @if($vendeur)
      @php
        [$mot, $classe] = match($vendeur->statut) {
          'verifie' => ['Vendeur vérifié', 'etiq-vert'],
          'en_attente' => ['Vendeur — vérification en cours', 'etiq-ambre'],
          'suspendu' => ['Vendeur suspendu', 'etiq-rouge'],
          default => [$vendeur->statut, 'etiq-gris'],
        };
      @endphp
      <span class="etiq {{ $classe }}">{{ $mot }}</span>
    @endif
    @if($utilisateur->est_admin)<span class="etiq etiq-gris">Administration</span>@endif
  </div>

  @if(! $vendeur)
    <p style="color:var(--gris);margin-bottom:12px;max-width:70ch">
      Tout compte naît acheteur. Pour vendre du fer sur FamFer, déposez une
      demande : votre établissement est vérifié avant d'apparaître chez le
      moindre acheteur — c'est la contrepartie du séquestre, puisque la
      plateforme encaisse pour votre compte.
    </p>
    <a href="{{ route('vendeur.demande') }}" class="btn">Vendre sur FamFer</a>

  @elseif($vendeur->statut === 'en_attente')
    <p style="color:var(--gris);margin-bottom:12px;max-width:70ch">
      <strong>{{ $vendeur->raison_sociale }}</strong> attend la vérification de
      l'administration. Vous pouvez déjà préparer vos offres : elles resteront
      invisibles jusque-là, puis apparaîtront sans que vous ayez à les
      republier.
    </p>
    <a href="{{ route('vendeur.offres') }}" class="btn btn-clair">Préparer mes offres</a>

  @elseif($vendeur->statut === 'suspendu')
    <p style="color:var(--rouge);margin-bottom:12px;max-width:70ch">
      <strong>{{ $vendeur->raison_sociale }}</strong> est suspendue.
      @if($vendeur->motif_suspension) Motif : {{ $vendeur->motif_suspension }}. @endif
      Vos offres ne sont plus visibles. Répondez à l'administration pour
      corriger votre dossier.
    </p>

  @else
    <p style="color:var(--gris);margin-bottom:14px;max-width:70ch">
      <strong>{{ $vendeur->raison_sociale }}</strong> — {{ $vendeur->commune }},
      vérifiée {{ $vendeur->verifie_le?->translatedFormat('en F Y') }}.
      Vos offres sont visibles et vous pouvez recevoir des commandes.
    </p>

    {{-- Ce que la plateforme gagne sur ce vendeur, dit clairement.
         Une place de marché qui retient l'argent de ses commerçants et ne leur
         montre pas ce qu'elle prélève leur demande une confiance qu'elle ne
         rend pas. --}}
    <div class="grille g3" style="margin-bottom:14px">
      <div class="carte" style="box-shadow:none">
        <div class="chiffre mono">{{ number_format($commission['chiffre_affaires'], 0, ',', ' ') }} F</div>
        <div class="chiffre-note">Vos ventes abouties, tout compris</div>
      </div>
      <div class="carte" style="box-shadow:none;border-color:var(--forge)">
        <div class="chiffre mono" style="color:var(--forge)">
          − {{ number_format($commission['commission_versee'], 0, ',', ' ') }} F
        </div>
        <div class="chiffre-note">
          Commission FamFer, {{ $vendeur->taux_commission_pour_mille / 10 }} %
          <br><span style="color:var(--gris)">sur la marchandise seule, jamais sur la livraison</span>
        </div>
      </div>
      <div class="carte" style="box-shadow:none">
        <div class="chiffre mono">{{ number_format($commission['net_percu'], 0, ',', ' ') }} F</div>
        <div class="chiffre-note">Ce qui vous revient</div>
      </div>
    </div>

    <p style="color:var(--gris);font-size:.86rem;margin-bottom:14px;max-width:70ch">
      Rien n'est prélevé à l'inscription ni à la publication d'une offre. La
      commission n'est due qu'une fois la commande reçue par l'acheteur : une
      vente annulée, expirée ou remboursée ne coûte rien.
    </p>

    <a href="{{ route('vendeur.tableau') }}" class="btn btn-clair">Mon commerce</a>
    <a href="{{ route('vendeur.argent') }}" class="btn btn-clair">Mon argent</a>
  @endif
</div>

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
