@extends('layouts.app')
@section('titre', 'Litiges')
@section('contenu')

@include('partials.entete', [
  'titre' => 'Litiges et surveillance',
  'sous' => 'Le seul endroit où la plateforme décide à la place des parties. Cela doit rester rare : une place de marché qui arbitre tous les jours a un problème de vendeurs, pas de logiciel.',
  'fil' => [
    ['libelle' => 'Administration', 'url' => route('admin.tableau')],
    ['libelle' => 'Les litiges'],
  ],
  'actions' => '<a href="' . route('admin.revenus') . '" class="btn btn-clair">Les revenus</a>',
])

<div class="bloc">
  <div class="bloc-tete">
    @include('partials.symbole', ['nom' => 'balance', 'taille' => 18])
    <h2>{{ $liste->count() }} litige(s) ouvert(s)</h2>
    <span class="sous">aucune commission n'est due tant qu'ils durent</span>
  </div>

  <div class="bloc-corps {{ $liste->isEmpty() ? '' : 'pile-lg' }}">
    @forelse($liste as $c)
      <div class="pile" style="{{ ! $loop->last ? 'padding-bottom:var(--s6);border-bottom:1px solid var(--line)' : '' }}">

        <div class="rang">
          <strong class="chiffre">{{ $c->reference }}</strong>
          <span class="jeton jeton-neutre">état contesté : {{ $c->etat_conteste }}</span>
          <span class="jeton jeton-alerte">ouvert par {{ $c->litige_par }}</span>
          <span class="petit secondaire">
            {{ $c->litige_le?->translatedFormat('j F Y') }}
            ({{ $c->litige_le?->diffForHumans() }})
          </span>
          <strong class="chiffre pousse" style="font-size:var(--t-md)">
            {{ number_format($c->total, 0, ',', ' ') }} F
          </strong>
        </div>

        <div class="grille g3">
          <div>
            <div class="mini secondaire">Client</div>
            <div>{{ $c->destinataire }}</div>
            <div class="chiffre petit secondaire">{{ $c->telephone }}</div>
          </div>
          <div>
            <div class="mini secondaire">Boutique</div>
            <div>{{ $c->lignes->first()?->boutique?->nom ?? '—' }}</div>
          </div>
          <div>
            {{-- Le code n'a jamais été remis : c'est l'indice le plus fort du
                 dossier. S'il l'a été, la livraison a matériellement eu lieu. --}}
            <div class="mini secondaire">Code de remise</div>
            @if($c->code_remis_le)
              <span class="jeton jeton-ok">
                <span class="point" aria-hidden="true"></span>
                remis le {{ $c->code_remis_le->format('d/m/Y') }}
              </span>
            @elseif($c->code_livraison)
              <span class="jeton jeton-grave">
                <span class="point" aria-hidden="true"></span>jamais remis
              </span>
            @else
              <span class="secondaire">—</span>
            @endif
          </div>
          <div>
            <div class="mini secondaire">Confirmé par le client</div>
            @if($c->confirmee_le)
              <span class="jeton jeton-ok">
                <span class="point" aria-hidden="true"></span>
                le {{ $c->confirmee_le->format('d/m/Y') }}
              </span>
            @else
              <span class="secondaire">non</span>
            @endif
          </div>
        </div>

        <div class="message message-alerte">
          @include('partials.symbole', ['nom' => 'info', 'taille' => 17])
          <div><strong>Motif invoqué :</strong> {{ $c->litige_motif }}</div>
        </div>

        <form method="POST" action="{{ route('admin.trancher', $c) }}"
              class="rang" style="align-items:flex-end">
          @csrf
          <div class="champ" style="flex:0 1 22rem;margin:0">
            <label for="vers{{ $c->id }}">Décision</label>
            <select id="vers{{ $c->id }}" name="vers" required>
              <option value="livree">Livrée — la vente a eu lieu, la commission est due</option>
              <option value="refusee">Refusée — le colis est revenu au vendeur</option>
              <option value="annulee">Annulée — la commande est effacée</option>
            </select>
          </div>
          <div class="champ" style="flex:1 1 20rem;margin:0">
            <label for="dec{{ $c->id }}">Motivation</label>
            <input id="dec{{ $c->id }}" name="motif" required minlength="10" maxlength="300"
                   placeholder="Sur quoi la décision se fonde (10 caractères minimum)">
          </div>
          <button type="submit" class="btn">Trancher</button>
        </form>
      </div>
    @empty
      @include('partials.vide', [
        'icone' => 'coche',
        'titre' => 'Aucun litige ouvert',
        'texte' => 'Les deux parties s\'accordent sur toutes les commandes closes. C\'est l\'état normal, et il doit le rester.',
      ])
    @endforelse
  </div>
