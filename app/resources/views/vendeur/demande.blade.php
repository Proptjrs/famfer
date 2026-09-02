@extends('layouts.app')
@section('titre', 'Vendre sur FamFer')
@section('contenu')

<div style="max-width:640px;margin:0 auto">
  <h1>Vendre sur FamFer</h1>
  <p class="sous">
    Publiez votre stock, recevez des commandes payées d'avance, et touchez votre
    argent dès que l'acheteur confirme la réception.
  </p>

  <div class="carte" style="margin-bottom:22px;border-left:4px solid var(--forge)">
    <strong>Comment nous nous rémunérons</strong>
    <p style="color:var(--gris);margin-top:6px">
      Une commission de 8 % sur chaque vente aboutie. Rien à l'inscription, rien
      par mois, et rien du tout si vous ne vendez pas. L'acheteur paie FamFer,
      nous retenons l'argent jusqu'à la livraison, puis nous vous versons le
      montant diminué de cette commission.
    </p>
  </div>

  <form method="POST" class="carte">
    @csrf

    <div class="champ">
      <label>Raison sociale</label>
      <input name="raison_sociale" value="{{ old('raison_sociale') }}"
             placeholder="Quincaillerie Ndiaye & Frères" required>
      @error('raison_sociale')<div class="erreur">{{ $message }}</div>@enderror
    </div>

    <div class="champ">
      <label>NINEA <span style="font-weight:400;color:var(--gris)">— facultatif, mais il accélère la vérification</span></label>
      <input name="ninea" value="{{ old('ninea') }}" placeholder="0012345678">
      @error('ninea')<div class="erreur">{{ $message }}</div>@enderror
    </div>

    <div class="champ">
      <label>Téléphone</label>
      <input name="telephone" value="{{ old('telephone') }}" placeholder="+221 77 000 00 00" required>
      @error('telephone')<div class="erreur">{{ $message }}</div>@enderror
    </div>

    <div class="champ">
      <label>Adresse du magasin</label>
      <input name="adresse" value="{{ old('adresse') }}" placeholder="Marché central, allée 4" required>
      @error('adresse')<div class="erreur">{{ $message }}</div>@enderror
    </div>

    <div class="champ">
      <label>Commune</label>
      <input name="commune" value="{{ old('commune') }}" placeholder="Pikine" required>
      @error('commune')<div class="erreur">{{ $message }}</div>@enderror
    </div>

    {{-- La position sert à classer les offres par distance : sans elle, une
         quincaillerie de Pikine s'affiche comme si elle était à Thiès. --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="champ">
        <label>Latitude</label>
        <input name="latitude" value="{{ old('latitude', '14.7547') }}" required>
        @error('latitude')<div class="erreur">{{ $message }}</div>@enderror
      </div>
      <div class="champ">
        <label>Longitude</label>
        <input name="longitude" value="{{ old('longitude', '-17.3906') }}" required>
        @error('longitude')<div class="erreur">{{ $message }}</div>@enderror
      </div>
    </div>
    <p style="color:var(--gris);font-size:.85rem;margin:-6px 0 18px">
      Ces coordonnées placent votre magasin sur la carte et servent à classer vos
      offres par distance. Relevez-les sur votre téléphone, devant la boutique.
    </p>

    <button class="btn" style="width:100%">Envoyer ma demande</button>

    <p style="color:var(--gris);font-size:.86rem;margin-top:14px">
      Vos offres resteront invisibles tant que l'administration n'aura pas
      vérifié votre établissement. Nous encaissons pour votre compte : nous
      devons savoir à qui nous versons l'argent.
    </p>
  </form>
</div>

@endsection
