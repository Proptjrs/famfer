@extends('layouts.app')
@section('titre', 'Mon panier')
@section('contenu')

@include('partials.entete', [
  'titre' => 'Mon panier',
  'sous' => $contenu->isEmpty() ? null
    : $contenu->sum('quantite') . ' article(s) chez '
      . $contenu->pluck('produit.boutique_id')->unique()->count() . ' vendeur(s)',
  'fil' => [
    ['libelle' => 'Accueil', 'url' => route('accueil')],
    ['libelle' => 'Mon panier'],
  ],
])

@if($contenu->isEmpty())
  <div class="bloc">
    @include('partials.vide', [
      'icone' => 'panier',
      'titre' => 'Votre panier est vide',
      'texte' => 'Parcourez les rayons ou cherchez directement un article : fer à béton, tôle, ciment, outillage.',
      'action' => '<a href="' . route('accueil') . '" class="btn">Voir le catalogue</a>',
    ])
  </div>
@else
  <div class="deux-colonnes">

    <div class="pile">
      @foreach($contenu as $ligne)
        @php $p = $ligne['produit']; @endphp

        <div class="carte" style="display:flex;gap:var(--s4);flex-wrap:wrap;
             {{ $ligne['ajuste'] ? 'border-color:var(--grave-line)' : '' }}">

          <a href="{{ route('produit', $p) }}"
             style="flex:0 0 5.5rem;height:5.5rem;background:var(--surface-2);
                    border-radius:var(--r-sm);display:grid;place-items:center;overflow:hidden">
            @include('partials.image', ['p' => $p, 'taille' => 70])
          </a>

          <div style="flex:1 1 14rem;min-width:0" class="pile-sm">
            <div>
              <a href="{{ route('produit', $p) }}" style="font-weight:650">{{ $p->nom }}</a>
              <div class="petit secondaire">
                Vendu par
                <a href="{{ route('boutique', $p->boutique) }}" class="lien">
                  {{ $p->boutique->nom }}</a>
                · {{ $p->boutique->ville }}
              </div>
            </div>

            @if($ligne['ajuste'])
              {{-- On signale plutôt qu'on ne corrige : le client doit décider
                   lui-même, sinon il recevrait moins que ce qu'il croit avoir
                   commandé sans jamais l'avoir accepté. --}}
              <div class="message message-grave petit">
                @include('partials.symbole', ['nom' => 'alerte', 'taille' => 15])
                <div>Il ne reste que <strong>{{ $ligne['disponible'] }}</strong>
                en stock. Réduisez la quantité pour continuer.</div>
              </div>
            @elseif($p->stock <= 5)
              <span class="jeton jeton-alerte">Plus que {{ $p->stock }} en stock</span>
            @endif

            <div class="rang-sm">
              <form method="POST" action="{{ route('panier.modifier', $p) }}"
                    class="rang-serre" style="gap:var(--s2)">
                @csrf @method('PUT')
                <label for="q{{ $p->id }}" class="visuellement-cache">
                  Quantité pour {{ $p->nom }}</label>
                <input id="q{{ $p->id }}" name="quantite" type="number"
                       value="{{ $ligne['quantite'] }}" min="1" max="{{ $p->stock }}"
                       class="chiffre" style="width:5rem" onchange="this.form.submit()">
                <noscript><button class="btn btn-sm btn-clair">Mettre à jour</button></noscript>
              </form>

              <form method="POST" action="{{ route('panier.retirer', $p) }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-fantome">
                  @include('partials.symbole', ['nom' => 'croix', 'taille' => 14])
                  Retirer
                </button>
              </form>
            </div>
          </div>

          <div style="text-align:right;margin-left:auto">
            <div class="chiffre" style="font-weight:700;font-size:var(--t-lg)">
              {{ number_format($ligne['montant'], 0, ',', ' ') }} F
            </div>
            <div class="mini secondaire chiffre">
              {{ number_format($p->prix, 0, ',', ' ') }} F l'unité
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <div class="colonne-fixe">
      <div class="bloc">
        <div class="bloc-tete"><h2>Récapitulatif</h2></div>
        <div class="bloc-corps pile">

          <div class="rang-serre">
            <span>Sous-total</span>
            <strong class="chiffre pousse">{{ number_format($sousTotal, 0, ',', ' ') }} F</strong>
          </div>

          @if($resteAvantGratuite === null)
            <div class="message message-ok petit">
              @include('partials.symbole', ['nom' => 'camion', 'taille' => 16])
              <div><strong>Livraison offerte</strong> — vous avez dépassé le seuil
              de 50 000 F.</div>
            </div>
          @else
            {{-- Le seuil affiché n'est pas un artifice : il pousse à remplir le
                 panier, et le client décide en connaissance de cause. La jauge
                 dit d'un coup d'œil ce qu'il reste à parcourir. --}}
            <div class="pile-sm">
              <div class="jauge" role="img"
                   aria-label="Progression vers la livraison offerte">
                <span style="width:{{ min(100, round($sousTotal * 100 / 50000)) }}%"></span>
              </div>
              <p class="petit secondaire">
                Plus que
                <strong class="chiffre" style="color:var(--brand-strong)">{{ number_format($resteAvantGratuite, 0, ',', ' ') }} F</strong>
                pour la livraison offerte.
              </p>
            </div>
          @endif

          <p class="petit secondaire">
            Les frais de livraison dépendent de votre région ; ils s'affichent à
            l'étape suivante, avant toute validation.
          </p>

          @if($contenu->contains('ajuste', true))
            <button class="btn btn-lg btn-bloc" disabled>Commander</button>
            <p class="erreur" style="justify-content:center">
              @include('partials.symbole', ['nom' => 'alerte', 'taille' => 14])
              Ajustez d'abord les quantités signalées.
            </p>
          @else
            <a href="{{ route('commande') }}" class="btn btn-lg btn-bloc">
              Commander
              @include('partials.symbole', ['nom' => 'fleche-droite', 'taille' => 17])
            </a>
          @endif

          <a href="{{ route('accueil') }}" class="btn btn-clair btn-bloc">
            Continuer mes achats
          </a>
        </div>
        <div class="bloc-pied">
          Rien n'est prélevé maintenant : vous paierez au livreur, en espèces,
          à la réception.
        </div>
      </div>
    </div>
  </div>
@endif

@endsection
