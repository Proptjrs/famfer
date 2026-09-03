@extends('layouts.app')
@section('titre', 'Créer un compte')
@section('contenu')

<style>
  /* Le choix de ce qu'on vient faire, en deux cartes plutôt qu'en liste
     déroulante : c'est la décision la plus structurante du formulaire — elle
     détermine l'acteur — et elle ne doit pas se lire comme un champ de plus. */
  .choix-role{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
              margin-bottom:20px}
  .choix-role label{display:block;cursor:pointer;border:2px solid var(--bord);
                    border-radius:var(--r-sm);padding:14px;background:var(--blanc)}
  .choix-role label:hover{border-color:var(--acier-3)}
  .choix-role input{position:absolute;opacity:0;width:0;height:0}
  .choix-role input:checked + .dedans{color:var(--forge)}
  .choix-role label:has(input:checked){border-color:var(--forge);background:var(--forge-pale)}
  /* Repli pour les navigateurs sans « :has » : la puce reste visible. */
  .choix-role .puce{display:inline-block;width:14px;height:14px;border-radius:50%;
                    border:2px solid var(--gris);margin-right:8px;vertical-align:-2px}
  .choix-role input:checked + .dedans .puce{border-color:var(--forge);
                    box-shadow:inset 0 0 0 3px var(--forge)}
  .choix-role strong{display:inline}
  .choix-role .quoi{display:block;color:var(--gris);font-size:.86rem;margin-top:6px}
</style>

<div style="max-width:560px;margin:0 auto">
  <h1>Créer un compte</h1>
  <p class="sous">Gratuit. Rien n'est prélevé à l'inscription.</p>

  <form method="POST" class="carte">
    @csrf

    {{-- L'acteur se décide ici, et nulle part ailleurs.
         Avant, tout compte naissait acheteur sans qu'on demande rien : une
         quincaillerie devait créer son compte, puis retrouver seule la porte
         « Vendez sur FamFer ». Le rôle n'était déterminé par personne. --}}
    <div class="champ">
      <label style="margin-bottom:10px">Vous venez sur FamFer pour</label>
      <div class="choix-role">
        <label>
          <input type="radio" name="role" value="acheteur"
                 @checked(old('role', $roleParDefaut) === 'acheteur')>
          <span class="dedans">
            <span class="puce"></span><strong>Acheter du fer</strong>
            <span class="quoi">
              Comparer les prix des quincailleries, commander, se faire livrer.
            </span>
          </span>
        </label>

        <label>
          <input type="radio" name="role" value="vendeur"
                 @checked(old('role', $roleParDefaut) === 'vendeur')>
          <span class="dedans">
            <span class="puce"></span><strong>Vendre du fer</strong>
            <span class="quoi">
              Vous tenez une quincaillerie. Votre établissement sera vérifié
              avant d'apparaître chez les acheteurs.
            </span>
          </span>
        </label>
      </div>
      @error('role')<div class="erreur">{{ $message }}</div>@enderror
    </div>

    <div class="champ"><label>Nom complet</label>
      <input name="name" value="{{ old('name') }}" required>
      @error('name')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Adresse électronique</label>
      <input type="email" name="email" value="{{ old('email') }}" required>
      @error('email')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Téléphone</label>
      <input name="telephone" value="{{ old('telephone') }}" placeholder="+221 77 000 00 00" required>
      @error('telephone')<div class="erreur">{{ $message }}</div>@enderror</div>

    {{-- Tout compte peut acheter, y compris celui d'un commerçant : une
         quincaillerie s'approvisionne aussi. Le genre reste donc demandé. --}}
    <div class="champ"><label>Vous achetez en tant que</label>
      <select name="genre" required>
        @foreach(['particulier' => 'Particulier', 'chantier' => 'Chantier',
                  'entreprise' => 'Entreprise'] as $c => $mot)
          <option value="{{ $c }}" @selected(old('genre') === $c)>{{ $mot }}</option>
        @endforeach
      </select></div>

    <div class="champ"><label>Mot de passe</label>
      <input type="password" name="password" required placeholder="8 caractères minimum"
             autocomplete="new-password">
      @error('password')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Confirmation</label>
      <input type="password" name="password_confirmation" required autocomplete="new-password"></div>

    <button class="btn" style="width:100%;justify-content:center">Créer mon compte</button>

    <p style="color:var(--gris);font-size:.84rem;margin-top:12px;text-align:center">
      En créant un compte, vous acceptez les
      <a href="{{ route('conditions') }}">conditions générales</a>.
    </p>
  </form>

  <p style="text-align:center;margin-top:16px;color:var(--gris)">
    Déjà un compte ? <a href="{{ route('connexion') }}">Se connecter</a>
  </p>
</div>
@endsection