</div>

<div class="bloc">
  <div class="bloc-tete">
    @include('partials.symbole', ['nom' => 'horloge', 'taille' => 18])
    <h2>Commandes dormantes</h2>
    <span class="sous">
      expédiées depuis plus de {{ \App\Services\Veille::JOURS_AVANT_RELANCE }} jours
      et jamais closes
    </span>
  </div>
  <div class="bloc-corps {{ $dormantes->isEmpty() ? '' : 'serre' }}">
    {{-- Le silence du vendeur est la troisième source de vérité. Un colis
         expédié il y a une semaine et jamais clos dort dans un magasin, ou bien
         il a été remis sans être déclaré — et la seconde hypothèse est celle qui
         l'arrange. La veille quotidienne a déjà posé la question au client. --}}
    @forelse($dormantes as $c)
      <div class="rang" style="padding:var(--s3) var(--s5);
           {{ ! $loop->last ? 'border-bottom:1px solid var(--line)' : '' }}">
        <strong class="chiffre">{{ $c->reference }}</strong>
        @include('partials.etat', ['etat' => $c->etat])
        <span class="petit secondaire">
          {{ $c->lignes->first()?->boutique?->nom ?? '—' }}
        </span>
        <span class="jeton {{ (int) $c->expediee_le?->diffInDays() >= 10 ? 'jeton-grave' : 'jeton-alerte' }}">
          {{ (int) $c->expediee_le?->diffInDays() }} jours
        </span>
        <span class="mini secondaire">
          {{ $c->relance_le ? 'client relancé le ' . $c->relance_le->format('d/m')
                            : 'pas encore relancé' }}
        </span>
        <strong class="chiffre pousse">{{ number_format($c->total, 0, ',', ' ') }} F</strong>
      </div>
    @empty
      @include('partials.vide', [
        'icone' => 'coche',
        'titre' => 'Aucune commande ne traîne',
        'texte' => 'Tout ce qui est parti a été clôturé dans les délais.',
      ])
    @endforelse
  </div>
</div>

<div class="bloc">
  <div class="bloc-tete">
    @include('partials.symbole', ['nom' => 'graphique', 'taille' => 18])
    <h2>Taux de refus par boutique</h2>
    <span class="sous">le détecteur de refus fictifs</span>
  </div>
  <div class="bloc-corps">
    {{-- La preuve par code couvre une commande ; ce tableau couvre le
         commerçant. Un vendeur qui encaisse puis déclare « refusée » pour éviter
         la commission fait monter ce taux, seul, pendant que ses concurrents
         restent bas. --}}
    <p class="secondaire" style="margin-bottom:var(--s4);max-width:68ch">
      Un taux nettement au-dessus des autres est le signe d'une boutique qui
      déclare des refus n'ayant pas eu lieu. Les enseignes de moins de cinq
      commandes closes ne figurent pas ici : deux refus sur trois ventes est le
      lot d'un débutant malchanceux, pas un indice.
    </p>

    <div class="defile-x">
      <table class="tableau">
        <thead>
          <tr>
            <th scope="col">Boutique</th>
            <th scope="col" class="num">Livrées</th>
            <th scope="col" class="num">Refusées</th>
            <th scope="col">Taux de refus</th>
          </tr>
        </thead>
        <tbody>
          @forelse($suspects as $b)
            <tr>
              <td>
                <a href="{{ route('boutique', $b) }}" class="lien">{{ $b->nom }}</a>
                <div class="mini secondaire">{{ $b->ville }}</div>
              </td>
              <td class="num">{{ $b->nb_livrees }}</td>
              <td class="num">{{ $b->nb_refusees }}</td>
              <td style="min-width:11rem">
                <div class="rang-serre" style="gap:var(--s2)">
                  <div class="jauge {{ $b->taux_refus >= 30 ? 'grave' : ($b->taux_refus >= 15 ? 'alerte' : 'ok') }}"
                       style="flex:1">
                    <span style="width:{{ min(100, $b->taux_refus) }}%"></span>
                  </div>
                  <span class="chiffre" style="flex:none;font-weight:700;
                        {{ $b->taux_refus >= 30 ? 'color:var(--grave-ink)' : '' }}">
                    {{ number_format($b->taux_refus, 1, ',', ' ') }} %
                  </span>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" style="padding:0">
              @include('partials.vide', [
                'icone' => 'graphique',
                'titre' => 'Pas encore assez de données',
                'texte' => 'Aucune boutique n\'a atteint cinq commandes closes. En dessous, un taux de refus ne signifie rien.',
              ])
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@endsection
