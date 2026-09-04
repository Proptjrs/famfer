@extends('layouts.app')
@section('titre', 'Revenus de la plateforme')
@section('contenu')

@include('partials.entete', [
  'titre' => 'Ce que gagne FamFer',
  'sous' => 'La plateforme ne touche jamais l\'argent du client : le vendeur encaisse, puis reverse sa commission. Ceci est un état de créances, pas un solde bancaire.',
  'fil' => [
    ['libelle' => 'Administration', 'url' => route('admin.tableau')],
    ['libelle' => 'Les revenus'],
  ],
  'actions' => '<a href="' . route('admin.litiges') . '" class="btn btn-clair">Les litiges</a>',
])

<div class="indicateurs" style="margin-bottom:var(--s6)">
  @include('partials.indicateur', [
    'libelle' => 'Marchandise livrée',
    'valeur' => number_format($chiffres['volume'], 0, ',', ' '),
    'unite' => 'F',
    'note' => 'assiette de la commission, port exclu',
  ])
  @include('partials.indicateur', [
    'libelle' => 'Commission acquise',
    'valeur' => number_format($chiffres['commission'], 0, ',', ' '),
    'unite' => 'F',
    'note' => 'due par les vendeurs, non encaissée',
    'ton' => 'bon',
  ])
  @include('partials.indicateur', [
    'libelle' => 'Taux moyen obtenu',
    'valeur' => number_format($chiffres['taux_moyen'], 2, ',', ' '),
    'unite' => '%',
    'note' => 'et non le taux affiché : les enseignes négocient',
  ])
  @include('partials.indicateur', [
    'libelle' => 'Perdue sur refus et retours',
    'valeur' => number_format($chiffres['perdue_sur_refus'], 0, ',', ' '),
    'unite' => 'F',
    'note' => 'le coût assumé du paiement à la livraison',
    'ton' => $chiffres['perdue_sur_refus'] > 0 ? 'tension' : null,
  ])
</div>

<div class="bloc">
  <div class="bloc-tete">
    <h2>Par boutique</h2>
    <span class="sous">
      {{ $classement->count() }} enseigne(s), du plus gros débiteur au plus petit
    </span>
  </div>

  <div class="bloc-corps serre defile-x">
    <table class="tableau">
      <thead>
        <tr>
          <th scope="col">Boutique</th>
          <th scope="col" class="num">Commission due</th>
          <th scope="col">Part du total</th>
          <th scope="col">Taux négocié</th>
        </tr>
      </thead>
      <tbody>
        @php $totalDu = max(1, (int) $classement->sum('commission_due')); @endphp

        @forelse($classement as $b)
          @php $part = round((int) $b->commission_due * 100 / $totalDu, 1); @endphp
          <tr>
            <td>
              <a href="{{ route('boutique', $b) }}" class="lien">{{ $b->nom }}</a>
              @if($b->officielle)<span class="jeton jeton-info">Officielle</span>@endif
              <div class="mini secondaire">{{ $b->ville }}</div>
            </td>
            <td class="num" style="font-weight:700">
              {{ number_format((int) $b->commission_due, 0, ',', ' ') }} F
            </td>
            <td style="min-width:9rem">
              {{-- La part de chacun, dessinée. Une colonne de montants ne dit
                   pas si le revenu tient à une seule enseigne — or c'est
                   exactement le risque qu'une plateforme doit surveiller. --}}
              <div class="rang-serre" style="gap:var(--s2)">
                <div class="jauge" style="flex:1">
                  <span style="width:{{ $part }}%"></span>
                </div>
                <span class="mini secondaire chiffre" style="flex:none">
                  {{ number_format($part, 1, ',', ' ') }} %
                </span>
              </div>
            </td>
            <td>
              {{-- Le taux se renégocie : une enseigne qui apporte du volume n'a
                   aucune raison de payer comme un nouveau venu. Les commandes
                   déjà passées gardent le leur, figé. --}}
              <form method="POST" action="{{ route('admin.taux', $b) }}"
                    class="rang-serre" style="gap:var(--s2)">
                @csrf
                <label for="taux{{ $b->id }}" class="visuellement-cache">
                  Taux de commission de {{ $b->nom }}, en pourcentage</label>
                <input id="taux{{ $b->id }}" type="number" name="taux" step="0.1"
                       min="0" max="30" value="{{ $b->tauxPourCent() }}"
                       class="chiffre" style="width:5.25rem">
                <span class="secondaire" aria-hidden="true">%</span>
                <button type="submit" class="btn btn-sm btn-clair">Appliquer</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" style="padding:0">
            @include('partials.vide', [
              'icone' => 'boutique',
              'titre' => 'Aucune boutique',
              'texte' => 'La place de marché ne compte encore aucune enseigne.',
            ])
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="bloc-pied">
    Un taux modifié ne vaut que pour l'avenir : chaque commande porte le sien,
    figé à sa création.
  </div>
</div>

@endsection
