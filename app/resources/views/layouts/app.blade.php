<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('titre', 'Le fer et la quincaillerie, livrés partout au Sénégal') · FamFer</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ══ Palette ═══════════════════════════════════════════════════════════════
   Fond clair et orange vif, comme les places de marché grand public : la
   marchandise doit ressortir, pas l'habillage. */
:root{
  --orange:#F68B1E; --orange-fonce:#D9740C; --orange-pale:#FEF3E5;
  --sombre:#282828; --gris-fonce:#565959; --gris:#7E859B; --gris-pale:#EDEDED;
  --fond:#F1F1F2; --blanc:#fff; --bord:#E4E4E4;
  --vert:#178A46; --vert-pale:#E7F5EC;
  --rouge:#C0392B; --rouge-pale:#FDEDEC;
  --bleu:#0B6BA8;
  --r:4px; --ombre:0 1px 3px rgba(0,0,0,.09);
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Inter,-apple-system,'Segoe UI',Roboto,sans-serif;background:var(--fond);
     color:var(--sombre);font-size:14px;line-height:1.5}
a{color:inherit;text-decoration:none}
a:hover{text-decoration:underline}
img,svg{display:block;max-width:100%}
h1{font-size:1.6rem;font-weight:700;margin-bottom:4px}
h2{font-size:1.15rem;font-weight:700}
h3{font-size:1rem;font-weight:600}
input,select,textarea,button{font:inherit;color:inherit}
input,select,textarea{width:100%;padding:10px 12px;border:1px solid #B7B7B7;border-radius:var(--r);
                      background:var(--blanc)}
input:focus,select:focus,textarea:focus{outline:2px solid var(--orange);border-color:var(--orange)}
label{display:block;font-weight:600;font-size:.86rem;margin-bottom:5px}
.champ{margin-bottom:14px}
.erreur{color:var(--rouge);font-size:.82rem;margin-top:4px}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;
     background:var(--orange);color:#fff;border:0;border-radius:var(--r);
     padding:11px 20px;font-weight:600;cursor:pointer;text-align:center}
