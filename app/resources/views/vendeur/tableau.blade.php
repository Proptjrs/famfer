@extends('layouts.app')
@section('titre', $boutique->nom)
@section('contenu')

@php
  $sousTitre = match ($boutique->statut) {
    'en_attente' => 'Votre boutique attend la validation de l\'administration.',
    'suspendue'  => 'Votre boutique est suspendue : ses produits ne sont pas visibles.',
    default      => 'Ce que vos ventes ont rapporté, et ce qui vous attend aujourd\'hui.',
  };
@endphp

@include('partials.entete', [
  'titre' => $boutique->nom,
  'sous' => $sousTitre,
  'fil' => [
    ['libelle' => 'Accueil', 'url' => route('accueil')],
    ['libelle' => 'Ma boutique'],
  ],
  'actions' => '<a href="' . route('vendeur.produit.nouveau') . '" class="btn">'
    . 'Ajouter un produit</a>'
    . '<a href="' . route('vendeur.commandes') . '" class="btn btn-clair">Mes ventes</a>',
])

<div class="rang" style="margin-bottom:var(--s5)">
  @include('partials.etat-boutique', ['boutique' => $boutique])
  <span class="petit secondaire">
    Commission négociée : {{ rtrim(rtrim(number_format($boutique->tauxPourCent(), 1, ',', ' '), '0'), ',') }} %
    de la marchandise livrée
  </span>
</div>

@if($boutique->statut === 'en_attente')
  <div class="message message-alerte" style="margin-bottom:var(--s5)">
    @include('partials.symbole', ['nom' => 'horloge'])
    <div>
      <strong>En attente de validation.</strong>
      Préparez déjà vos produits : ils apparaîtront au catalogue dès la
      validation, sans que vous ayez à les republier.
    </div>
  </div>
@elseif($boutique->statut === 'suspendue')
  <div class="message message-grave" style="margin-bottom:var(--s5)">
    @include('partials.symbole', ['nom' => 'alerte'])
    <div><strong>Boutique suspendue.</strong> {{ $boutique->motif_suspension }}</div>
  </div>
@endif

{{-- Les quatre chiffres qui décident de la journée. L'ancien tableau en
     alignait sept d'importance égale, ce qui revenait à n'en souligner aucun. --}}
<div class="indicateurs" style="margin-bottom:var(--s6)">
  @include('partials.indicateur', [
    'libelle' => 'Ventes livrées',
    'valeur' => number_format($chiffres['chiffre_affaires'], 0, ',', ' '),
    'unite' => 'F',
    'ecart' => $variation,
    'note' => $chiffres['articles_vendus'] . ' article(s), port non compris',
  ])
  @include('partials.indicateur', [
    'libelle' => 'Ce qui vous reste',
    'valeur' => number_format($compte['net'], 0, ',', ' '),
    'unite' => 'F',
    'note' => 'après ' . number_format($compte['commission'], 0, ',', ' ') . ' F de commission',
    'lien' => route('vendeur.commissions'),
    'ton' => 'bon',
  ])
  @include('partials.indicateur', [
    'libelle' => 'À expédier',
    'valeur' => $chiffres['a_expedier'],
    'note' => $chiffres['a_expedier'] ? 'Chaque jour d\'attente est un client qui doute.'
                                      : 'Rien en attente.',
    'ton' => $chiffres['a_expedier'] ? 'attention' : null,
    'lien' => route('vendeur.commandes'),
  ])
  @include('partials.indicateur', [
    'libelle' => 'Refusées à la porte',
    'valeur' => $chiffres['refusees'],
    'note' => $chiffres['refusees'] ? 'Chaque refus est une tournée payée pour rien.'
                                    : 'Aucun refus enregistré.',
    'ton' => $chiffres['refusees'] ? 'tension' : null,
  ])
</div>

