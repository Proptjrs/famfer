@extends('layouts.app')
@section('titre', 'Les commandes')
@section('contenu')

@php
  $mots = [
    'en_preparation' => 'En préparation', 'expediee' => 'Expédiées',
    'en_livraison' => 'En livraison', 'livree' => 'Livrées',
    'litige' => 'Litiges', 'refusee' => 'Refusées',
    'annulee' => 'Annulées', 'retournee' => 'Retournées',
  ];
@endphp

@include('partials.entete', [
  'titre' => 'Les commandes',
  'sous' => 'Toutes les commandes de la place de marché, dans tous leurs états.',
  'fil' => [
    ['libelle' => 'Administration', 'url' => route('admin.tableau')],
    ['libelle' => 'Les commandes'],
  ],
  'actions' => '<a href="' . route('admin.litiges') . '" class="btn btn-clair">Les litiges</a>',
])

<nav class="onglets" style="margin-bottom:var(--s5)" aria-label="Filtrer par état">
  <a href="{{ route('admin.commandes') }}" @if(! $etatFiltre) aria-current="page" @endif>
    Toutes <span class="nb">{{ $parEtat->sum() }}</span>
  </a>
  @foreach($mots as $cle => $mot)
    @continue(! isset($parEtat[$cle]))
    <a href="{{ route('admin.commandes', ['etat' => $cle]) }}"
       @if($etatFiltre === $cle) aria-current="page" @endif>
      {{ $mot }} <span class="nb">{{ $parEtat[$cle] }}</span>
    </a>
  @endforeach
</nav>

<div class="bloc">
  <div class="bloc-corps serre defile-x">
    <table class="tableau">
      <thead>
        <tr>
          <th scope="col">Référence</th>
          <th scope="col">Client</th>
          <th scope="col">Livraison</th>
          <th scope="col" class="num">Articles</th>
          <th scope="col" class="num">Total</th>
          <th scope="col" class="num">Commission</th>
          <th scope="col">État</th>
        </tr>
      </thead>
      <tbody>
        @forelse($liste as $c)
          <tr>
            <td>
              <strong class="chiffre">{{ $c->reference }}</strong>
              <div class="mini secondaire">
                {{ $c->created_at->translatedFormat('j M Y') }}
              </div>
            </td>

            <td>
              {{ $c->utilisateur->name }}
              <div class="mini secondaire chiffre">{{ $c->telephone }}</div>
            </td>

            <td class="petit secondaire" style="max-width:16rem">
              {{ $c->adresse_livraison }}
            </td>

            <td class="num">{{ $c->lignes->sum('quantite') }}</td>

            <td class="num" style="font-weight:700">
              {{ number_format($c->total, 0, ',', ' ') }} F
              <div class="mini secondaire" style="font-weight:400">
                à la livraison
              </div>
            </td>

            {{-- La commission par commande manquait ici. C'est pourtant la seule
                 colonne de cet écran qui parle du revenu de la plateforme. --}}
            <td class="num">
              @if($c->etat === 'livree')
                <span style="color:var(--ok-ink);font-weight:650">
                  {{ number_format($c->commission, 0, ',', ' ') }} F
                </span>
                <div class="mini secondaire">acquise</div>
              @elseif(in_array($c->etat, ['refusee', 'retournee'], true))
                <span class="secondaire" style="text-decoration:line-through">
                  {{ number_format($c->commission, 0, ',', ' ') }} F
                </span>
                <div class="mini secondaire">perdue</div>
              @elseif($c->etat === 'annulee')
                <span class="secondaire">—</span>
              @else
                <span class="secondaire">
                  {{ number_format($c->commission, 0, ',', ' ') }} F
                </span>
                <div class="mini secondaire">à la livraison</div>
              @endif
            </td>

            <td>
              @include('partials.etat', ['etat' => $c->etat])
              @if($c->motif)
                <div class="mini secondaire" style="max-width:14rem;margin-top:var(--s1)">
                  {{ $c->motif }}
                </div>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" style="padding:0">
            @include('partials.vide', [
              'icone' => 'boite',
              'titre' => $etatFiltre ? 'Aucune commande dans cet état' : 'Aucune commande',
              'texte' => $etatFiltre
                ? 'Changez de filtre pour voir le reste.'
                : 'La place de marché n\'a encore enregistré aucune commande.',
              'action' => $etatFiltre
                ? '<a href="' . route('admin.commandes') . '" class="btn btn-clair">Toutes les commandes</a>'
                : null,
            ])
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@if($liste->hasPages())
  <div style="margin-top:var(--s6)">{{ $liste->links() }}</div>
@endif

@endsection
