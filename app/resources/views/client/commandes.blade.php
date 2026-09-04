@extends('layouts.app')
@section('titre', 'Mes commandes')
@section('contenu')

@include('partials.entete', [
  'titre' => 'Mes commandes',
  'sous' => 'Vous réglez à la livraison : rien n\'est prélevé avant que le colis n\'arrive.',
  'fil' => [
    ['libelle' => 'Accueil', 'url' => route('accueil')],
    ['libelle' => 'Mes commandes'],
  ],
])

@if($liste->isEmpty())
  <div class="bloc">
    @include('partials.vide', [
      'icone' => 'boite',
      'titre' => 'Aucune commande',
      'texte' => 'Vos commandes apparaîtront ici avec leur suivi, leur code de remise et le montant à préparer pour le livreur.',
      'action' => '<a href="' . route('accueil') . '" class="btn">Voir le catalogue</a>',
    ])
  </div>
@else
  <div class="pile">
    @foreach($liste as $c)
      <a href="{{ route('mes-commandes.detail', $c) }}" class="carte carte-lien pile-sm">

        <div class="rang">
          <strong class="chiffre">{{ $c->reference }}</strong>
          @include('partials.etat', ['etat' => $c->etat])

          {{-- Ce qui demande une action du client passe devant la date : un
               code à donner au livreur ou un litige en cours ne doivent pas se
               découvrir en ouvrant la commande. --}}
          @if($c->code_livraison && in_array($c->etat, ['expediee', 'en_livraison'], true))
            <span class="jeton jeton-marque">
              @include('partials.symbole', ['nom' => 'cadenas', 'taille' => 12])
              Code {{ $c->code_livraison }}
            </span>
          @endif

          <span class="petit secondaire">
            {{ $c->created_at->translatedFormat('j F Y') }} ·
            {{ $c->lignes->count() }} article{{ $c->lignes->count() > 1 ? 's' : '' }}
          </span>

          <strong class="chiffre pousse" style="font-size:var(--t-md)">
            {{ number_format($c->total, 0, ',', ' ') }} F
          </strong>
        </div>

        <div class="petit secondaire tronque-1">
          {{ $c->lignes->pluck('nom_produit')->take(2)->implode(' · ') }}
          @if($c->lignes->count() > 2)
            · et {{ $c->lignes->count() - 2 }} autre(s)
          @endif
        </div>

        @if($c->confirmableParLeClient())
          <div class="petit" style="color:var(--brand-strong);font-weight:650">
            Reçue ? Confirmez-le pour clore la vente et pouvoir noter les articles.
          </div>
        @elseif($c->etat === 'livree' && $c->contestableParLeClient())
          <div class="petit" style="color:var(--brand-strong);font-weight:650">
            Donnez votre avis sur les articles reçus.
          </div>
        @endif
      </a>
    @endforeach
  </div>

  @if($liste->hasPages())
    <div style="margin-top:var(--s6)">{{ $liste->links() }}</div>
  @endif
@endif

@endsection
