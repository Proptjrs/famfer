@extends('layouts.app')
@section('titre', 'Mes commandes')
@section('contenu')
@php
$libelles = [
 'en_attente_paiement' => ['En attente de paiement', 'etiq-gris'],
 'payee' => ['Payée — à accepter', 'etiq-ambre'],
 'acceptee' => ['Acceptée', 'etiq-vert'],
 'prete' => ['Prête', 'etiq-vert'],
 'en_livraison' => ['En livraison', 'etiq-vert'],
 'receptionnee' => ['Reçue', 'etiq-vert'],
 'soldee' => ['Terminée et payée', 'etiq-gris'],
 'en_litige' => ['Litige', 'etiq-rouge'],
 'annulee' => ['Annulée', 'etiq-gris'],
 'expiree' => ['Expirée', 'etiq-gris'],
 'remboursee' => ['Remboursée', 'etiq-gris'],
];
@endphp

<h1>Mes commandes</h1>
<p class="sous">
  Tout ce qui est passé par votre comptoir — y compris ce qui n'a pas abouti.
</p>

{{-- Le compte par état sert autant que le filtre : il dit d'un coup d'œil
     combien de commandes attendent une action, et combien ont échoué. --}}
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:22px">
  <a href="{{ route('vendeur.commandes') }}"
     class="btn btn-sm {{ $etatFiltre ? 'btn-clair' : '' }}">
    Toutes <span style="opacity:.7">{{ $parEtat->sum() }}</span>
  </a>
  @foreach($libelles as $cle => [$mot, $classe])
    @continue(! isset($parEtat[$cle]))
    <a href="{{ route('vendeur.commandes', ['etat' => $cle]) }}"
       class="btn btn-sm {{ $etatFiltre === $cle ? '' : 'btn-clair' }}">
      {{ $mot }} <span style="opacity:.7">{{ $parEtat[$cle] }}</span>
    </a>
  @endforeach
</div>

@forelse($liste as $c)
  @php [$mot, $classe] = $libelles[$c->etat] ?? [$c->etat, 'etiq-gris']; @endphp
  <div class="carte" style="margin-bottom:12px">
    <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:baseline">
      <strong>{{ $c->reference }}</strong>
      <span class="etiq {{ $classe }}">{{ $mot }}</span>
      <span style="color:var(--gris);font-size:.88rem">
        {{ $c->acheteur->utilisateur->name }} ·
        {{ $c->created_at->translatedFormat('j M Y') }}
      </span>
      <span class="mono" style="margin-left:auto;font-weight:700">
        {{ number_format($c->montant_total, 0, ',', ' ') }} F
      </span>
    </div>

    <div style="color:var(--gris);font-size:.88rem;margin-top:6px">
      @foreach($c->lignes as $l)
        {{ $l->quantite_affichee }} {{ $l->unite_affichee }} —
        {{ $l->offre->article->designation }}<br>
      @endforeach
    </div>

    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:10px;
                padding-top:10px;border-top:1px solid var(--bord);font-size:.86rem">
      <span style="color:var(--gris)">
        Commission {{ $c->taux_commission_pour_mille / 10 }} % ·
        <span class="mono" style="color:var(--forge)">
          − {{ number_format($c->montant_commission, 0, ',', ' ') }} F
        </span>
      </span>
      <span style="color:var(--gris)">
        Vous revient ·
        <span class="mono">{{ number_format($c->montantVendeur(), 0, ',', ' ') }} F</span>
      </span>
      @if($c->frais_livraison > 0)
        <span style="color:var(--gris)">
          dont livraison
          <span class="mono">{{ number_format($c->frais_livraison, 0, ',', ' ') }} F</span>
        </span>
      @endif
      @if($c->motif_annulation)
        <span style="color:var(--rouge)">{{ $c->motif_annulation }}</span>
      @endif
      @if($c->evaluation)
        <span style="color:var(--forge);margin-left:auto">
          {{ str_repeat('★', $c->evaluation->note) }}{{ str_repeat('☆', 5 - $c->evaluation->note) }}
        </span>
      @endif
    </div>
  </div>
@empty
  <div class="carte vide">
    @if($etatFiltre)
      Aucune commande dans cet état.
      <a href="{{ route('vendeur.commandes') }}">Voir toutes</a>
    @else
      Aucune commande pour l'instant.
    @endif
  </div>
@endforelse

<div style="margin-top:22px">{{ $liste->links() }}</div>

<p style="margin-top:22px;display:flex;gap:20px;flex-wrap:wrap">
  <a href="{{ route('vendeur.tableau') }}">← Tableau de bord</a>
  <a href="{{ route('vendeur.argent') }}">Mon argent →</a>
</p>

@endsection
