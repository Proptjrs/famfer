@extends('layouts.app')
@section('titre', 'Mon commerce')
@section('contenu')

<h1>{{ $vendeur->raison_sociale }}</h1>
<p class="sous">{{ $vendeur->commune }} · commission {{ $vendeur->taux_commission_pour_mille / 10 }} %</p>

<div class="grille g4" style="margin-bottom:26px">
  <div class="carte">
    <div class="chiffre mono">{{ number_format($chiffres['chiffre_affaires'], 0, ',', ' ') }}</div>
    <div class="chiffre-note">francs vendus sur 30 jours</div>
  </div>
  <div class="carte">
    <div class="chiffre mono" style="color:var(--vert)">{{ number_format($chiffres['net_percu'], 0, ',', ' ') }}</div>
    <div class="chiffre-note">net, commission déduite</div>
  </div>
  <div class="carte">
    <div class="chiffre mono" style="color:var(--forge)">{{ number_format($chiffres['reste_du'], 0, ',', ' ') }}</div>
    <div class="chiffre-note">que FamFer vous doit</div>
  </div>
  <div class="carte">
    <div class="chiffre mono">{{ $chiffres['litiges_ouverts'] }}</div>
    <div class="chiffre-note">litige(s) ouvert(s)</div>
  </div>
</div>

@if($chiffres['reste_du'] > 0)
  <form method="POST" action="{{ route('vendeur.reversement') }}" class="carte" style="margin-bottom:26px">
    @csrf
    <h2>Demander mon virement</h2>
    <p style="color:var(--gris);margin-bottom:12px">
      Les commandes reçues sont d'abord soldées, puis le total vous est viré en une fois.
      Un litige ouvert gèle la totalité.
    </p>
    <button class="btn btn-vert">Virer {{ number_format($chiffres['reste_du'], 0, ',', ' ') }} F</button>
  </form>
@endif

<h2>Commandes à traiter</h2>
@forelse($aTraiter as $c)
  <div class="carte" style="margin-bottom:12px">
    <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:baseline">
      <strong>{{ $c->reference }}</strong>
      <span class="etiq etiq-ambre">{{ $c->etat }}</span>
      <span style="color:var(--gris)">{{ $c->acheteur->utilisateur->name }}</span>
      <span class="mono" style="margin-left:auto;font-weight:700">
        {{ number_format($c->montant_total, 0, ',', ' ') }} F
      </span>
    </div>

    <div style="color:var(--gris);font-size:.88rem;margin-top:6px">
      @foreach($c->lignes as $l)
        {{ $l->quantite_affichee }} {{ $l->unite_affichee }} — {{ $l->offre->article->designation }}<br>
      @endforeach
      {{ $c->mode_remise === 'livraison' ? 'Livraison : ' . $c->adresse_livraison : 'Retrait au magasin' }}
    </div>

    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
      @if($c->etat === 'payee')
        <form method="POST" action="{{ route('vendeur.accepter', $c) }}">
          @csrf <button class="btn btn-sm btn-vert">Accepter</button>
        </form>
        <form method="POST" action="{{ route('vendeur.refuser', $c) }}">
          @csrf <button class="btn btn-sm btn-clair">Refuser</button>
        </form>
      @elseif($c->etat === 'acceptee')
        <form method="POST" action="{{ route('vendeur.prete', $c) }}">
          @csrf <button class="btn btn-sm">Marquer prête</button>
        </form>
      @elseif($c->etat === 'prete')
        <form method="POST" action="{{ route('vendeur.remettre', $c) }}">
          @csrf <button class="btn btn-sm btn-vert">Remettre la marchandise</button>
        </form>
      @endif
    </div>
  </div>
@empty
  <div class="carte vide">Rien à traiter.</div>
@endforelse

@if($dormants)
  <h2 style="margin-top:28px">Ce qui dort depuis 60 jours</h2>
  <div class="carte">
    <div class="tableau-large">
      <table>
        <tr><th>Article</th><th style="text-align:right">En stock</th></tr>
        @foreach($dormants as $d)
          <tr>
            <td>{{ $d['article'] }}</td>
            <td class="mono" style="text-align:right">
              {{ number_format($d['quantite_pivot'] / 1000, 0, ',', ' ') }} kg
            </td>
          </tr>
        @endforeach
      </table>
    </div>
    <p style="color:var(--gris);font-size:.86rem;margin-top:10px">
      Du fer immobilisé, c'est de la trésorerie qui dort.
    </p>
  </div>
@endif

<p style="margin-top:24px;display:flex;gap:20px;flex-wrap:wrap">
  <a href="{{ route('vendeur.offres') }}">Gérer mes offres et mon stock →</a>
  <a href="{{ route('vendeur.commandes') }}">Toutes mes commandes →</a>
  <a href="{{ route('vendeur.argent') }}">Mon argent et mes virements →</a>
</p>
@endsection
