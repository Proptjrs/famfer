@extends('layouts.app')
@section('titre', 'Les boutiques')
@section('contenu')

@php $mots = ['en_attente' => 'En attente', 'active' => 'Actives', 'suspendue' => 'Suspendues']; @endphp

@include('partials.entete', [
  'titre' => 'Les boutiques',
  'sous' => 'Valider, suspendre, et distinguer les enseignes démarchées par la plateforme.',
  'fil' => [
    ['libelle' => 'Administration', 'url' => route('admin.tableau')],
    ['libelle' => 'Les boutiques'],
  ],
])

<nav class="onglets" style="margin-bottom:var(--s5)" aria-label="Filtrer par état">
  <a href="{{ route('admin.boutiques') }}" @if(! $statutFiltre) aria-current="page" @endif>
    Toutes <span class="nb">{{ $parStatut->sum() }}</span>
  </a>
  @foreach($mots as $cle => $mot)
    @continue(! isset($parStatut[$cle]))
    <a href="{{ route('admin.boutiques', ['statut' => $cle]) }}"
       @if($statutFiltre === $cle) aria-current="page" @endif>
      {{ $mot }} <span class="nb">{{ $parStatut[$cle] }}</span>
    </a>
  @endforeach
</nav>

<div class="bloc">
  <div class="bloc-corps serre defile-x">
    <table class="tableau">
      <thead>
        <tr>
          <th scope="col">Boutique</th>
          <th scope="col">Tenue par</th>
          <th scope="col">Ville</th>
          <th scope="col" class="num">Produits</th>
          <th scope="col">Note</th>
          <th scope="col">État</th>
          <th scope="col"><span class="visuellement-cache">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        @forelse($boutiques as $b)
          <tr>
            <td>
              @if($b->estVisible())
                <a href="{{ route('boutique', $b) }}" class="lien"
                   style="font-weight:650">{{ $b->nom }}</a>
              @else
                <strong>{{ $b->nom }}</strong>
              @endif
              @if($b->officielle)
                <div><span class="jeton jeton-info">Officielle</span></div>
              @endif
            </td>

            <td class="secondaire">
              {{ $b->utilisateur->name }}
              <div class="mini chiffre">{{ $b->telephone }}</div>
            </td>

            <td class="secondaire">{{ $b->ville }}</td>

            <td class="num">{{ $b->produits_count }}</td>

            <td>
              @if($b->nombre_avis)
                <span class="etoiles" aria-hidden="true">★</span>
                <span class="chiffre">{{ number_format($b->noteSurCinq(), 1, ',', ' ') }}</span>
                <span class="mini secondaire">({{ $b->nombre_avis }})</span>
              @else
                <span class="secondaire">—</span>
              @endif
            </td>

            <td>
              @if($b->statut === 'active')
                <span class="jeton jeton-ok"><span class="point" aria-hidden="true"></span>Active</span>
              @elseif($b->statut === 'en_attente')
                <span class="jeton jeton-alerte"><span class="point" aria-hidden="true"></span>En attente</span>
              @else
                <span class="jeton jeton-grave"><span class="point" aria-hidden="true"></span>Suspendue</span>
                @if($b->motif_suspension)
                  <div class="mini secondaire" style="max-width:14rem;margin-top:var(--s1)">
                    {{ $b->motif_suspension }}
                  </div>
                @endif
              @endif
            </td>

            <td style="text-align:right">
              <div class="rang-sm" style="justify-content:flex-end;gap:var(--s2)">
                @if($b->statut !== 'active')
                  <form method="POST" action="{{ route('admin.activer', $b) }}">
                    @csrf<button type="submit" class="btn btn-sm btn-ok">Activer</button>
                  </form>
                @else
                  <details>
                    <summary class="btn btn-sm btn-clair" style="list-style:none">Suspendre</summary>
                    <form method="POST" action="{{ route('admin.suspendre', $b) }}"
                          class="carte" style="position:absolute;right:var(--s6);z-index:10;
                                 margin-top:var(--s2);min-width:18rem;text-align:left;
                                 box-shadow:var(--e3)">
                      @csrf
                      <div class="champ">
                        <label for="motif{{ $b->id }}">Motif de la suspension</label>
                        <textarea id="motif{{ $b->id }}" name="motif" rows="3" required
                                  minlength="5" placeholder="Marchandise non conforme, taux de refus anormal…"></textarea>
                        <div class="aide">Le vendeur en est informé par courriel.</div>
                      </div>
                      <button type="submit" class="btn btn-sm btn-grave">Suspendre</button>
                    </form>
                  </details>
                @endif

                {{-- La mise en avant réservée aux enseignes démarchées. --}}
                <form method="POST" action="{{ route('admin.officielle', $b) }}">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-fantome">
                    {{ $b->officielle ? 'Retirer « officielle »' : 'Rendre officielle' }}
                  </button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" style="padding:0">
            @include('partials.vide', [
              'icone' => 'boutique',
              'titre' => $statutFiltre ? 'Aucune boutique dans cet état' : 'Aucune boutique',
              'texte' => $statutFiltre
                ? 'Changez de filtre pour voir les autres enseignes.'
                : 'La place de marché ne compte encore aucune enseigne inscrite.',
              'action' => $statutFiltre
                ? '<a href="' . route('admin.boutiques') . '" class="btn btn-clair">Toutes les boutiques</a>'
                : null,
            ])
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@if($boutiques->hasPages())
  <div style="margin-top:var(--s6)">{{ $boutiques->links() }}</div>
@endif

@endsection