<div class="deux-colonnes">
  <div class="pile-lg">

    <div class="bloc">
      <div class="bloc-tete">
        <h2>Chiffre d'affaires</h2>
        <span class="sous">six derniers mois, marchandise livrée</span>
        <a href="{{ route('vendeur.commissions') }}" class="btn btn-sm btn-clair pousse">
          Le relevé détaillé
        </a>
      </div>
      <div class="bloc-corps">
        @include('partials.graphe-barres', [
          'series' => $ventes->map(fn ($m) => [
            'libelle' => $m['libelle'], 'valeur' => $m['valeur'],
          ])->all(),
          'suffixe' => ' F',
          'titre' => 'Chiffre d\'affaires mensuel de la boutique, six derniers mois',
        ])
      </div>
    </div>

    <div class="bloc">
      <div class="bloc-tete">
        <h2>Commandes à traiter</h2>
        <a href="{{ route('vendeur.commandes') }}" class="btn btn-sm btn-clair pousse">
          Toutes mes ventes
        </a>
      </div>
      <div class="bloc-corps {{ $aTraiter->isEmpty() ? '' : 'serre' }}">
        @forelse($aTraiter as $c)
          <div class="rang" style="padding:var(--s3) var(--s5);border-bottom:1px solid var(--line)">
            <a href="{{ route('vendeur.commandes') }}" class="chiffre" style="font-weight:700">
              {{ $c->reference }}
            </a>
            @include('partials.etat', ['etat' => $c->etat])
            <span class="petit secondaire">
              {{ $c->lignes->count() }} article(s) · {{ $c->created_at->diffForHumans() }}
            </span>
            <span class="chiffre pousse" style="font-weight:700">
              {{ number_format($c->total, 0, ',', ' ') }} F
            </span>
          </div>
        @empty
          @include('partials.vide', [
            'icone' => 'coche',
            'titre' => 'Rien à préparer',
            'texte' => 'Toutes vos commandes sont traitées. Les nouvelles apparaîtront ici, et vous serez prévenu par courriel.',
            'action' => '<a href="' . route('vendeur.produits') . '" class="btn btn-clair btn-sm">Gérer mes produits</a>',
          ])
        @endforelse
      </div>
    </div>
  </div>

  <div class="pile-lg colonne-fixe">

    <div class="bloc">
      <div class="bloc-tete"><h2>Où en sont mes commandes</h2></div>
      <div class="bloc-corps pile">
        @if($etats->isEmpty())
          <p class="petit secondaire">Aucune commande pour l'instant.</p>
        @else
          <div class="repartition" role="img"
               aria-label="Répartition des commandes par état">
            @foreach($etats as $e)
              @php $c = ['ok'=>'var(--ok)','grave'=>'var(--grave)','alerte'=>'var(--alerte)',
                         'info'=>'var(--info)','neutre'=>'var(--line-strong)'][$e['ton']] @endphp
              <span style="width:{{ $e['part'] }}%;background:{{ $c }}"
                    title="{{ $e['libelle'] }} : {{ $e['nombre'] }}"></span>
            @endforeach
          </div>
          <div class="pile-sm">
            @foreach($etats as $e)
              @php $c = ['ok'=>'var(--ok)','grave'=>'var(--grave)','alerte'=>'var(--alerte)',
                         'info'=>'var(--info)','neutre'=>'var(--line-strong)'][$e['ton']] @endphp
              <div class="rang-serre petit">
                <i style="width:.625rem;height:.625rem;border-radius:2px;background:{{ $c }};flex:none"></i>
                <span>{{ $e['libelle'] }}</span>
                <span class="chiffre pousse secondaire">{{ $e['nombre'] }}</span>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>

    {{-- Les ruptures : la seule chose de cet écran sur laquelle le vendeur peut
         agir dans la minute. Un compteur ne disait pas quels produits. --}}
    <div class="bloc">
      <div class="bloc-tete">
        <h2>En rupture</h2>
        @if($chiffres['en_rupture'])
          <span class="jeton jeton-grave">{{ $chiffres['en_rupture'] }}</span>
        @endif
      </div>
      <div class="bloc-corps">
        @forelse($ruptures as $p)
          <div class="rang-serre" style="padding:var(--s2) 0">
            <a href="{{ route('vendeur.produit.editer', $p) }}" class="petit tronque-1">
              {{ $p->nom }}
            </a>
            <span class="mini secondaire pousse chiffre">{{ $p->nombre_ventes }} vendus</span>
          </div>
        @empty
          <p class="petit secondaire">Aucun produit en rupture. Votre catalogue est
          entièrement commandable.</p>
        @endforelse

        @if($chiffres['en_rupture'] > $ruptures->count())
          <a href="{{ route('vendeur.produits') }}" class="lien petit">
            et {{ $chiffres['en_rupture'] - $ruptures->count() }} autre(s)
          </a>
        @endif
      </div>
    </div>

    <div class="bloc">
      <div class="bloc-tete"><h2>Ma vitrine</h2></div>
      <div class="bloc-corps pile-sm">
        <div class="rang-serre">
          <span class="petit secondaire">Note</span>
          <span class="pousse">
            @if($boutique->nombre_avis)
              <span class="chiffre" style="font-weight:700">{{ number_format($boutique->noteSurCinq(), 1, ',', ' ') }}</span>
              <span class="mini secondaire">/ 5 · {{ $boutique->nombre_avis }} avis</span>
            @else
              <span class="mini secondaire">pas encore d'avis</span>
            @endif
          </span>
        </div>
        <div class="rang-serre">
          <span class="petit secondaire">Produits en ligne</span>
          <span class="chiffre pousse" style="font-weight:700">{{ $chiffres['produits'] }}</span>
        </div>
        <a href="{{ route('vendeur.boutique') }}" class="btn btn-clair btn-sm btn-bloc"
           style="margin-top:var(--s2)">Modifier ma vitrine</a>
      </div>
    </div>
  </div>
</div>

@endsection