.btn:hover{background:var(--orange-fonce);text-decoration:none;color:#fff}
.btn:disabled{background:#C9C9C9;cursor:not-allowed}
.btn-sm{padding:7px 13px;font-size:.85rem}
.btn-clair{background:var(--blanc);color:var(--sombre);border:1px solid #B7B7B7}
.btn-clair:hover{background:var(--gris-pale);color:var(--sombre)}
.btn-vert{background:var(--vert)}.btn-vert:hover{background:#116B36}
.btn-rouge{background:var(--rouge)}.btn-rouge:hover{background:#9C2B1F}

.etiq{display:inline-block;padding:2px 8px;border-radius:3px;font-size:.74rem;font-weight:700}
.etiq-orange{background:var(--orange);color:#fff}
.etiq-vert{background:var(--vert-pale);color:var(--vert)}
.etiq-rouge{background:var(--rouge-pale);color:var(--rouge)}
.etiq-gris{background:var(--gris-pale);color:var(--gris-fonce)}
.etiq-officielle{background:var(--bleu);color:#fff}

/* ══ Bandeau de service ═══════════════════════════════════════════════════ */
.bandeau{background:var(--gris-pale);font-size:.78rem;color:var(--gris-fonce)}
.bandeau-in{max-width:1280px;margin:0 auto;padding:5px 16px;display:flex;gap:16px;
            align-items:center;flex-wrap:wrap}
.bandeau .vendre{color:var(--orange-fonce);font-weight:700}

/* ══ En-tête ══════════════════════════════════════════════════════════════ */
.tete{background:var(--blanc);box-shadow:var(--ombre);position:sticky;top:0;z-index:50}
.tete-in{max-width:1280px;margin:0 auto;padding:12px 16px;display:flex;gap:20px;
         align-items:center;flex-wrap:wrap}
.marque{font-weight:800;font-size:1.55rem;letter-spacing:-.5px;white-space:nowrap}
.marque span{color:var(--orange)}
.quete{display:flex;flex:1 1 320px;min-width:0;max-width:640px}
.quete input{border-radius:var(--r) 0 0 var(--r);border-right:0}
.quete button{border:0;background:var(--orange);color:#fff;padding:0 22px;
              border-radius:0 var(--r) var(--r) 0;cursor:pointer;font-weight:600}
.quete button:hover{background:var(--orange-fonce)}
.tete nav{display:flex;gap:6px;align-items:center;margin-left:auto}
.lien-tete{display:flex;align-items:center;gap:7px;padding:8px 11px;border-radius:var(--r);
           font-weight:600;font-size:.88rem;white-space:nowrap}
.lien-tete:hover{background:var(--gris-pale);text-decoration:none}
.pastille{background:var(--orange);color:#fff;border-radius:20px;padding:1px 7px;
          font-size:.72rem;font-weight:700}

/* Le menu du compte : un « details » natif, que le clavier ouvre sans script. */
.compte{position:relative}
.compte>summary{list-style:none;cursor:pointer}
.compte>summary::-webkit-details-marker{display:none}
.compte[open]>summary{background:var(--gris-pale)}
.tiroir{position:absolute;right:0;top:calc(100% + 6px);z-index:60;background:var(--blanc);
        border:1px solid var(--bord);border-radius:var(--r);box-shadow:0 8px 24px rgba(0,0,0,.16);
        padding:12px;min-width:250px}
.tiroir .btn{width:100%;margin-bottom:10px}
.tete nav .tiroir a{display:block;padding:8px 10px;border-radius:var(--r);font-weight:500;
                    font-size:.9rem;color:var(--sombre)}
.tete nav .tiroir a:hover{background:var(--fond);text-decoration:none}
.tiroir hr{border:0;border-top:1px solid var(--bord);margin:8px 0}
.tiroir .titre{color:var(--gris);font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;
               padding:4px 10px;font-weight:800}
.tiroir form{margin:0}.tiroir form button{width:100%}

/* ══ Rayons ═══════════════════════════════════════════════════════════════ */
.rayons{background:var(--blanc);border-top:1px solid var(--bord);box-shadow:var(--ombre)}
.rayons-in{max-width:1280px;margin:0 auto;padding:0 16px;display:flex;gap:2px;
           overflow-x:auto;scrollbar-width:thin}
.rayons a{display:flex;align-items:center;gap:7px;padding:11px 12px;font-size:.87rem;
          font-weight:600;white-space:nowrap;border-bottom:3px solid transparent}
.rayons a:hover{color:var(--orange-fonce);text-decoration:none}
.rayons a.ici{color:var(--orange-fonce);border-bottom-color:var(--orange)}
.rayons .nb{color:var(--gris);font-weight:500;font-size:.76rem}

/* ══ Blocs ════════════════════════════════════════════════════════════════ */
main{max-width:1280px;margin:0 auto;padding:16px}
.carte{background:var(--blanc);border-radius:var(--r);box-shadow:var(--ombre);padding:16px}
.bloc{background:var(--blanc);border-radius:var(--r);box-shadow:var(--ombre);margin-bottom:16px}
.bloc-tete{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--bord)}
.bloc-tete h2{flex:1}
.bloc-corps{padding:16px}
.grille{display:grid;gap:12px}
.g2{grid-template-columns:repeat(auto-fit,minmax(280px,1fr))}
.g3{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
.g4{grid-template-columns:repeat(auto-fill,minmax(178px,1fr))}
.vide{text-align:center;padding:44px 16px;color:var(--gris)}
.mono{font-variant-numeric:tabular-nums}

table{width:100%;border-collapse:collapse;font-size:.88rem}
th{text-align:left;font-weight:700;font-size:.76rem;text-transform:uppercase;
   color:var(--gris);padding:9px 10px;border-bottom:1px solid var(--bord)}
td{padding:11px 10px;border-bottom:1px solid var(--bord);vertical-align:top}
.large{overflow-x:auto}

.avis{background:var(--vert-pale);color:var(--vert);padding:11px 14px;border-radius:var(--r);
      margin-bottom:14px;font-weight:500}
.avis-err{background:var(--rouge-pale);color:var(--rouge)}

/* ══ Fiche produit en liste ═══════════════════════════════════════════════ */
.produit{background:var(--blanc);border-radius:var(--r);box-shadow:var(--ombre);
         padding:10px;display:flex;flex-direction:column;gap:7px;position:relative}
.produit:hover{box-shadow:0 3px 12px rgba(0,0,0,.16);text-decoration:none}
.produit .image{aspect-ratio:1;background:var(--fond);border-radius:var(--r);
                display:flex;align-items:center;justify-content:center;overflow:hidden}
.produit .nom{font-size:.86rem;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;
              -webkit-box-orient:vertical;overflow:hidden}
.produit .prix{font-size:1.1rem;font-weight:700}
.produit .barre{color:var(--gris);text-decoration:line-through;font-size:.8rem}
.remise{position:absolute;top:8px;right:8px;background:var(--orange-pale);
        color:var(--orange-fonce);font-weight:800;font-size:.74rem;padding:3px 6px;
        border-radius:3px}
.etoiles{color:var(--orange);font-size:.8rem;letter-spacing:.5px}

/* ══ Pied ═════════════════════════════════════════════════════════════════ */
.pied{background:var(--sombre);color:#B6B6B6;margin-top:28px}
.pied-in{max-width:1280px;margin:0 auto;padding:26px 16px;display:grid;gap:22px;
         grid-template-columns:repeat(auto-fit,minmax(190px,1fr));font-size:.86rem}
.pied h3{color:#fff;font-size:.82rem;text-transform:uppercase;letter-spacing:.6px;
         margin-bottom:10px}
.pied a{display:block;padding:3px 0;color:#B6B6B6}
.pied a:hover{color:#fff}
.pied-bas{border-top:1px solid #444;text-align:center;padding:14px 16px;font-size:.8rem}

@media(max-width:760px){
  .tete-in{padding:10px 12px;gap:10px}
  .quete{order:3;flex-basis:100%;max-width:none}
  .tete nav{margin-left:auto}
  .rayons{position:static}
  main{padding:12px}
  .tiroir{right:-6px;min-width:222px}
}
</style>
</head>
<body>

<div class="bandeau">
  <div class="bandeau-in">
    <a href="{{ route('vendeur.ouvrir') }}" class="vendre">★ Vendez sur FamFer</a>
    <a href="{{ route('conditions') }}">Aide et conditions</a>
    <span style="margin-left:auto">Livraison partout au Sénégal · Paiement à la livraison</span>
  </div>
</div>

<header class="tete">
  <div class="tete-in">
    <a href="{{ route('accueil') }}" class="marque">FAM<span>FER</span></a>

    <form method="GET" action="{{ route('recherche') }}" class="quete" role="search">
      <input name="q" value="{{ request('q') }}" aria-label="Chercher"
             placeholder="Cherchez un produit, une marque ou une catégorie">
      <button>Rechercher</button>
    </form>

    <nav>
      <details class="compte">
        <summary class="lien-tete">
          <svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor"
               stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
            <circle cx="10" cy="6.5" r="3.2"/><path d="M3.8 17c.6-3.4 3.2-5.2 6.2-5.2s5.6 1.8 6.2 5.2"/>
          </svg>
          @auth {{ Str::of(auth()->user()->name)->explode(' ')->first() }} @else Se connecter @endauth
          <svg viewBox="0 0 20 20" width="11" height="11" fill="none" stroke="currentColor"
               stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7.5l6 5 6-5"/></svg>
        </summary>
        <div class="tiroir">
          @guest
            <a href="{{ route('connexion') }}" class="btn">Se connecter</a>
            <p style="text-align:center;color:var(--gris);font-size:.84rem;margin-bottom:8px">
              Pas de compte ? <a href="{{ route('inscription') }}"
                 style="display:inline;padding:0;color:var(--bleu);font-weight:600">En créer un</a>
            </p>
            <hr>
            <a href="{{ route('vendeur.ouvrir') }}">★ Vendez sur FamFer</a>
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
                  <span class="etiq etiq-gris">en attente</span>
                @endif
              </a>
              <a href="{{ route('vendeur.produits') }}">Mes produits</a>
              <a href="{{ route('vendeur.commandes') }}">Mes ventes</a>
              <a href="{{ route('vendeur.commissions') }}">Ma commission</a>
              <a href="{{ route('vendeur.boutique') }}">Ma vitrine</a>
            @else
              <hr><a href="{{ route('vendeur.ouvrir') }}">★ Vendez sur FamFer</a>
            @endif
            @if(auth()->user()->estAdmin())
              <hr><div class="titre">Plateforme</div>
              <a href="{{ route('admin.tableau') }}">Tableau de bord</a>
              <a href="{{ route('admin.boutiques') }}">Les boutiques</a>
              <a href="{{ route('admin.commandes') }}">Les commandes</a>
              <a href="{{ route('admin.revenus') }}">Les revenus</a>
            @endif
            <hr>
            <form method="POST" action="{{ route('deconnexion') }}">
              @csrf <button class="btn btn-clair">Se déconnecter</button>
            </form>
          @endguest
        </div>
      </details>

      <a href="{{ route('panier') }}" class="lien-tete">
        <svg viewBox="0 0 20 20" width="19" height="19" fill="none" stroke="currentColor"
             stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M2 3h2.2l2 10.5h9.4"/><path d="M5.4 6h12l-1.4 5.5H6.4"/>
          <circle cx="7.5" cy="16.8" r="1.2"/><circle cx="15" cy="16.8" r="1.2"/>
        </svg>
        Panier
        @if($n = array_sum(session('panier', [])))<span class="pastille">{{ $n }}</span>@endif
      </a>
    </nav>
  </div>

  <nav class="rayons" aria-label="Rayons">
    <div class="rayons-in">
      @foreach($rayonsDuMenu ?? [] as $rayon)
        <a href="{{ route('rayon', $rayon) }}"
           class="{{ isset($categorie) && $categorie && $categorie->id === $rayon->id ? 'ici' : '' }}">
          @include('partials.icone', ['icone' => $rayon->icone])
          {{ $rayon->nom }}<span class="nb">{{ $rayon->produits_count }}</span>
        </a>
      @endforeach
    </div>
  </nav>
</header>

<main>
  @if(session('ok'))<div class="avis">{{ session('ok') }}</div>@endif
  @if(session('erreur'))<div class="avis avis-err">{{ session('erreur') }}</div>@endif
  @yield('contenu')
</main>

<footer class="pied">
  <div class="pied-in">
    <div>
      <h3>FamFer</h3>
      <a href="{{ route('accueil') }}">Le catalogue</a>
      <a href="{{ route('conditions') }}">Conditions générales</a>
      <a href="{{ route('credits') }}">Crédits des images</a>
      <a href="{{ route('vendeur.ouvrir') }}">Vendez sur FamFer</a>
    </div>
    <div>
      <h3>Mon compte</h3>
      @guest
        <a href="{{ route('connexion') }}">Se connecter</a>
        <a href="{{ route('inscription') }}">Créer un compte</a>
      @else
        <a href="{{ route('mes-commandes') }}">Mes commandes</a>
        <a href="{{ route('adresses') }}">Mes adresses</a>
      @endguest
    </div>
    <div>
      <h3>Livraison</h3>
      <span>Partout au Sénégal, à partir de 1 500 F</span><br>
      <span style="color:var(--orange)">Offerte dès 50 000 F d'achat</span>
    </div>
    <div>
      <h3>Paiement</h3>
      <span>À la livraison, en espèces</span><br>
      <span>Wave et Orange Money</span>
    </div>
  </div>
  <div class="pied-bas">
    FamFer — place de marché du fer et de la quincaillerie · Dakar, Sénégal
  </div>
</footer>

</body>
</html>
