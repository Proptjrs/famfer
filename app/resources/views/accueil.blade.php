@extends('layouts.app')
@section('titre', 'Le fer et la quincaillerie, livrés partout au Sénégal')
@section('contenu')

{{-- Le bandeau d'accueil.

     L'ancien empilait un dégradé orange, un titre blanc et une boîte
     translucide : du blanc sur de l'orange plafonne à 2,2:1, donc illisible
     pour une part réelle des visiteurs, et la boîte translucide ne portait que
     ce que le titre disait déjà.

     Celui-ci dit trois choses, dans l'ordre où elles décident d'un achat :
     ce qu'on trouve, combien coûte la livraison, et pourquoi on peut payer sans
     risque. La troisième est le vrai argument de FamFer, et elle n'était nulle
     part sur la page d'accueil. --}}
<section class="carte" style="padding:var(--s10) var(--s8);margin-bottom:var(--s6);
         background:linear-gradient(140deg, var(--surface) 0%, var(--brand-soft) 100%);
         border-color:var(--brand-line)">
  <div style="display:grid;gap:var(--s8);align-items:center;
              grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr))">
    <div class="pile">
      <span class="sur-titre" style="color:var(--brand-strong)">
        Place de marché nationale
      </span>
      <h1 style="font-size:clamp(1.75rem, 4vw, 2.5rem);line-height:1.1">
        Le fer, les tôles et la quincaillerie, livrés chez vous.
      </h1>
      <p style="font-size:var(--t-md);color:var(--ink-2);max-width:52ch">
        Comparez le même article chez plusieurs quincailliers du pays, commandez
        sans compte, et ne payez qu'en recevant le colis.
      </p>
      <div class="rang" style="margin-top:var(--s2)">
        <a href="{{ route('recherche') }}" class="btn btn-lg">
          Parcourir le catalogue
          @include('partials.symbole', ['nom' => 'fleche-droite', 'taille' => 17])
        </a>
        <a href="{{ route('vendeur.ouvrir') }}" class="btn btn-lg btn-clair">
          Vendre sur FamFer
        </a>
      </div>
      <p class="petit secondaire" style="margin-top:var(--s1)">
        <span class="chiffre" style="font-weight:700;color:var(--ink)">{{ number_format($nbProduits, 0, ',', ' ') }}</span> produits ·
        <span class="chiffre" style="font-weight:700;color:var(--ink)">{{ $nbBoutiques }}</span> boutiques ·
        livraison partout au Sénégal
      </p>
    </div>

    {{-- Les trois garanties. Elles remplacent la boîte translucide, qui
         répétait le seuil de livraison déjà écrit à gauche. --}}
    <div class="pile">
      @foreach([
        ['argent', 'Payez à la réception',
         'En espèces, au livreur, après avoir vu le colis. Rien n\'est prélevé à la commande.'],
        ['cadenas', 'Un code prouve la livraison',
         'Six chiffres, connus de vous seul. Le vendeur ne clôt la vente qu\'en les recevant.'],
        ['camion', 'Livraison offerte dès 50 000 F',
         'Sinon à partir de 1 500 F sur Dakar, selon la région.'],
      ] as [$icone, $titre, $texte])
        <div class="rang-serre" style="align-items:flex-start;gap:var(--s3);
                    padding:var(--s3) var(--s4);background:var(--surface);
                    border:1px solid var(--line);border-radius:var(--r-sm)">
          <span style="color:var(--brand-strong);flex:none;margin-top:.125rem">
            @include('partials.symbole', ['nom' => $icone, 'taille' => 20])
          </span>
          <div>
            <div style="font-weight:700">{{ $titre }}</div>
            <div class="petit secondaire">{{ $texte }}</div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- Les rayons, pour ceux qui parcourent au lieu de chercher. --}}
<section class="bloc">
  <div class="bloc-tete">
    <h2>Nos rayons</h2>
    <span class="sous">{{ $rayons->count() }} familles de produits</span>
  </div>
  <div class="bloc-corps">
    <div class="grille g4">
      @foreach($rayons as $rayon)
        <a href="{{ route('rayon', $rayon) }}" class="carte carte-lien marque pile-sm"
           style="align-items:center;text-align:center;padding:var(--s5) var(--s3)">
          <span style="color:var(--brand-strong);display:grid;place-items:center;
                       width:2.75rem;height:2.75rem;border-radius:var(--r-rond);
                       background:var(--brand-soft);margin-bottom:var(--s1)">
            <span style="transform:scale(1.5);display:block">
              @include('partials.icone', ['icone' => $rayon->icone])
            </span>
          </span>
          <span style="font-weight:650;font-size:var(--t-xs)">{{ $rayon->nom }}</span>
          <span class="mini secondaire chiffre">{{ $rayon->produits_count }} produits</span>
        </a>
      @endforeach
    </div>
  </div>
</section>

@if($promotions->isNotEmpty())
  <section class="bloc">
    <div class="bloc-tete">
      <h2>Promotions</h2>
      <span class="sous">les plus fortes remises du moment</span>
      <a href="{{ route('recherche', ['tri' => 'remise']) }}"
         class="btn btn-sm btn-clair pousse">Tout voir</a>
    </div>
    <div class="bloc-corps">
      <div class="grille g4">
        @foreach($promotions as $p)
          @include('partials.carte', ['p' => $p])
        @endforeach
      </div>
    </div>
  </section>
@endif

@if($populaires->isNotEmpty())
  <section class="bloc">
    <div class="bloc-tete">
      <h2>Les plus vendus</h2>
      <span class="sous">ce que les chantiers commandent le plus</span>
      <a href="{{ route('recherche', ['tri' => 'ventes']) }}"
         class="btn btn-sm btn-clair pousse">Tout voir</a>
    </div>
    <div class="bloc-corps">
      <div class="grille g4">
        @foreach($populaires as $p)
          @include('partials.carte', ['p' => $p])
        @endforeach
      </div>
    </div>
  </section>
@endif

{{-- Comment ça marche. Un acheteur qui n'a jamais commandé en ligne au Sénégal
     a besoin de savoir à quoi il s'engage avant de remplir un panier. --}}
<section class="bloc">
  <div class="bloc-tete">
    <h2>Comment ça marche</h2>
    <span class="sous">de la recherche à la remise du colis</span>
  </div>
  <div class="bloc-corps">
    <ol class="grille g4" style="list-style:none;counter-reset:e">
      @foreach([
        ['Vous comparez', 'Le même article chez plusieurs quincailliers, avec les prix, les notes et le stock réel.'],
        ['Vous commandez', 'Sans compte jusqu\'au dernier écran. Rien n\'est prélevé.'],
        ['Le vendeur prépare', 'Il est prévenu aussitôt et vous suivez chaque étape par courriel.'],
        ['Vous payez à la porte', 'En espèces, après avoir vu le colis. Vous donnez alors votre code de remise.'],
      ] as $i => [$titre, $texte])
        <li class="pile-sm">
          <span class="chiffre" style="color:var(--brand-strong);font-weight:700;
                       font-size:var(--t-xs)">0{{ $i + 1 }}</span>
          <span style="font-weight:700">{{ $titre }}</span>
          <span class="petit secondaire">{{ $texte }}</span>
        </li>
      @endforeach
    </ol>
  </div>
  <div class="bloc-pied">
    Vous pouvez refuser le colis à la porte : vous ne devez alors rien, et la
    marchandise repart chez le vendeur.
  </div>
</section>

@endsection
