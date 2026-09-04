@extends('layouts.app')
@section('titre', 'Administration')
@section('contenu')

@include('partials.entete', [
  'titre' => 'La place de marché',
  'sous' => 'Ce qui vit, ce qui rapporte, et ce qui demande une décision aujourd\'hui.',
  'fil' => [
    ['libelle' => 'Accueil', 'url' => route('accueil')],
    ['libelle' => 'Administration'],
  ],
  'actions' => '<a href="' . route('admin.revenus') . '" class="btn btn-clair">Les revenus</a>'
    . '<a href="' . route('admin.boutiques') . '" class="btn btn-clair">Les boutiques</a>',
])

{{-- Ce qui appelle une décision passe avant ce qui décrit l'activité : un
     dossier en attente d'arbitrage bloque une commission et laisse deux parties
     sans réponse. L'ancien tableau le noyait au milieu de sept compteurs. --}}
@if($litiges || $chiffres['boutiques_en_attente'] || $chiffres['a_expedier'])
  <div class="bloc" style="margin-bottom:var(--s6)">
    <div class="bloc-tete">
      <h2>À traiter</h2>
      <span class="sous">ce qui attend une décision de la plateforme</span>
    </div>
    <div class="bloc-corps">
      <div class="grille g3">
        @if($litiges)
          <a href="{{ route('admin.litiges') }}" class="rang-serre"
             style="padding:var(--s3);border:1px solid var(--alerte-line);
                    border-radius:var(--r-sm);background:var(--alerte-soft)">
            @include('partials.symbole', ['nom' => 'balance'])
            <div>
              <div style="font-weight:700;color:var(--alerte-ink)">
                {{ $litiges }} litige(s) à trancher
              </div>
              <div class="mini" style="color:var(--alerte-ink)">
                Aucune commission n'est due tant qu'ils durent.
              </div>
            </div>
          </a>
        @endif

        @if($chiffres['boutiques_en_attente'])
          <a href="{{ route('admin.boutiques', ['statut' => 'en_attente']) }}" class="rang-serre"
             style="padding:var(--s3);border:1px solid var(--info-line);
                    border-radius:var(--r-sm);background:var(--info-soft)">
            @include('partials.symbole', ['nom' => 'boutique'])
            <div>
              <div style="font-weight:700;color:var(--info-ink)">
                {{ $chiffres['boutiques_en_attente'] }} boutique(s) à valider
              </div>
              <div class="mini" style="color:var(--info-ink)">
                Leurs produits restent invisibles au catalogue.
              </div>
            </div>
          </a>
        @endif

        @if($chiffres['a_expedier'])
          <a href="{{ route('admin.commandes', ['etat' => 'en_preparation']) }}" class="rang-serre"
             style="padding:var(--s3);border:1px solid var(--line);
                    border-radius:var(--r-sm);background:var(--surface-2)">
            @include('partials.symbole', ['nom' => 'boite'])
            <div>
              <div style="font-weight:700">{{ $chiffres['a_expedier'] }} commande(s) non expédiée(s)</div>
              <div class="mini secondaire">Chez les vendeurs, pas chez vous.</div>
            </div>
          </a>
        @endif
      </div>
    </div>
  </div>
@endif

<div class="indicateurs" style="margin-bottom:var(--s6)">
  @include('partials.indicateur', [
    'libelle' => 'Commission acquise',
    'valeur' => number_format($chiffres['commission'], 0, ',', ' '),
    'unite' => 'F',
    'ecart' => $variation,
    'note' => 'sur ' . number_format($chiffres['volume_livre'], 0, ',', ' ') . ' F livrés',
    'lien' => route('admin.revenus'),
    'ton' => 'bon',
  ])
  @include('partials.indicateur', [
    'libelle' => 'Commandes',
    'valeur' => $chiffres['commandes'],
    'note' => $chiffres['en_route'] . ' en route en ce moment',
    'lien' => route('admin.commandes'),
  ])
  @include('partials.indicateur', [
    'libelle' => 'Taux de refus',
    'valeur' => number_format($chiffres['taux_refus'], 1, ',', ' '),
    'unite' => '%',
    'note' => $chiffres['refusees'] . ' colis repoussé(s) à la porte',
    'ton' => $chiffres['taux_refus'] >= 15 ? 'tension' : null,
    'lien' => route('admin.litiges'),
  ])
  @include('partials.indicateur', [
    'libelle' => 'La place',
    'valeur' => $chiffres['boutiques_actives'],
    'note' => $chiffres['produits'] . ' produits · ' . $chiffres['clients'] . ' clients',
    'lien' => route('admin.boutiques'),
  ])
</div>

<div class="deux-colonnes">
  <div class="pile-lg">

    <div class="bloc">
      <div class="bloc-tete">
        <h2>Ce que gagne la plateforme</h2>
        <span class="sous">commission acquise, six derniers mois</span>
      </div>
      <div class="bloc-corps">
        @include('partials.graphe-ligne', [
          'points' => $commissions->map(fn ($m) => [
            'libelle' => $m['libelle'], 'valeur' => $m['valeur'],
          ])->all(),
          'suffixe' => ' F',
          'titre' => 'Commission mensuelle acquise par la plateforme, six derniers mois',
        ])
      </div>
      <div class="bloc-pied">
        La plateforme ne touche jamais l'argent du client : ceci est un état de
        créances sur les vendeurs, pas un solde bancaire.
      </div>
    </div>

    <div class="bloc">
      <div class="bloc-tete">
        <h2>Volume de marchandise livrée</h2>
        <span class="sous">six derniers mois, toutes boutiques</span>
      </div>
      <div class="bloc-corps">
        @include('partials.graphe-barres', [
          'series' => $volumes->map(fn ($m) => [
            'libelle' => $m['libelle'], 'valeur' => $m['valeur'],
          ])->all(),
          'suffixe' => ' F',
          'titre' => 'Volume mensuel de marchandise livrée, six derniers mois',
        ])
      </div>
    </div>
  </div>

  <div class="pile-lg colonne-fixe">

    @if($aValider->isNotEmpty())
      <div class="bloc">
        <div class="bloc-tete">
          <h2>Dossiers en attente</h2>
          <span class="jeton jeton-alerte">{{ $aValider->count() }}</span>
        </div>
        <div class="bloc-corps pile">
          @foreach($aValider as $b)
            <div class="pile-sm" style="padding-bottom:var(--s3);
                        border-bottom:1px solid var(--line)">
              <div>
                <div style="font-weight:650">{{ $b->nom }}</div>
                <div class="mini secondaire">
                  {{ $b->ville }} · {{ $b->utilisateur?->email }}
                </div>
              </div>
              <div class="rang-sm">
                <form method="POST" action="{{ route('admin.activer', $b) }}">
                  @csrf<button class="btn btn-sm btn-ok">Valider</button>
                </form>
                <a href="{{ route('admin.boutiques', ['statut' => 'en_attente']) }}"
                   class="btn btn-sm btn-clair">Examiner</a>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endif

    <div class="bloc">
      <div class="bloc-tete"><h2>Répartition des commandes</h2></div>
      <div class="bloc-corps pile">
        @if($etats->isEmpty())
          <p class="petit secondaire">Aucune commande enregistrée.</p>
        @else
          <div class="repartition" role="img" aria-label="Répartition des commandes par état">
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
                <span class="chiffre pousse secondaire">
                  {{ $e['nombre'] }}
                  <span class="mini">({{ number_format($e['part'], 1, ',', ' ') }} %)</span>
                </span>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>

    <div class="bloc">
      <div class="bloc-tete">
        <h2>Les mieux notées</h2>
        <a href="{{ route('admin.boutiques') }}" class="btn btn-sm btn-fantome pousse">Toutes</a>
      </div>
      <div class="bloc-corps">
        @forelse($meilleures as $b)
          <div class="rang-serre" style="padding:var(--s2) 0">
            <span class="petit tronque-1">{{ $b->nom }}</span>
            <span class="pousse rang-serre" style="gap:var(--s1)">
              @if($b->nombre_avis)
                <span class="chiffre petit" style="font-weight:700">
                  {{ number_format($b->noteSurCinq(), 1, ',', ' ') }}
                </span>
                <span class="mini secondaire">({{ $b->nombre_avis }})</span>
              @else
                <span class="mini secondaire">pas d'avis</span>
              @endif
            </span>
          </div>
        @empty
          <p class="petit secondaire">Aucune boutique active.</p>
        @endforelse
      </div>
    </div>
  </div>
</div>

@endsection
