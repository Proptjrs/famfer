@extends('layouts.app')
@section('titre', 'Ma commission')
@section('contenu')

@php
  $taux = rtrim(rtrim(number_format($compte['taux'], 1, ',', ' '), '0'), ',');
  $partCommission = $compte['encaisse'] > 0
    ? round($compte['commission'] * 100 / $compte['encaisse'], 1) : 0;
@endphp

@include('partials.entete', [
  'titre' => 'Ma commission FamFer',
  'sous' => 'Vous encaissez vous-même les espèces. La plateforme ne retient rien : elle vous facture après coup, et seulement sur les commandes livrées.',
  'fil' => [
    ['libelle' => 'Ma boutique', 'url' => route('vendeur.tableau')],
    ['libelle' => 'Ma commission'],
  ],
  'actions' => '<a href="' . route('vendeur.commandes') . '" class="btn btn-clair">Mes ventes</a>',
])

<div class="indicateurs" style="margin-bottom:var(--s6)">
  @include('partials.indicateur', [
    'libelle' => 'Encaissé à la livraison',
    'valeur' => number_format($compte['encaisse'], 0, ',', ' '),
    'unite' => 'F',
    'note' => 'dont ' . number_format($compte['port'], 0, ',', ' ') . ' F de port, qui vous revient',
  ])
  @include('partials.indicateur', [
    'libelle' => 'Commission FamFer',
    'valeur' => '− ' . number_format($compte['commission'], 0, ',', ' '),
    'unite' => 'F',
    'note' => $taux . ' % de la marchandise · ' . number_format($partCommission, 1, ',', ' ')
      . ' % de ce que vous encaissez',
    'ton' => 'attention',
  ])
  @include('partials.indicateur', [
    'libelle' => 'Ce qui vous reste',
    'valeur' => number_format($compte['net'], 0, ',', ' '),
    'unite' => 'F',
    'note' => 'net, port compris',
    'ton' => 'bon',
  ])
  @include('partials.indicateur', [
    'libelle' => 'Articles livrés',
    'valeur' => $compte['ventes'],
    'note' => 'seuls ceux-ci sont facturés',
  ])
</div>

<div class="deux-colonnes">
  <div class="pile-lg">

    <div class="bloc">
      <div class="bloc-tete">
        <h2>Relevé mensuel</h2>
        <span class="sous">le document sur lequel vous réglez</span>
      </div>

      @if($releve->isNotEmpty())
        <div class="bloc-corps" style="border-bottom:1px solid var(--line)">
          @include('partials.graphe-barres', [
            'series' => $releve->reverse()->values()->map(fn ($l) => [
              'libelle' => \Illuminate\Support\Carbon::createFromFormat('Y-m', $l->periode)
                             ->translatedFormat('M'),
              'valeur' => (int) $l->commission,
              'ton' => 'alerte',
            ])->all(),
            'hauteur' => 150,
            'suffixe' => ' F',
            'titre' => 'Commission due mois par mois',
          ])
        </div>
      @endif

      <div class="bloc-corps serre defile-x">
        <table class="tableau">
          <thead>
            <tr>
              <th scope="col">Mois</th>
              <th scope="col" class="num">Commandes</th>
              <th scope="col" class="num">Marchandise</th>
              <th scope="col" class="num">Commission</th>
              <th scope="col" class="num">Net</th>
            </tr>
          </thead>
          <tbody>
            @forelse($releve as $l)
              <tr>
                <td class="chiffre">
                  {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $l->periode)
                       ->translatedFormat('F Y') }}
                </td>
                <td class="num">{{ $l->commandes }}</td>
                <td class="num">{{ number_format((int) $l->marchandise, 0, ',', ' ') }} F</td>
                <td class="num" style="color:var(--alerte-ink);font-weight:650">
                  − {{ number_format((int) $l->commission, 0, ',', ' ') }} F
                </td>
                <td class="num" style="font-weight:700">
                  {{ number_format((int) $l->marchandise - (int) $l->commission, 0, ',', ' ') }} F
                </td>
              </tr>
            @empty
              <tr><td colspan="5" style="padding:0">
                @include('partials.vide', [
                  'icone' => 'argent',
                  'titre' => 'Vous ne devez rien',
                  'texte' => 'Aucune commande livrée pour l\'instant. La commission n\'est due qu\'à la remise du colis.',
                  'action' => '<a href="' . route('vendeur.produits') . '" class="btn btn-clair btn-sm">Gérer mes produits</a>',
                ])
              </td></tr>
            @endforelse
          </tbody>

          @if($releve->isNotEmpty())
            <tfoot>
              <tr>
                <td>Total</td>
                <td class="num">{{ $releve->sum('commandes') }}</td>
                <td class="num">{{ number_format((int) $releve->sum('marchandise'), 0, ',', ' ') }} F</td>
                <td class="num">− {{ number_format((int) $releve->sum('commission'), 0, ',', ' ') }} F</td>
                <td class="num">
                  {{ number_format((int) $releve->sum('marchandise') - (int) $releve->sum('commission'), 0, ',', ' ') }} F
                </td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    </div>
  </div>

  <div class="pile-lg colonne-fixe">

    <div class="bloc">
      <div class="bloc-tete"><h2>Comment c'est calculé</h2></div>
      <div class="bloc-corps pile">
        {{-- Le calcul posé ligne à ligne. Un décompte qu'un commerçant ne peut
             pas refaire lui-même n'inspire aucune confiance, et la confiance
             est ce qui le fait rester. --}}
        <div class="liste dans-bloc">
          <div class="rang-serre">
            <span class="petit secondaire">Marchandise livrée</span>
            <span class="chiffre pousse">{{ number_format($compte['marchandise'], 0, ',', ' ') }} F</span>
          </div>
          <div class="rang-serre">
            <span class="petit secondaire">Taux négocié</span>
            <span class="chiffre pousse">× {{ $taux }} %</span>
          </div>
          <div class="rang-serre" style="border-top:1px solid var(--line-strong)">
            <span style="font-weight:650">Commission due</span>
            <span class="chiffre pousse" style="font-weight:700;color:var(--alerte-ink)">
              {{ number_format($compte['commission'], 0, ',', ' ') }} F
            </span>
          </div>
        </div>

        <div class="message message-ok petit">
          @include('partials.symbole', ['nom' => 'camion', 'taille' => 16])
          <div>
            Les <strong>{{ number_format($compte['port'], 0, ',', ' ') }} F de port</strong>
            vous reviennent entièrement : c'est vous qui faites la tournée, en
            prélever une part reviendrait à taxer votre carburant.
          </div>
        </div>

        <div class="message message-info petit">
          @include('partials.symbole', ['nom' => 'info', 'taille' => 16])
          <div>
            Une commande <strong>refusée à la porte, annulée ou retournée ne vous
            coûte rien</strong> — le déplacement vous a déjà coûté assez.
          </div>
        </div>
      </div>
    </div>

    <div class="bloc">
      <div class="bloc-tete"><h2>Le règlement</h2></div>
      <div class="bloc-corps">
        <p class="petit secondaire">
          La plateforme ne touche jamais l'argent du client : c'est vous qui
          encaissez. Elle vous présente donc ce relevé, et vous le réglez par vos
          propres moyens. Le prélèvement automatique suppose un contrat avec un
          opérateur mobile, qui n'est pas encore signé.
        </p>
      </div>
    </div>
  </div>
</div>

@endsection
