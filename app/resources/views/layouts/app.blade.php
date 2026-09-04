<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="description" content="@yield('description', 'FamFer réunit les quincailliers du Sénégal : fer à béton, tôles, outillage et plomberie, livrés partout, payés à la livraison.')">
<title>@yield('titre', 'Le fer et la quincaillerie, livrés partout au Sénégal') · FamFer</title>

{{-- Le thème est appliqué avant le premier rendu. Sans cela, un visiteur en
     mode sombre verrait la page blanche pendant une fraction de seconde à
     chaque navigation, ce qui est plus fatigant que de n'avoir aucun thème. --}}
<script>
  try {
    var t = localStorage.getItem('famfer-theme');
    if (t === 'clair' || t === 'sombre') document.documentElement.dataset.theme = t;
  } catch (e) { /* navigation privée : on garde le thème du système */ }
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&family=IBM+Plex+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="{{ asset('css/famfer.css') }}?v={{ @filemtime(public_path('css/famfer.css')) ?: 1 }}">
@stack('styles')
</head>
<body>

{{-- Premier élément atteint au clavier. Sans lui, un lecteur d'écran traverse
     l'en-tête et les quatorze rayons avant le contenu, sur chaque page. --}}
<a href="#contenu" class="evitement">Aller au contenu</a>

<div class="bandeau">
  <div class="large">
    <a href="{{ route('vendeur.ouvrir') }}" class="vendre">Vendez sur FamFer</a>
    <a href="{{ route('conditions') }}">Aide et conditions</a>
    <span class="pousse">Livraison partout au Sénégal · Paiement à la livraison</span>
  </div>
</div>

<header class="tete">
  <div class="large tete-in">
    <a href="{{ route('accueil') }}" class="marque" aria-label="FamFer, accueil">
      <span class="sigle" aria-hidden="true">FF</span>
      FAM<em>FER</em>
    </a>

    <form method="GET" action="{{ route('recherche') }}" class="quete" role="search">
      <label for="q" class="visuellement-cache">Chercher un produit</label>
      <input id="q" name="q" type="search" value="{{ request('q') }}"
             placeholder="Fer à béton, tôle, cadenas, ciment…" autocomplete="off">
      <button type="submit">
        @include('partials.symbole', ['nom' => 'loupe', 'taille' => 17])
        <span>Rechercher</span>
      </button>
    </form>

    <nav class="principale" aria-label="Compte et panier">
      <button type="button" class="lien-tete" data-theme-bascule
              aria-label="Changer de thème" title="Changer de thème">
        @include('partials.symbole', ['nom' => 'theme', 'taille' => 17])
      </button>

      <details class="compte">
        <summary class="lien-tete">
          @include('partials.symbole', ['nom' => 'personne', 'taille' => 18])
          <span>@auth{{ Str::of(auth()->user()->name)->explode(' ')->first() }}@else Se connecter @endauth</span>
          <span class="chevron">@include('partials.symbole', ['nom' => 'chevron', 'taille' => 11])</span>
        </summary>
        <div class="tiroir">
          @guest
            <a href="{{ route('connexion') }}" class="btn">Se connecter</a>
            <p class="petit secondaire" style="text-align:center;padding:var(--s3) 0">
              Pas encore de compte ?
              <a href="{{ route('inscription') }}" class="lien">En créer un</a>
            </p>
            <hr>
            <a href="{{ route('vendeur.ouvrir') }}">Vendez sur FamFer</a>
            <a href="{{ route('conditions') }}">Aide et conditions</a>
          @else
            <div class="titre">Mon compte</div>
            <a href="{{ route('compte') }}">Mes informations</a>
            <a href="{{ route('mes-commandes') }}">Mes commandes</a>
            <a href="{{ route('adresses') }}">Mes adresses</a>

            @if(auth()->user()->boutique)
              <hr><div class="titre">Ma boutique</div>
              <a href="{{ route('vendeur.tableau') }}">
                Tableau de bord
                @if(auth()->user()->boutique->statut === 'en_attente')
                  <span class="jeton jeton-alerte">en attente</span>
                @elseif(auth()->user()->boutique->statut === 'suspendue')
                  <span class="jeton jeton-grave">suspendue</span>
                @endif
              </a>
              <a href="{{ route('vendeur.produits') }}">Mes produits</a>
              <a href="{{ route('vendeur.commandes') }}">Mes ventes</a>
              <a href="{{ route('vendeur.commissions') }}">Ma commission</a>
              <a href="{{ route('vendeur.boutique') }}">Ma vitrine</a>
            @else
              <hr>
              <a href="{{ route('vendeur.ouvrir') }}">Vendez sur FamFer</a>
            @endif

            @if(auth()->user()->estAdmin())
              <hr><div class="titre">Plateforme</div>
              <a href="{{ route('admin.tableau') }}">Tableau de bord</a>
              <a href="{{ route('admin.boutiques') }}">Les boutiques</a>
              <a href="{{ route('admin.commandes') }}">Les commandes</a>
              <a href="{{ route('admin.revenus') }}">Les revenus</a>
              <a href="{{ route('admin.litiges') }}">
                Les litiges
                @if($litigesOuverts ?? 0)
                  <span class="compteur">{{ $litigesOuverts }}</span>
                @endif
              </a>
            @endif

            <hr>
            <form method="POST" action="{{ route('deconnexion') }}">
              @csrf
              <button type="submit" class="btn btn-clair btn-bloc">Se déconnecter</button>
            </form>
          @endguest
        </div>
      </details>

      <a href="{{ route('panier') }}" class="lien-tete">
        @include('partials.symbole', ['nom' => 'panier', 'taille' => 19])
        <span>Panier</span>
        @if($n = array_sum(session('panier', [])))
          <span class="compteur">{{ $n }}</span>
          <span class="visuellement-cache">{{ $n }} article(s)</span>
        @endif
      </a>
    </nav>
  </div>

  <nav class="rayons" aria-label="Rayons du catalogue">
    <div class="large rayons-in">
      @foreach($rayonsDuMenu ?? [] as $rayon)
        @php $ici = isset($categorie) && $categorie && $categorie->id === $rayon->id @endphp
        <a href="{{ route('rayon', $rayon) }}" class="{{ $ici ? 'ici' : '' }}"
           @if($ici) aria-current="page" @endif>
          @include('partials.icone', ['icone' => $rayon->icone])
          {{ $rayon->nom }}<span class="nb">{{ $rayon->produits_count }}</span>
        </a>
      @endforeach
    </div>
  </nav>
</header>

<main id="contenu" class="large">
  {{-- Les messages sont annoncés aux outils d'assistance : une confirmation
       qu'on ne voit pas et qu'on n'entend pas n'a servi à personne. --}}
  @if(session('ok'))
    <div class="message message-ok" role="status" style="margin-bottom:var(--s5)">
      @include('partials.symbole', ['nom' => 'coche', 'taille' => 18])
      <div>{{ session('ok') }}</div>
    </div>
  @endif
  @if(session('erreur'))
    <div class="message message-grave" role="alert" style="margin-bottom:var(--s5)">
      @include('partials.symbole', ['nom' => 'alerte', 'taille' => 18])
      <div>{{ session('erreur') }}</div>
    </div>
  @endif
  @if($errors->any() && ! $errors->has('_ignorer'))
    <div class="message message-grave" role="alert" style="margin-bottom:var(--s5)">
      @include('partials.symbole', ['nom' => 'alerte', 'taille' => 18])
      <div>
        <strong>{{ $errors->count() }} champ(s) à corriger.</strong>
        <ul style="margin:var(--s1) 0 0;padding-left:1.1em">
          @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
      </div>
    </div>
  @endif

  @yield('contenu')
</main>

<footer class="pied">
  <div class="large">
    <div class="pied-grille">
      <div>
        <h3>FamFer</h3>
        <a href="{{ route('accueil') }}">Le catalogue</a>
        <a href="{{ route('vendeur.ouvrir') }}">Vendez sur FamFer</a>
        <a href="{{ route('conditions') }}">Conditions générales</a>
        <a href="{{ route('credits') }}">Crédits des images</a>
      </div>
      <div>
        <h3>Mon compte</h3>
        @guest
          <a href="{{ route('connexion') }}">Se connecter</a>
          <a href="{{ route('inscription') }}">Créer un compte</a>
        @else
          <a href="{{ route('mes-commandes') }}">Mes commandes</a>
          <a href="{{ route('adresses') }}">Mes adresses</a>
          <a href="{{ route('compte') }}">Mes informations</a>
        @endguest
      </div>
      <div>
        <h3>Livraison</h3>
        <p>Partout au Sénégal, à partir de 1 500 F selon la région.</p>
        <p class="repere">Offerte dès 50 000 F d'achat.</p>
      </div>
      <div>
        <h3>Paiement</h3>
        {{-- Le pied annonçait « Wave et Orange Money ». L'application ne les
             gère pas : c'était une promesse que le logiciel ne tenait pas. --}}
        <p>En espèces, au livreur, à la réception — après avoir vu le colis.</p>
        <p>Un code de remise à six chiffres atteste la livraison.</p>
      </div>
    </div>
    <div class="pied-bas">
      <span>FamFer — place de marché du fer et de la quincaillerie · Dakar, Sénégal</span>
      <span>{{ now()->year }}</span>
    </div>
  </div>
</footer>

<script>
  /* La bascule de thème. Trois états et non deux : « système » est le défaut,
     et un visiteur qui n'a jamais choisi doit suivre les réglages de sa
     machine plutôt qu'une préférence qu'on lui aurait inventée. */
  (function () {
    var bouton = document.querySelector('[data-theme-bascule]');
    if (!bouton) return;

    bouton.addEventListener('click', function () {
      var racine = document.documentElement;
      var sombreSysteme = matchMedia('(prefers-color-scheme: dark)').matches;
      var actuel = racine.dataset.theme || (sombreSysteme ? 'sombre' : 'clair');
      var suivant = actuel === 'sombre' ? 'clair' : 'sombre';

      racine.dataset.theme = suivant;
      try { localStorage.setItem('famfer-theme', suivant); } catch (e) {}
      bouton.setAttribute('aria-label',
        suivant === 'sombre' ? 'Passer au thème clair' : 'Passer au thème sombre');
    });
  })();

  /* Le tiroir du compte se referme au clic extérieur et sur « Échap ». Sans
     cela il restait ouvert derrière la page, et masquait le contenu. */
  (function () {
    var tiroir = document.querySelector('details.compte');
    if (!tiroir) return;

    document.addEventListener('click', function (e) {
      if (tiroir.open && !tiroir.contains(e.target)) tiroir.open = false;
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && tiroir.open) {
        tiroir.open = false;
        tiroir.querySelector('summary').focus();
      }
    });
  })();
</script>
@stack('scripts')

</body>
</html>
