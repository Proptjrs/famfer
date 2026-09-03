@extends('layouts.app')
@section('titre', 'Administration')
@section('contenu')

<h1>Administration</h1>
<p class="sous">Vue d'ensemble de la place de marché, sur trente jours.</p>

{{-- Les deux invariants du grand livre sont affichés en permanence : s'ils
     tombent faux, c'est que l'argent ne se retrouve plus, et cela doit se voir
     avant qu'un vendeur ne s'en aperçoive. --}}
<div class="carte" style="margin-bottom:22px;border-left:4px solid
     {{ $chiffres['livre_equilibre'] && $chiffres['sequestre_justifie'] ? 'var(--vert)' : 'var(--rouge)' }}">
  <strong>Santé des comptes</strong>
  <div style="margin-top:8px;display:flex;gap:10px;flex-wrap:wrap">
    <span class="etiq {{ $chiffres['livre_equilibre'] ? 'etiq-vert' : 'etiq-rouge' }}">
      Grand livre {{ $chiffres['livre_equilibre'] ? 'équilibré' : 'DÉSÉQUILIBRÉ' }}
    </span>
    <span class="etiq {{ $chiffres['sequestre_justifie'] ? 'etiq-vert' : 'etiq-rouge' }}">
      Séquestre {{ $chiffres['sequestre_justifie'] ? 'justifié' : 'INJUSTIFIÉ' }}
    </span>
  </div>
</div>

<div class="grille g4" style="margin-bottom:26px">
  <div class="carte">
    <div class="chiffre mono" style="color:var(--vert)">
      {{ number_format($chiffres['commission_acquise'], 0, ',', ' ') }}
    </div>
    <div class="chiffre-note">francs de commission acquise</div>
  </div>
  <div class="carte">
    <div class="chiffre mono" style="color:var(--ambre)">
      {{ number_format($chiffres['sequestre_detenu'], 0, ',', ' ') }}
    </div>
    <div class="chiffre-note">retenus pour les acheteurs — pas un revenu</div>
  </div>
  <div class="carte">
    <div class="chiffre mono">{{ number_format($chiffres['du_aux_vendeurs'], 0, ',', ' ') }}</div>
    <div class="chiffre-note">dus aux vendeurs</div>
  </div>
  <div class="carte">
    <div class="chiffre mono">{{ $chiffres['taux_annulation_pour_cent'] }} %</div>
    <div class="chiffre-note">de commandes annulées</div>
  </div>
</div>

<div class="grille g4" style="margin-bottom:30px">
  <div class="carte">
    <div class="chiffre mono">{{ $chiffres['vendeurs_verifies'] }}</div>
    <div class="chiffre-note">vendeurs vérifiés</div>
  </div>
  <div class="carte">
    <div class="chiffre mono">{{ $chiffres['commandes'] }}</div>
    <div class="chiffre-note">commandes</div>
  </div>
  <div class="carte">
    <div class="chiffre mono">{{ number_format($chiffres['volume_traite'], 0, ',', ' ') }}</div>
    <div class="chiffre-note">francs de volume traité</div>
  </div>
  <div class="carte">
    <div class="chiffre mono">{{ number_format($chiffres['frais_operateur'], 0, ',', ' ') }}</div>
    <div class="chiffre-note">francs de frais d'opérateur</div>
  </div>
</div>

<h2>Vendeurs à vérifier</h2>
@forelse($aVerifier as $v)
  <div class="carte" style="margin-bottom:12px">
    <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:baseline">
      <strong>{{ $v->raison_sociale }}</strong>
      <span style="color:var(--gris)">
        {{ $v->commune }} · NINEA {{ $v->ninea ?? '—' }} · {{ $v->telephone }}
      </span>
    </div>
    <p style="color:var(--gris);font-size:.86rem;margin-top:6px">
      Tant qu'il n'est pas vérifié, ce vendeur n'apparaît chez aucun acheteur.
    </p>
    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
      <form method="POST" action="{{ route('admin.verifier', $v) }}">
        @csrf <button class="btn btn-sm btn-vert">Vérifier</button>
      </form>
      <form method="POST" action="{{ route('admin.refuser.vendeur', $v) }}"
            style="display:flex;gap:8px;align-items:center">
        @csrf
        <input name="motif" placeholder="Motif du refus" required
               style="padding:6px 10px;border:1px solid var(--bord);border-radius:8px">
        <button class="btn btn-sm btn-clair">Refuser</button>
      </form>
    </div>
  </div>
