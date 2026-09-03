<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('titre', 'Le fer, au juste prix') · FamFer</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ══ La palette ═══════════════════════════════════════════════════════════
   L'acier et la forge. Un gris bleuté profond pour la matière, un orange de
   métal chauffé pour l'action. Le métier se lit avant le texte.            */
:root{
  --nuit:#131A22; --acier:#1F2933; --acier-2:#323F4B; --acier-3:#52606D;
  --forge:#E8590C; --forge-vif:#FD7E14; --forge-pale:#FFF4E6;
  --vert:#099268; --vert-pale:#E6FCF5;
  --rouge:#C92A2A; --rouge-pale:#FFF5F5;
  --ambre:#E67700; --ambre-pale:#FFF9DB;
  --fond:#F4F6F8; --blanc:#fff; --bord:#E1E6EB; --texte:#1F2933; --gris:#78868F;
  --r:14px; --r-sm:9px;
  --ombre:0 1px 2px rgba(19,26,34,.05), 0 6px 20px -6px rgba(19,26,34,.12);
  --ombre-forte:0 2px 4px rgba(19,26,34,.06), 0 18px 40px -12px rgba(19,26,34,.22);
  --titre:'Barlow Condensed', 'Arial Narrow', sans-serif;
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font:15.5px/1.6 'Inter',system-ui,-apple-system,sans-serif;background:var(--fond);
     color:var(--texte);-webkit-font-smoothing:antialiased}
a{color:var(--forge);text-decoration:none}
a:hover{text-decoration:underline}
input,select,textarea,button{font:inherit;max-width:100%}
h1,h2,h3{font-family:var(--titre);letter-spacing:.2px;line-height:1.1}
h1{font-size:2.5rem;font-weight:700;text-transform:uppercase}
h2{font-size:1.5rem;font-weight:700;text-transform:uppercase;margin-bottom:16px}
.sous{color:var(--gris);margin-bottom:28px;max-width:62ch}

/* ══ En-tête ══════════════════════════════════════════════════════════════ */
.tete{background:var(--nuit);color:#fff;position:sticky;top:0;z-index:50;
      border-bottom:3px solid var(--forge)}
.tete-in{max-width:1200px;margin:0 auto;padding:0 22px;min-height:66px;
         display:flex;align-items:center;gap:24px;flex-wrap:wrap}
.marque{font-family:var(--titre);font-weight:700;font-size:1.7rem;color:#fff;
        text-transform:uppercase;letter-spacing:1px;white-space:nowrap;line-height:1}
