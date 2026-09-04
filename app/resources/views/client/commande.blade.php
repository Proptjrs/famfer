@extends('layouts.app')
@section('titre', 'Commande ' . $commande->reference)
@section('contenu')

<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
  <h1 style="font-size:1.3rem">Commande {{ $commande->reference }}</h1>
  @include('partials.etat', ['etat' => $commande->etat])
  @if($commande->annulableParLeClient())
    <form method="POST" action="{{ route('commande.annuler', $commande) }}" style="margin-left:auto">
      @csrf <button class="btn btn-sm btn-clair">Annuler la commande</button>
    </form>
  @endif
</div>

{{-- Le suivi, étape par étape : c'est la première chose qu'un client cherche
     après avoir commandé. Les états d'échec n'y figurent pas — ils sortent du
     parcours et méritent une phrase, pas une case. --}}
@php
  $etapes = ['en_preparation' => 'En préparation', 'expediee' => 'Expédiée',
             'en_livraison' => 'En livraison', 'livree' => 'Livrée'];
  $rangs = array_keys($etapes);
  $actuel = array_search($commande->etat, $rangs, true);
@endphp

@if($actuel !== false)
  <div class="carte" style="margin-bottom:14px;display:flex;gap:6px;flex-wrap:wrap">
    @foreach($etapes as $cle => $mot)
      @php $rang = array_search($cle, $rangs, true); @endphp
      <div style="flex:1 1 120px;text-align:center;padding:10px 6px;border-radius:var(--r);
                  background:{{ $rang <= $actuel ? 'var(--vert-pale)' : 'var(--fond)' }};
                  color:{{ $rang <= $actuel ? 'var(--vert)' : 'var(--gris)' }};
                  font-weight:600;font-size:.84rem">
        {{ $rang <= $actuel ? '✓ ' : '' }}{{ $mot }}
      </div>
    @endforeach
  </div>
@elseif($commande->motif)
  <div class="avis avis-err">{{ $commande->motif }}</div>
@endif

{{-- Le code de remise.

     C'est la piece maitresse du paiement a la livraison. Le vendeur ne peut
     pas clore la commande sans ce code, que le client ne dicte qu'en recevant
     le colis. Sans lui, le commercant declarerait seul une livraison dont il
     est le beneficiaire. --}}
@if($commande->code_livraison && in_array($commande->etat, ['expediee', 'en_livraison'], true))
  <div class="carte" style="margin-bottom:14px;border:1px solid var(--orange);
              display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <div>
      <div style="color:var(--gris);font-size:.82rem">Votre code de remise</div>
      <div class="mono" style="font-size:2rem;font-weight:800;letter-spacing:.14em">
        {{ $commande->code_livraison }}
      </div>
    </div>
    <div style="flex:1 1 260px;font-size:.88rem;color:var(--gris)">
      Ne le donnez au livreur <strong>qu'au moment ou vous recevez le colis et
      reglez les {{ number_format($commande->total, 0, ',', ' ') }} F</strong>.
      C'est ce code qui prouve que la livraison a eu lieu.
    </div>
  </div>
@endif