@empty
  <div class="carte vide">Aucune demande en attente.</div>
@endforelse

<h2 style="margin-top:28px">Litiges à trancher</h2>
@forelse($litiges as $l)
  <div class="carte" style="margin-bottom:12px">
    <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:baseline">
      <strong>{{ $l->commande->reference }}</strong>
      <span class="etiq etiq-rouge">{{ str_replace('_', ' ', $l->motif) }}</span>
      <span style="color:var(--gris)">
        {{ $l->auteur->name }} contre {{ $l->commande->vendeur->raison_sociale }}
      </span>
      <span class="mono" style="margin-left:auto;font-weight:700">
        {{ number_format($l->commande->montant_total, 0, ',', ' ') }} F retenus
      </span>
    </div>
    <p style="margin-top:8px">{{ $l->description }}</p>

    <form method="POST" action="{{ route('admin.trancher', $l) }}" style="margin-top:12px">
      @csrf
      <div class="champ">
        <label>Décision motivée</label>
        <textarea name="decision" rows="2" required minlength="10"></textarea>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button name="sens" value="acheteur" class="btn btn-sm btn-rouge">
          Rembourser l'acheteur
        </button>
        <button name="sens" value="vendeur" class="btn btn-sm btn-vert">
          Payer le vendeur
        </button>
      </div>
      <p style="color:var(--gris);font-size:.84rem;margin-top:8px">
        Rembourser l'acheteur n'engendre aucune commission ; les frais de
        l'opérateur restent à la charge de la plateforme.
      </p>
    </form>
  </div>
@empty
  <div class="carte vide">Aucun litige ouvert.</div>
@endforelse

{{-- Les maisons vérifiées, et le taux qu'on négocie avec chacune.
     La colonne « taux_commission_pour_mille » existait par vendeur depuis le
     début, mais rien ne permettait de la changer : tout le monde payait 8 %,
     alors que la table prévoyait le contraire. --}}
<h2 style="margin-top:34px">Les quincailleries et leur commission</h2>
<div class="carte tableau-large"><table>
  <tr>
    <th>Maison</th><th>Commune</th><th>Note</th>
    <th>Compte de versement</th><th style="width:210px">Commission</th>
  </tr>
  @forelse($maisons as $m)
    <tr>
      <td>
        <a href="{{ route('vendeur.public', $m) }}">{{ $m->raison_sociale }}</a>
        @if($m->statut === 'suspendu')<span class="etiq etiq-rouge">suspendue</span>@endif
      </td>
      <td style="color:var(--gris)">{{ $m->commune }}</td>
      <td>
        @if($m->nombre_evaluations)
          <span class="mono">{{ $m->noteSurCinq() }}</span>
          <span style="color:var(--gris);font-size:.82rem">({{ $m->nombre_evaluations }})</span>
        @else
          <span style="color:var(--gris);font-size:.82rem">—</span>
        @endif
      </td>
      <td style="font-size:.84rem">
        @if($m->peutEtreVire())
          {{ $m->compteDeVersement() }}
        @else
          {{-- Sans destination, aucun virement ne peut partir : l'administration
               doit pouvoir le repérer avant que le vendeur ne s'en plaigne. --}}
          <span style="color:var(--forge)">aucun — virements impossibles</span>
        @endif
      </td>
      <td>
        <form method="POST" action="{{ route('admin.commission', $m) }}"
              style="display:flex;gap:6px;align-items:center">
          @csrf @method('PUT')
          <input name="taux_pour_cent" value="{{ $m->taux_commission_pour_mille / 10 }}"
                 style="width:70px;padding:6px 8px;border:1px solid var(--bord);border-radius:6px"
                 inputmode="decimal">
          <span style="color:var(--gris)">%</span>
          <button class="btn btn-sm btn-clair">Fixer</button>
        </form>
      </td>
    </tr>
  @empty
    <tr><td colspan="5" style="color:var(--gris)">Aucune maison vérifiée.</td></tr>
  @endforelse
</table></div>

<div style="margin-top:18px">{{ $maisons->links() }}</div>

<p style="color:var(--gris);font-size:.86rem;margin-top:14px;max-width:72ch">
  Un taux modifié ne vaut que pour l'avenir : chaque commande fige le sien à sa
  création, et les commandes déjà passées ne sont pas recalculées.
</p>

@endsection