.marque span{color:var(--forge-vif)}
.tete nav{display:flex;gap:20px;margin-left:auto;align-items:center;flex-wrap:wrap}
.tete nav a{color:#AEB8C2;font-size:.92rem;font-weight:500}
.tete nav a:hover{color:#fff;text-decoration:none}
/* L'espace professionnel se distingue des liens d'achat : un filet à gauche,
   et la couleur de la marque. Sans cela, « Mon commerce » se lit comme une
   rubrique de plus au milieu du panier. */
.espace-pro{color:var(--forge-vif) !important;font-weight:600 !important;
            padding-left:16px;border-left:1px solid rgba(255,255,255,.18)}
.espace-pro:hover{color:#fff !important}

.pastille{background:var(--forge);color:#fff;border-radius:20px;padding:1px 8px;
          font-size:.76rem;font-weight:700;margin-left:2px}

/* ══ Bandeau de service ═══════════════════════════════════════════════════
   La ligne la plus haute, réservée à ce qui ne s'achète pas : la porte des
   vendeurs et les conditions. La séparer du menu d'achat évite que « Vendre »
   se lise comme une rubrique du catalogue. */
.bandeau{background:#12181F;color:#8794A1;font-size:.8rem}
.bandeau-in{max-width:1200px;margin:0 auto;padding:6px 22px;display:flex;
            gap:18px;align-items:center;flex-wrap:wrap}
.bandeau a{color:#AEB8C2}
.bandeau a:hover{color:#fff;text-decoration:none}
.bandeau .vendre{color:var(--forge-vif);font-weight:600}
.bandeau .vendre:hover{color:#fff}

/* ══ Menu du compte ═══════════════════════════════════════════════════════
   Un « details » natif plutôt qu'un script : le clavier l'ouvre et le ferme
   sans qu'on ait à réimplémenter quoi que ce soit, et il fonctionne même si le
   JavaScript ne se charge pas. */
.compte{position:relative}
.compte > summary{list-style:none;cursor:pointer;display:flex;align-items:center;
                  gap:7px;color:#AEB8C2;font-size:.92rem;font-weight:500;
                  padding:7px 10px;border-radius:8px;white-space:nowrap}
.compte > summary::-webkit-details-marker{display:none}
.compte > summary:hover,.compte[open] > summary{color:#fff;background:rgba(255,255,255,.08)}
.compte .fleche{transition:transform .15s}
.compte[open] .fleche{transform:rotate(180deg)}
.tiroir{position:absolute;right:0;top:calc(100% + 8px);z-index:60;
        background:var(--blanc);border:1px solid var(--bord);border-radius:var(--r-sm);
        box-shadow:0 12px 32px rgba(16,24,32,.22);padding:12px;min-width:236px}
.tiroir .btn{width:100%;justify-content:center;margin-bottom:10px}
/* « .tete nav a » pose un gris clair, et sa spécificité l'emporte sur une
   règle en « .tiroir a » : les liens du menu sortaient délavés sur fond blanc.
   On rentre dans la même cascade plutôt que de forcer avec « important ». */
.tete nav .tiroir a{display:flex;align-items:center;gap:10px;padding:9px 10px;
          color:var(--nuit);font-size:.92rem;border-radius:8px;font-weight:500}
.tete nav .tiroir a:hover{color:var(--nuit);background:var(--fond);text-decoration:none}
.tete nav .tiroir p a{display:inline;padding:0;color:var(--forge);font-weight:600}
.tiroir hr{border:0;border-top:1px solid var(--bord);margin:8px 0}
.tiroir .titre{color:var(--gris);font-size:.76rem;text-transform:uppercase;
               letter-spacing:.5px;padding:4px 10px;font-weight:700}
.tiroir form{margin:0}
.tiroir form button{width:100%;justify-content:center;margin-top:6px}

/* ══ Barre des catégories ═════════════════════════════════════════════════
   Une place de marché se parcourt par familles autant que par recherche : sans
   cette barre, le catalogue n'était atteignable qu'en tapant le bon mot. */
.rayons{background:var(--acier-2);border-bottom:1px solid rgba(255,255,255,.08);
        position:sticky;top:69px;z-index:49}
.rayons-in{max-width:1200px;margin:0 auto;padding:0 22px;display:flex;
           align-items:stretch;gap:2px;overflow-x:auto;scrollbar-width:thin}
.rayons a{color:#D5DBE1;font-size:.86rem;font-weight:500;white-space:nowrap;
          padding:11px 12px;border-bottom:3px solid transparent;line-height:1.2;
          display:flex;align-items:center;gap:7px}
.rayons a:hover{color:#fff;background:rgba(255,255,255,.06);text-decoration:none}
.rayons a.ici{color:#fff;border-bottom-color:var(--forge-vif)}
.rayons .nb{color:#8794A1;font-size:.74rem}
.rayons a:hover .nb,.rayons a.ici .nb{color:#C3CCD5}
.rayon-tout{font-weight:700 !important;color:#fff !important}

/* La recherche vit dans l'en-tête : on cherche du fer depuis n'importe quelle
   page, pas seulement depuis l'accueil. */
.quete{display:flex;flex:1 1 260px;max-width:520px;min-width:0}
.quete input{flex:1;min-width:0;padding:10px 15px;border:0;font-size:.92rem;
             border-radius:8px 0 0 8px}
.quete button{border:0;background:var(--forge);color:#fff;padding:0 20px;
              border-radius:0 8px 8px 0;cursor:pointer;font-weight:600}
.quete button:hover{background:var(--forge-vif)}

main{max-width:1200px;margin:0 auto;padding:30px 22px 60px}

/* ══ Pied de page ═════════════════════════════════════════════════════════ */
.pied{background:var(--nuit);color:#8794A1;margin-top:40px;
      border-top:3px solid var(--forge)}
.pied-in{max-width:1200px;margin:0 auto;padding:26px 22px;display:flex;
         gap:24px;flex-wrap:wrap;align-items:center;font-size:.88rem}
.pied a{color:#C3CCD5}
.pied a:hover{color:#fff}

/* ══ Blocs ════════════════════════════════════════════════════════════════ */
.carte{background:var(--blanc);border:1px solid var(--bord);border-radius:var(--r);
       padding:22px;box-shadow:var(--ombre)}
.grille{display:grid;gap:18px}
.g2{grid-template-columns:repeat(auto-fit,minmax(290px,1fr))}
.g3{grid-template-columns:repeat(auto-fit,minmax(236px,1fr))}
.g4{grid-template-columns:repeat(auto-fit,minmax(184px,1fr))}

/* La vignette d'un article : le dessin sur un fond d'atelier. */
.vignette{background:linear-gradient(150deg,#EDF1F4,#DDE4EA);border-radius:var(--r-sm);
          display:grid;place-items:center;padding:14px;flex:0 0 auto}
.produit{display:block;color:inherit;transition:transform .16s ease, box-shadow .16s ease}
.produit:hover{text-decoration:none;transform:translateY(-3px);box-shadow:var(--ombre-forte)}

/* ══ Éléments ═════════════════════════════════════════════════════════════ */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;
     background:var(--forge);color:#fff;border:0;border-radius:var(--r-sm);
     padding:11px 19px;font-weight:600;cursor:pointer;font-size:.94rem;
     transition:background .14s ease}
.btn:hover{background:var(--forge-vif);text-decoration:none;color:#fff}
.btn-clair{background:var(--blanc);color:var(--acier);border:1px solid var(--bord)}
.btn-clair:hover{background:var(--fond);color:var(--acier)}
.btn-nuit{background:var(--acier)} .btn-nuit:hover{background:var(--acier-2)}
.btn-vert{background:var(--vert)} .btn-vert:hover{background:#087f5b}
.btn-rouge{background:var(--rouge)} .btn-rouge:hover{background:#a51111}
.btn-sm{padding:7px 13px;font-size:.86rem}

.champ{margin-bottom:16px}
.champ label{display:block;font-weight:600;font-size:.87rem;margin-bottom:6px}
.champ input,.champ select,.champ textarea{width:100%;padding:10px 13px;
     border:1px solid var(--bord);border-radius:var(--r-sm);background:var(--blanc)}
.champ input:focus,.champ select:focus,.champ textarea:focus{
     outline:2px solid var(--forge);outline-offset:-1px;border-color:transparent}
.erreur{color:var(--rouge);font-size:.85rem;margin-top:5px}

.etiq{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.77rem;
      font-weight:700;letter-spacing:.2px}
.etiq-vert{background:var(--vert-pale);color:#087f5b}
.etiq-ambre{background:var(--ambre-pale);color:#a1670a}
.etiq-rouge{background:var(--rouge-pale);color:var(--rouge)}
.etiq-gris{background:#EBEFF3;color:var(--acier-3)}
.etiq-forge{background:var(--forge-pale);color:#b34700}

.avis{padding:14px 18px;border-radius:var(--r-sm);margin-bottom:22px;border-left:4px solid}
.avis-ok{background:var(--vert-pale);border-color:var(--vert)}
.avis-err{background:var(--rouge-pale);border-color:var(--rouge)}

table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:.76rem;text-transform:uppercase;letter-spacing:.7px;
   color:var(--gris);padding:10px;border-bottom:2px solid var(--bord)}
td{padding:12px 10px;border-bottom:1px solid var(--bord);vertical-align:middle}
tr:last-child td{border-bottom:0}
.tableau-large{overflow-x:auto}

.chiffre{font-family:var(--titre);font-size:2.2rem;font-weight:700;line-height:1;
         letter-spacing:.5px}
.chiffre-note{color:var(--gris);font-size:.83rem;margin-top:5px}
.mono{font-variant-numeric:tabular-nums}
.vide{text-align:center;padding:48px 22px;color:var(--gris)}

@media(max-width:640px){
  h1{font-size:1.9rem}
  .tete-in{padding:12px 16px;gap:12px}
  .tete nav{margin-left:0;width:100%;gap:15px}
  /* La barre colle sous un en-tête qui a grandi : la laisser à 69 px la
     ferait chevaucher le menu. Sur téléphone, elle défile avec la page. */
  .rayons{position:static}
  .rayons-in{padding:0 16px}
  /* Pas de « order » ici : il faisait passer la recherche AVANT la marque, et
     l'on arrivait sur une page dont on ne voyait pas le nom. */
  .quete{max-width:none;flex-basis:100%}
  .bandeau-in{padding:6px 16px;gap:14px}
  /* Le tiroir déborderait de l'écran en position absolue : sur téléphone il
     s'ancre au bord droit de la fenêtre plutôt qu'au bouton. */
  .tiroir{right:-8px;min-width:210px}
  main{padding:22px 16px 64px}
}
</style>
</head>
<body>

{{-- Les dégradés des dessins sont définis une seule fois pour toute la page :
     les répéter dans chaque vignette produirait autant d'identifiants en double,
     ce que le navigateur ne sait pas départager. --}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <defs>
    <linearGradient id="g-acier" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#8794A1"/>
      <stop offset="45%" stop-color="#CBD2D9"/>
      <stop offset="100%" stop-color="#6B7784"/>
    </linearGradient>
    <linearGradient id="g-forge" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#F76707"/>
      <stop offset="100%" stop-color="#D9480F"/>
    </linearGradient>
  </defs>
</svg>

{{-- Le bandeau de service : ce qui ne s'achète pas.
     La porte des vendeurs y vit, séparée du menu d'achat — sinon « Vendre »
     se lit comme une rubrique du catalogue. --}}
<div class="bandeau">
  <div class="bandeau-in">
    <a href="{{ route('vendeur.demande') }}" class="vendre">★ Vendez sur FamFer</a>
    <a href="{{ route('conditions') }}">Conditions générales</a>
    <span style="margin-left:auto">Le fer, au juste prix — partout au Sénégal</span>
  </div>
</div>

<header class="tete">
  <div class="tete-in">
    <a href="{{ route('accueil') }}" class="marque">Fam<span>Fer</span></a>

    <form method="GET" action="{{ route('accueil') }}" class="quete" role="search">
      <input name="q" value="{{ request('q') }}" aria-label="Chercher un article"
             placeholder="Cherchez un article, une référence ou une famille">
      <button aria-label="Rechercher">Rechercher</button>
    </form>

    <nav>
      {{-- Le compte tient dans un seul menu déroulant plutôt qu'en cinq liens
           alignés : c'est ce qui laisse la place à la recherche, et ce qui
           permet de nommer les espaces au lieu de les juxtaposer. --}}
      <details class="compte">
        <summary>
          <svg viewBox="0 0 20 20" width="17" height="17" fill="none" stroke="currentColor"
               stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
            <circle cx="10" cy="6.5" r="3.2"/>
            <path d="M3.8 17c.6-3.4 3.2-5.2 6.2-5.2s5.6 1.8 6.2 5.2"/>
          </svg>
          @auth {{ Str::of(auth()->user()->name)->explode(' ')->first() }} @else Se connecter @endauth
          <svg class="fleche" viewBox="0 0 20 20" width="11" height="11" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M4 7.5l6 5 6-5"/>
          </svg>
        </summary>

        <div class="tiroir">
          @guest
            <a href="{{ route('connexion') }}" class="btn">Se connecter</a>
            <p style="color:var(--gris);font-size:.84rem;text-align:center;margin-bottom:10px">
              Pas de compte ? <a href="{{ route('inscription') }}"
                 style="display:inline;padding:0">En créer un</a>
            </p>
            <hr>
            <a href="{{ route('vendeur.demande') }}">★ Vendez sur FamFer</a>
            <a href="{{ route('conditions') }}">Conditions générales</a>
          @else
            <div class="titre">Mon espace</div>
            <a href="{{ route('compte') }}">Mon compte</a>
            @if(auth()->user()->acheteur)
              <a href="{{ route('acheteur.commandes') }}">Mes commandes</a>
            @endif

            @if(auth()->user()->vendeur)
              <hr>
              <div class="titre">Mon commerce</div>
              <a href="{{ route('vendeur.tableau') }}">
                Tableau de bord
                @if(auth()->user()->vendeur->statut === 'en_attente')
                  <span class="pastille" style="background:var(--ambre)">en attente</span>
                @endif
              </a>
              <a href="{{ route('vendeur.offres') }}">Mes offres et mon stock</a>
              <a href="{{ route('vendeur.commandes') }}">Mes ventes</a>
              <a href="{{ route('vendeur.argent') }}">Mon argent</a>
            @else
              <hr>
              <a href="{{ route('vendeur.demande') }}">★ Vendez sur FamFer</a>
            @endif

            @if(auth()->user()->est_admin)
              <hr>
              <div class="titre">Plateforme</div>
              <a href="{{ route('admin.tableau') }}">Administration</a>
            @endif

            <hr>
            <form method="POST" action="{{ route('deconnexion') }}">
              @csrf <button class="btn btn-clair">Sortir</button>
            </form>
          @endguest
        </div>
      </details>

      @auth
        @if(auth()->user()->acheteur)
          <a href="{{ route('panier.voir') }}" style="display:flex;align-items:center;gap:7px">
            <svg viewBox="0 0 20 20" width="17" height="17" fill="none" stroke="currentColor"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M2 3h2.2l2 10.5h9.4"/>
              <path d="M5.4 6h12l-1.4 5.5H6.4"/>
              <circle cx="7.5" cy="16.8" r="1.2"/><circle cx="15" cy="16.8" r="1.2"/>
            </svg>
            Panier
            @if($n = count(session('panier', [])))<span class="pastille">{{ $n }}</span>@endif
          </a>
        @endif
      @endauth
    </nav>
  </div>
</header>

{{-- Les familles viennent d'un « view composer » : aucune page n'a à y penser,
     et la barre ne peut donc pas disparaître d'un écran par oubli. --}}
<nav class="rayons" aria-label="Familles d'articles">
  <div class="rayons-in">
    <a href="{{ route('accueil') }}" class="rayon-tout {{ ! request('famille') && ! request('q') ? 'ici' : '' }}">
      Tout le catalogue
    </a>
    @foreach($famillesDuMenu ?? [] as $f)
      <a href="{{ route('accueil', ['famille' => $f->id]) }}"
         class="{{ (int) request('famille') === $f->id ? 'ici' : '' }}">
        @include('partials.icone-famille', ['famille' => $f->nom])
        {{ $f->nom }}<span class="nb">{{ $f->articles_count }}</span>
      </a>
    @endforeach
  </div>
</nav>

<main>
  @if(session('ok'))<div class="avis avis-ok">{{ session('ok') }}</div>@endif
  @if(session('erreur'))<div class="avis avis-err">{{ session('erreur') }}</div>@endif
  @yield('contenu')
</main>

<footer class="pied">
  <div class="pied-in">
    <span><strong style="color:#fff">FamFer</strong> — le fer, au juste prix</span>
    <a href="{{ route('conditions') }}">Conditions générales</a>
    <a href="{{ route('accueil') }}">Catalogue</a>
    @guest<a href="{{ route('vendeur.demande') }}">Vendre sur FamFer</a>@endguest
    <span style="margin-left:auto;max-width:44ch">
      Votre argent est retenu par FamFer jusqu'à ce que vous confirmiez la
      réception. La commission ne porte que sur la marchandise.
    </span>
  </div>
</footer>

</body>
</html>
