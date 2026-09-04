@extends('layouts.app')
@section('titre', 'Mes ventes')
@section('contenu')

@php
  $libelles = [
    'en_preparation' => 'À expédier', 'expediee' => 'Expédiées',
    'en_livraison' => 'En livraison', 'livree' => 'Livrées',
    'litige' => 'Litiges', 'refusee' => 'Refusées',
    'annulee' => 'Annulées', 'retournee' => 'Retournées',
  ];
@endphp

@include('partials.entete', [
  'titre' => 'Mes ventes',
  'sous' => 'Tout ce qui est passé par votre boutique, y compris ce qui n\'a pas abouti.',
  'fil' => [
    ['libelle' => 'Ma boutique', 'url' => route('vendeur.tableau')],
    ['libelle' => 'Mes ventes'],
  ],
  'actions' => '<a href="' . route('vendeur.commissions') . '" class="btn btn-clair">Ma commission</a>',
])

{{-- Les états en onglets plutôt qu'en boutons. Une rangée de boutons pleins ne
     disait pas lequel était actif ; l'onglet souligné le dit sans ambiguïté, et
     « aria-current » le dit aussi aux lecteurs d'écran. --}}
<nav class="onglets" style="margin-bottom:var(--s5)" aria-label="Filtrer par état">
  <a href="{{ route('vendeur.commandes') }}" @if(! $etatFiltre) aria-current="page" @endif>
    Toutes <span class="nb">{{ $parEtat->sum() }}</span>
  </a>
  @foreach($libelles as $cle => $mot)
    @continue(! isset($parEtat[$cle]))
    <a href="{{ route('vendeur.commandes', ['etat' => $cle]) }}"
       @if($etatFiltre === $cle) aria-current="page" @endif>
      {{ $mot }} <span class="nb">{{ $parEtat[$cle] }}</span>
    </a>
  @endforeach
</nav>

@forelse($liste as $c)
  @php $miennes = $c->lignes->where('boutique_id', $boutique->id); @endphp

  <div class="bloc" style="margin-bottom:var(--s4)">
    <div class="bloc-tete">
      <strong class="chiffre">{{ $c->reference }}</strong>
      @include('partials.etat', ['etat' => $c->etat])
      <span class="sous">
        {{ $c->utilisateur->name }} · {{ $c->created_at->translatedFormat('j M Y') }}
      </span>
      <strong class="chiffre pousse" style="font-size:var(--t-md)">
        {{ number_format($miennes->sum('montant'), 0, ',', ' ') }} F
      </strong>
    </div>

    <div class="bloc-corps pile">
      <div class="pile-sm">
        @foreach($miennes as $ligne)
          <div class="rang-serre petit">
            <span class="chiffre secondaire" style="flex:none">{{ $ligne->quantite }} ×</span>
            <span class="tronque-1">{{ $ligne->nom_produit }}</span>
            <span class="chiffre pousse secondaire">
              {{ number_format($ligne->montant, 0, ',', ' ') }} F
            </span>
          </div>
        @endforeach
      </div>

      <div class="rang" style="padding-top:var(--s3);border-top:1px solid var(--line)">
        <div class="petit secondaire" style="flex:1 1 16rem;min-width:0">
          <div style="font-weight:650;color:var(--ink-2)">{{ $c->destinataire }}</div>
          <div class="chiffre">{{ $c->telephone }}</div>
          <div>{{ $c->adresse_livraison }}</div>
        </div>

        @if($c->etat === 'en_preparation')
          <form method="POST" action="{{ route('vendeur.expedier', $c) }}" class="pousse">
            @csrf
            <button type="submit" class="btn btn-sm">
              @include('partials.symbole', ['nom' => 'camion', 'taille' => 15])
              Expédier
            </button>
          </form>

        @elseif(in_array($c->etat, ['expediee', 'en_livraison']))
          {{-- Le code de remise, dicté par le client au moment où il reçoit et
               règle. Sans lui, le vendeur déclarerait seul une livraison dont il
               est le bénéficiaire : il pourrait encaisser puis annoncer un refus
               pour garder l'argent sans payer de commission. --}}
          <form method="POST" action="{{ route('vendeur.livrer', $c) }}"
                class="rang-serre pousse" style="gap:var(--s2)">
            @csrf
            <label for="code{{ $c->id }}" class="visuellement-cache">
              Code de remise dicté par le client</label>
            <input id="code{{ $c->id }}" name="code" inputmode="numeric"
                   pattern="[0-9]{6}" maxlength="6" required class="chiffre"
                   placeholder="Code client" autocomplete="off"
                   style="width:7rem;letter-spacing:.1em;text-align:center">
            <button type="submit" class="btn btn-sm btn-ok">Marquer livrée</button>
          </form>

        @elseif($c->etat === 'litige')
          <span class="jeton jeton-alerte pousse">
            @include('partials.symbole', ['nom' => 'balance', 'taille' => 12])
            L'administration examine le dossier
          </span>

        @elseif($c->motif)
          <span class="petit pousse" style="color:var(--grave-ink);text-align:right;
                       max-width:22rem">{{ $c->motif }}</span>
        @endif
      </div>

      {{-- Le refus à la porte est le risque propre au paiement à la livraison :
           la tournée a eu lieu et n'a rien rapporté. Il doit s'enregistrer, sans
           quoi le taux de refus des tableaux de bord reste à zéro quoi qu'il
           arrive. --}}
      @if(in_array($c->etat, ['expediee', 'en_livraison']))
        <details>
          <summary style="cursor:pointer;color:var(--grave-ink);font-size:var(--t-xs);
                          font-weight:650;padding:var(--s1) 0">
            Le client a refusé le colis
          </summary>
          <form method="POST" action="{{ route('vendeur.refuser', $c) }}"
                class="rang" style="margin-top:var(--s3);align-items:flex-end">
            @csrf
            <div class="champ" style="flex:1 1 18rem;margin:0">
              <label for="refus{{ $c->id }}">Pourquoi</label>
              <input id="refus{{ $c->id }}" name="motif" required maxlength="200"
                     placeholder="Absent après deux passages, a changé d'avis, marchandise non conforme…">
            </div>
            <button type="submit" class="btn btn-sm btn-grave">Enregistrer le refus</button>
          </form>
          <p class="mini secondaire" style="margin-top:var(--s2)">
            Le stock revient en rayon et vous ne devez aucune commission. Le client
            pourra contester ce refus s'il estime avoir reçu le colis.
          </p>
        </details>

      @elseif($c->etat === 'livree')
        <details>
          <summary style="cursor:pointer;color:var(--ink-2);font-size:var(--t-xs);
                          font-weight:650;padding:var(--s1) 0">
            Enregistrer un retour
          </summary>
          <form method="POST" action="{{ route('vendeur.retourner', $c) }}"
                class="rang" style="margin-top:var(--s3);align-items:flex-end">
            @csrf
            <div class="champ" style="flex:1 1 18rem;margin:0">
              <label for="retour{{ $c->id }}">Motif du retour</label>
              <input id="retour{{ $c->id }}" name="motif" required maxlength="200"
                     placeholder="Article non conforme, dimension erronée…">
            </div>
            <button type="submit" class="btn btn-sm btn-clair">Enregistrer le retour</button>
          </form>
        </details>

      @elseif(in_array($c->etat, ['expediee', 'en_livraison', 'livree'], true))
        <details>
          <summary style="cursor:pointer;color:var(--ink-2);font-size:var(--t-xs);
                          font-weight:650;padding:var(--s1) 0">
            Le client refuse de donner son code
          </summary>
          <form method="POST" action="{{ route('vendeur.contester', $c) }}"
                class="rang" style="margin-top:var(--s3);align-items:flex-end">
            @csrf
            <div class="champ" style="flex:1 1 18rem;margin:0">
              <label for="lit{{ $c->id }}">Que s'est-il passé ?</label>
              <input id="lit{{ $c->id }}" name="motif" required minlength="10" maxlength="300"
                     placeholder="Colis remis et payé, le client refuse de communiquer son code…">
            </div>
            <button type="submit" class="btn btn-sm btn-clair">Ouvrir un litige</button>
          </form>
        </details>
      @endif
    </div>
  </div>
@empty
  <div class="bloc">
    @include('partials.vide', [
      'icone' => $etatFiltre ? 'filtre' : 'boite',
      'titre' => $etatFiltre ? 'Aucune commande dans cet état' : 'Aucune vente pour l\'instant',
      'texte' => $etatFiltre
        ? 'Changez de filtre pour voir le reste de vos ventes.'
        : 'Vos ventes apparaîtront ici. Vous serez prévenu par courriel dès qu\'une commande arrive.',
      'action' => $etatFiltre
        ? '<a href="' . route('vendeur.commandes') . '" class="btn btn-clair">Voir toutes mes ventes</a>'
        : '<a href="' . route('vendeur.produit.nouveau') . '" class="btn">Ajouter un produit</a>',
    ])
  </div>
@endforelse

@if($liste->hasPages())
  <div style="margin-top:var(--s6)">{{ $liste->links() }}</div>
@endif

@endsection