{{-- Les deux recours du client. Le premier, parce que le vendeur peut oublier
     de cloturer ; le second, parce qu'il peut mentir. --}}
@if($commande->confirmableParLeClient())
  <form method="POST" action="{{ route('commande.confirmer', $commande) }}"
        style="margin-bottom:14px">
    @csrf
    <button class="btn">J'ai recu ma commande et je l'ai payee</button>
    <span style="color:var(--gris);font-size:.85rem;margin-left:8px">
      Votre confirmation cloture la vente, meme si le vendeur ne l'a pas fait.
    </span>
  </form>
@endif

@if($commande->contestableParLeClient())
  <details class="carte" style="margin-bottom:14px">
    <summary style="cursor:pointer;font-weight:600">
      @if($commande->etat === 'refusee')
        Ce refus est faux : j'ai bien recu et paye cette commande
      @else
        Je n'ai jamais recu cette commande
      @endif
    </summary>
    <form method="POST" action="{{ route('commande.contester', $commande) }}"
          style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
      @csrf
      <input name="motif" required minlength="10" maxlength="300"
             placeholder="Expliquez ce qui s'est passe (10 caracteres minimum)"
             style="flex:1 1 320px;padding:8px 10px;border:1px solid var(--bord);
                    border-radius:var(--r)">
      <button class="btn btn-clair">Ouvrir un litige</button>
    </form>
    <p style="color:var(--gris);font-size:.84rem;margin:8px 0 0">
      L'administration de FamFer examinera les deux versions et tranchera.
    </p>
  </details>
@endif

@if($commande->enLitige())
  <div class="avis" style="background:var(--orange-pale);color:var(--orange-fonce)">
    <strong>Litige en cours d'examen.</strong>
    Ouvert par {{ $commande->litige_par === 'client' ? 'vous' : 'le vendeur' }}
    le {{ $commande->litige_le?->format('d/m/Y') }} :
    {{ $commande->litige_motif }}
  </div>
@endif

<div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
  <div style="flex:1 1 400px;min-width:0">

    <div class="bloc">
      <div class="bloc-tete"><h2>Les articles</h2></div>
      <div class="bloc-corps">
        @foreach($commande->lignes as $ligne)
          <div style="display:flex;gap:12px;align-items:center;padding:10px 0;
                      border-bottom:1px solid var(--bord);flex-wrap:wrap">
            <div style="flex:0 0 60px;height:60px;background:var(--fond);border-radius:var(--r);
                        display:flex;align-items:center;justify-content:center">
              {{-- Le produit a pu être retiré depuis : on retombe sur le
                   dessin plutôt que d'afficher un cadre vide. --}}
              @if($ligne->produit)
                @include('partials.image', ['p' => $ligne->produit, 'taille' => 46])
              @else
                @include('partials.dessin', ['dessin' => 'defaut', 'taille' => 46])
              @endif
            </div>
            <div style="flex:1 1 180px;min-width:0">
              <div style="font-weight:600">{{ $ligne->nom_produit }}</div>
              <div style="color:var(--gris);font-size:.84rem">
                {{ $ligne->quantite }} × {{ number_format($ligne->prix_unitaire, 0, ',', ' ') }} F
              </div>
            </div>
            <strong class="mono">{{ number_format($ligne->montant, 0, ',', ' ') }} F</strong>
          </div>
        @endforeach
      </div>
    </div>

    @if($aNoter->isNotEmpty())
      <div class="bloc">
        <div class="bloc-tete"><h2>Donnez votre avis</h2></div>
        <div class="bloc-corps">
          @foreach($aNoter as $ligne)
            <form method="POST" action="{{ route('commande.noter', $commande) }}"
                  style="padding-bottom:14px;margin-bottom:14px;border-bottom:1px solid var(--bord)">
              @csrf
              <input type="hidden" name="produit_id" value="{{ $ligne->produit_id }}">
              <div style="font-weight:600;margin-bottom:8px">{{ $ligne->nom_produit }}</div>
              <div class="grille g2">
                <div class="champ"><label>Votre note</label>
                  <select name="note" required>
                    <option value="5">5 étoiles — excellent</option>
                    <option value="4">4 étoiles — bien</option>
                    <option value="3">3 étoiles — correct</option>
                    <option value="2">2 étoiles — décevant</option>
                    <option value="1">1 étoile — mauvais</option>
                  </select></div>
                <div class="champ"><label>Titre <span style="color:var(--gris)">(facultatif)</span></label>
                  <input name="titre" maxlength="160"></div>
              </div>
              <div class="champ"><label>Votre avis</label>
                <textarea name="commentaire" rows="2" maxlength="1500"></textarea></div>
              <button class="btn btn-sm">Publier mon avis</button>
            </form>
          @endforeach
        </div>
      </div>
    @endif
  </div>

  <div class="carte" style="flex:0 0 292px">
    <h2 style="margin-bottom:12px">Récapitulatif</h2>

    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
      <span>Sous-total</span>
      <span class="mono">{{ number_format($commande->sous_total, 0, ',', ' ') }} F</span>
    </div>
    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
      <span>Livraison</span>
      <span class="mono">
        @if($commande->frais_livraison === 0)
          offerte
        @else
          {{ number_format($commande->frais_livraison, 0, ',', ' ') }} F
        @endif
      </span>
    </div>
    <hr style="border:0;border-top:1px solid var(--bord);margin:10px 0">
    <div style="display:flex;justify-content:space-between;font-size:1.1rem;font-weight:800">
      <span>Total</span>
      <span class="mono">{{ number_format($commande->total, 0, ',', ' ') }} F</span>
    </div>

    <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--bord);font-size:.86rem">
      <strong>Paiement</strong><br>
      <span style="color:var(--gris-fonce)">
        @if($commande->paiement === 'livraison')
          À la livraison, en espèces
        @else
          {{ strtoupper($commande->paiement) }}
        @endif
      </span>
      @if($commande->paye)
        <br><span style="color:var(--vert);font-weight:600">Réglée</span>
      @endif

      <div style="margin-top:10px"><strong>Livraison</strong><br>
        <span style="color:var(--gris-fonce)">
          {{ $commande->destinataire }}<br>
          {{ $commande->telephone }}<br>
          {{ $commande->adresse_livraison }}
        </span>
      </div>
    </div>
  </div>
</div>

@endsection
