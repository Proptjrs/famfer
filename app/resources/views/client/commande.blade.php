@extends('layouts.app')
@section('titre', 'Commande ' . $commande->reference)
@section('contenu')

@php
  $etapes = ['en_preparation' => 'Enregistrée', 'expediee' => 'Expédiée',
             'en_livraison' => 'En livraison', 'livree' => 'Livrée'];
  $rangs = array_keys($etapes);
  $actuel = array_search($commande->etat, $rangs, true);
@endphp

@include('partials.entete', [
  'titre' => 'Commande ' . $commande->reference,
  'sous' => $commande->created_at->translatedFormat('\\P\\a\\s\\s\\é\\e \\l\\e j F Y \\à H\\hi')
    . ' · ' . $commande->lignes->count() . ' article(s)',
  'fil' => [
    ['libelle' => 'Mes commandes', 'url' => route('mes-commandes')],
    ['libelle' => $commande->reference],
  ],
  'actions' => $commande->annulableParLeClient()
    ? '<form method="POST" action="' . route('commande.annuler', $commande) . '">'
      . csrf_field() . '<button class="btn btn-clair">Annuler la commande</button></form>'
    : null,
])

<div class="rang" style="margin-bottom:var(--s5)">
  @include('partials.etat', ['etat' => $commande->etat])
  @if($commande->paye)
    <span class="jeton jeton-ok"><span class="point" aria-hidden="true"></span>Réglée</span>
  @endif
</div>

{{-- Le suivi, étape par étape : c'est la première chose qu'un client cherche
     après avoir commandé. Les états d'échec n'y figurent pas — ils sortent du
     parcours et méritent une phrase, pas une case. --}}
@if($actuel !== false)
  <div class="bloc" style="margin-bottom:var(--s5)">
    <div class="bloc-corps">
      <ol class="etapes">
        @foreach($etapes as $cle => $mot)
          @php $rang = array_search($cle, $rangs, true); @endphp
          <li class="{{ $rang < $actuel ? 'faite' : ($rang === $actuel ? 'ici' : '') }}">
            <span>{{ $mot }}</span>
            @if($rang === 0 && $commande->created_at)
              <span class="mini secondaire">{{ $commande->created_at->format('d/m') }}</span>
            @elseif($cle === 'expediee' && $commande->expediee_le)
              <span class="mini secondaire">{{ $commande->expediee_le->format('d/m') }}</span>
            @elseif($cle === 'livree' && $commande->livree_le)
              <span class="mini secondaire">{{ $commande->livree_le->format('d/m') }}</span>
            @endif
          </li>
        @endforeach
      </ol>
    </div>
  </div>
@elseif($commande->motif)
  <div class="message message-grave" style="margin-bottom:var(--s5)">
    @include('partials.symbole', ['nom' => 'alerte', 'taille' => 18])
    <div>
      <strong>
        @if($commande->etat === 'refusee') Commande refusée à la livraison.
        @elseif($commande->etat === 'annulee') Commande annulée.
        @elseif($commande->etat === 'retournee') Retour enregistré.
        @else {{ ucfirst($commande->etat) }}.
        @endif
      </strong>
      {{ $commande->motif }}
    </div>
  </div>
@endif

@if($commande->enLitige())
  <div class="message message-alerte" style="margin-bottom:var(--s5)">
    @include('partials.symbole', ['nom' => 'balance', 'taille' => 18])
    <div>
      <strong>Litige en cours d'examen.</strong>
      Ouvert par {{ $commande->litige_par === 'client' ? 'vous' : 'le vendeur' }}
      le {{ $commande->litige_le?->translatedFormat('j F Y') }} :
      « {{ $commande->litige_motif }} »
      <div class="petit" style="margin-top:var(--s1)">
        L'administration de FamFer examine les deux versions et tranchera.
        Aucune commission n'est due tant que le litige dure.
      </div>
    </div>
  </div>
@endif

