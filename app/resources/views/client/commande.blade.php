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
              @include('partials.dessin', [
                'dessin' => $ligne->produit?->dessin ?? 'defaut', 'taille' => 46,
              ])
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
