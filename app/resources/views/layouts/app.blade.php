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
.pastille{background:var(--forge);color:#fff;border-radius:20px;padding:1px 8px;
          font-size:.76rem;font-weight:700;margin-left:2px}

/* ══ Barre des catégories ═════════════════════════════════════════════════
   Une place de marché se parcourt par familles autant que par recherche : sans
   cette barre, le catalogue n'était atteignable qu'en tapant le bon mot. */
/* 69 px et non 66 : l'en-tête mesure 66 px de contenu plus les 3 px de sa
   bordure orange. À 66, la barre laisse voir une raie de page en défilant. */
.rayons{background:var(--acier-2);border-bottom:1px solid rgba(255,255,255,.08);
        position:sticky;top:69px;z-index:49}
.rayons-in{max-width:1200px;margin:0 auto;padding:0 22px;display:flex;
           align-items:stretch;gap:4px;overflow-x:auto;scrollbar-width:thin}
.rayons a{color:#D5DBE1;font-size:.88rem;font-weight:500;white-space:nowrap;
          padding:11px 13px;border-bottom:3px solid transparent;line-height:1.2}
.rayons a:hover{color:#fff;background:rgba(255,255,255,.06);text-decoration:none}
.rayons a.ici{color:#fff;border-bottom-color:var(--forge-vif)}
.rayons .nb{color:#8794A1;font-size:.76rem;margin-left:5px}
.rayons a:hover .nb,.rayons a.ici .nb{color:#C3CCD5}
.rayon-tout{font-weight:700 !important;color:#fff !important}

/* La recherche vit dans l'en-tête : on cherche du fer depuis n'importe quelle
   page, pas seulement depuis l'accueil. */
.quete{display:flex;flex:1 1 260px;max-width:420px;min-width:0}
.quete input{flex:1;min-width:0;padding:9px 14px;border:0;font-size:.92rem;
             border-radius:8px 0 0 8px}
.quete button{border:0;background:var(--forge);color:#fff;padding:0 16px;
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
  /* La barre colle sous un en-tête qui a grandi : la laisser à 66 px la
     ferait chevaucher le menu. Sur téléphone, elle défile avec la page. */
  .rayons{position:static}
  .rayons-in{padding:0 16px}
  /* Pas de « order » ici : il faisait passer la recherche AVANT la marque, et
     l'on arrivait sur une page dont on ne voyait pas le nom. */
  .quete{max-width:none;flex-basis:100%}
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

<header class="tete">
  <div class="tete-in">
    <a href="{{ route('accueil') }}" class="marque">Fam<span>Fer</span></a>
    <form method="GET" action="{{ route('accueil') }}" class="quete" role="search">
      <input name="q" value="{{ request('q') }}" aria-label="Chercher un article"
             placeholder="fer 10, tôle bac, cornière 40…">
      <button aria-label="Rechercher">Chercher</button>
    </form>

    <nav>
      @auth
        <a href="{{ route('compte') }}">Mon compte</a>
        @if(auth()->user()->vendeur)<a href="{{ route('vendeur.tableau') }}">Mon commerce</a>
        @else<a href="{{ route('vendeur.demande') }}">Vendre sur FamFer</a>@endif
        @if(auth()->user()->est_admin)<a href="{{ route('admin.tableau') }}">Administration</a>@endif
        @if(auth()->user()->acheteur)
          <a href="{{ route('panier.voir') }}">Panier @if($n = count(session('panier', [])))<span class="pastille">{{ $n }}</span>@endif</a>
          <a href="{{ route('acheteur.commandes') }}">Mes commandes</a>
        @endif
        <form method="POST" action="{{ route('deconnexion') }}" style="display:inline">
          @csrf <button class="btn btn-sm btn-clair">Sortir</button>
        </form>
      @else
        <a href="{{ route('connexion') }}">Se connecter</a>
        <a href="{{ route('inscription') }}" class="btn btn-sm">Créer un compte</a>
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