<div class="deux-colonnes">
  <div class="pile-lg">

    {{-- Le code de remise.

         C'est la pièce maîtresse du paiement à la livraison. Le vendeur ne peut
         pas clore la commande sans ce code, que le client ne dicte qu'en
         recevant le colis. Sans lui, le commerçant déclarerait seul une
         livraison dont il est le bénéficiaire.

         Il passe avant les articles : quand le livreur sonne, c'est la seule
         chose que l'acheteur cherche sur cet écran. --}}
    @if($commande->code_livraison && in_array($commande->etat, ['expediee', 'en_livraison'], true))
      <div class="bloc" style="border-color:var(--brand);border-width:2px">
        <div class="bloc-tete" style="background:var(--brand-soft);border-color:var(--brand-line)">
          @include('partials.symbole', ['nom' => 'cadenas', 'taille' => 18])
          <h2>Votre code de remise</h2>
        </div>
        <div class="bloc-corps rang" style="gap:var(--s6)">
          <div>
            <div class="chiffre" style="font-size:var(--t-4xl);font-weight:600;
                        letter-spacing:.18em;line-height:1.1;color:var(--ink)">
              {{ $commande->code_livraison }}
            </div>
          </div>
          <p style="flex:1 1 16rem;color:var(--ink-2)">
            Ne le donnez au livreur <strong>qu'au moment où vous recevez le colis
            et réglez les {{ number_format($commande->total, 0, ',', ' ') }} F</strong>.
            C'est lui qui prouve que la livraison a eu lieu — sans lui, le vendeur
            ne peut pas clore la vente.
          </p>
        </div>
        <div class="bloc-pied">
          Ce code vous a aussi été envoyé par courriel et par SMS.
        </div>
      </div>
    @endif

    {{-- Les deux recours du client. Le premier parce que le vendeur peut oublier
         de clôturer ; le second parce qu'il peut mentir. --}}
    @if($commande->confirmableParLeClient())
      <div class="bloc">
        <div class="bloc-tete"><h2>Avez-vous reçu votre commande ?</h2></div>
        <div class="bloc-corps pile">
          <p class="secondaire">
            Votre confirmation clôt la vente même si le vendeur ne l'a pas fait,
            et vous permet de noter les articles reçus.
          </p>
          <div class="rang-sm">
            <form method="POST" action="{{ route('commande.confirmer', $commande) }}">
              @csrf
              <button type="submit" class="btn">
                @include('partials.symbole', ['nom' => 'coche', 'taille' => 17])
                Oui, je l'ai reçue et payée
              </button>
            </form>
          </div>
        </div>
      </div>
    @endif

    @if($commande->contestableParLeClient())
      <details class="bloc">
        <summary class="bloc-tete" style="cursor:pointer;list-style:none">
          @include('partials.symbole', ['nom' => 'balance', 'taille' => 17])
          <h2>
            @if($commande->etat === 'refusee')
              Ce refus est faux : j'ai bien reçu et payé
            @else
              Je n'ai jamais reçu cette commande
            @endif
          </h2>
          <span class="chevron pousse">
            @include('partials.symbole', ['nom' => 'chevron', 'taille' => 14])
          </span>
        </summary>
        <div class="bloc-corps pile">
          <form method="POST" action="{{ route('commande.contester', $commande) }}" class="pile">
            @csrf
            <div class="champ">
              <label for="motif">Que s'est-il passé ?</label>
              <textarea id="motif" name="motif" required minlength="10" maxlength="300"
                        rows="3" placeholder="Décrivez ce qui s'est passé : date, heure, ce que vous avez remis au livreur…"
                        @error('motif') aria-invalid="true" @enderror></textarea>
              <div class="aide">Dix caractères au minimum. Soyez précis : c'est ce
              texte que l'administration lira.</div>
              @error('motif')<div class="erreur">{{ $message }}</div>@enderror
            </div>
            <div>
              <button type="submit" class="btn btn-clair">Ouvrir un litige</button>
            </div>
          </form>
          <p class="petit secondaire">
            L'administration de FamFer examinera les deux versions et tranchera.
            La contestation reste ouverte
            {{ \App\Services\Veille::JOURS_DE_CONTESTATION }} jours après la
            clôture de la commande.
          </p>
        </div>
      </details>
    @endif

    <div class="bloc">
      <div class="bloc-tete">
        <h2>Les articles</h2>
        <span class="sous">{{ $commande->lignes->count() }} ligne(s)</span>
      </div>
      <div class="bloc-corps serre">
        @foreach($commande->lignes as $ligne)
          <div class="rang" style="padding:var(--s3) var(--s5);
               {{ ! $loop->last ? 'border-bottom:1px solid var(--line)' : '' }}">
            <div style="flex:0 0 3.5rem;height:3.5rem;background:var(--surface-2);
                        border-radius:var(--r-sm);display:grid;place-items:center;
                        overflow:hidden">
              {{-- Le produit a pu être retiré depuis : on retombe sur le dessin
                   plutôt que d'afficher un cadre vide. --}}
              @if($ligne->produit)
                @include('partials.image', ['p' => $ligne->produit, 'taille' => 44])
              @else
                @include('partials.dessin', ['dessin' => 'defaut', 'taille' => 44])
              @endif
            </div>

            <div style="flex:1 1 12rem;min-width:0">
              <div style="font-weight:650">{{ $ligne->nom_produit }}</div>
              <div class="petit secondaire chiffre">
                {{ $ligne->quantite }} × {{ number_format($ligne->prix_unitaire, 0, ',', ' ') }} F
              </div>
            </div>

            <strong class="chiffre pousse">
              {{ number_format($ligne->montant, 0, ',', ' ') }} F
            </strong>
          </div>
        @endforeach
      </div>
    </div>

    @if($aNoter->isNotEmpty())
      <div class="bloc">
        <div class="bloc-tete">
          <h2>Donnez votre avis</h2>
          <span class="sous">{{ $aNoter->count() }} article(s) à noter</span>
        </div>
        <div class="bloc-corps pile-lg">
          @foreach($aNoter as $ligne)
            <form method="POST" action="{{ route('commande.noter', $commande) }}" class="pile"
                  style="{{ ! $loop->last ? 'padding-bottom:var(--s6);border-bottom:1px solid var(--line)' : '' }}">
              @csrf
              <input type="hidden" name="produit_id" value="{{ $ligne->produit_id }}">
              <div style="font-weight:650">{{ $ligne->nom_produit }}</div>

              <div class="grille g2">
                <div class="champ">
                  <label for="note{{ $ligne->produit_id }}">Votre note</label>
                  <select id="note{{ $ligne->produit_id }}" name="note" required>
                    <option value="5">★★★★★ — excellent</option>
                    <option value="4">★★★★☆ — bien</option>
                    <option value="3">★★★☆☆ — correct</option>
                    <option value="2">★★☆☆☆ — décevant</option>
                    <option value="1">★☆☆☆☆ — mauvais</option>
                  </select>
                </div>
                <div class="champ">
                  <label for="titre{{ $ligne->produit_id }}">
                    Titre <span class="facultatif">— facultatif</span>
                  </label>
                  <input id="titre{{ $ligne->produit_id }}" name="titre" maxlength="160"
                         placeholder="Conforme, livré vite…">
                </div>
              </div>

              <div class="champ">
                <label for="com{{ $ligne->produit_id }}">Votre avis</label>
                <textarea id="com{{ $ligne->produit_id }}" name="commentaire"
                          rows="3" maxlength="1500"
                          placeholder="La qualité, la conformité à la description, l'état à la livraison…"></textarea>
                <div class="aide">
                  Votre avis aide les prochains acheteurs — et seuls ceux qui ont
                  reçu le produit peuvent en laisser un.
                </div>
              </div>

              <div><button type="submit" class="btn btn-sm">Publier mon avis</button></div>
            </form>
          @endforeach
        </div>
      </div>
    @endif
  </div>

  <div class="pile-lg colonne-fixe">

    <div class="bloc">
      <div class="bloc-tete"><h2>Récapitulatif</h2></div>
      <div class="bloc-corps pile-sm">
        <div class="rang-serre">
          <span class="secondaire">Sous-total</span>
          <span class="chiffre pousse">{{ number_format($commande->sous_total, 0, ',', ' ') }} F</span>
        </div>
        <div class="rang-serre">
          <span class="secondaire">Livraison</span>
          <span class="chiffre pousse">
            @if($commande->frais_livraison === 0)
              <span style="color:var(--ok-ink);font-weight:650">offerte</span>
            @else
              {{ number_format($commande->frais_livraison, 0, ',', ' ') }} F
            @endif
          </span>
        </div>
        <hr style="margin-block:var(--s2)">
        <div class="rang-serre">
          <strong style="font-size:var(--t-md)">Total</strong>
          <strong class="chiffre pousse" style="font-size:var(--t-xl)">
            {{ number_format($commande->total, 0, ',', ' ') }} F
          </strong>
        </div>
      </div>
      <div class="bloc-pied">
        @if($commande->paye)
          Réglée en espèces à la livraison.
        @else
          À régler au livreur, en espèces, à la réception.
        @endif
      </div>
    </div>

    <div class="bloc">
      <div class="bloc-tete"><h2>Livraison</h2></div>
      <div class="bloc-corps pile-sm">
        <div>
          <div class="petit secondaire">Destinataire</div>
          <div style="font-weight:650">{{ $commande->destinataire }}</div>
        </div>
        <div>
          <div class="petit secondaire">Téléphone</div>
          <div class="chiffre">{{ $commande->telephone }}</div>
        </div>
        <div>
          <div class="petit secondaire">Adresse</div>
          <div style="color:var(--ink-2)">{{ $commande->adresse_livraison }}</div>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
