@extends('layouts.app')
@section('titre', 'Mon argent')
@section('contenu')

<h1>Mon argent</h1>
<p class="sous">
  FamFer encaisse pour votre compte et vous reverse. Cette page dit exactement
  où en est chaque franc.
</p>

<div class="grille g3" style="margin-bottom:24px">
  <div class="carte">
    <div class="chiffre mono">{{ number_format($solde, 0, ',', ' ') }} F</div>
    <div class="chiffre-note">Disponible pour virement</div>
  </div>
  <div class="carte">
    <div class="chiffre mono">{{ $aSolder }}</div>
    <div class="chiffre-note">
      Commandes reçues, pas encore soldées
      @if($aSolder > 0)
        <br><span style="color:var(--gris)">Elles basculeront à votre prochaine demande.</span>
      @endif
    </div>
  </div>
  <div class="carte" style="{{ $litiges > 0 ? 'border-color:var(--rouge)' : '' }}">
    <div class="chiffre mono">{{ $litiges }}</div>
    <div class="chiffre-note">
      Litiges ouverts
      @if($litiges > 0)
        <br><span style="color:var(--rouge)">Tout virement est gelé tant qu'un litige n'est pas tranché.</span>
      @endif
    </div>
  </div>
</div>

{{-- Le partage, dit sans détour. Une place de marché qui retient l'argent de
     ses commerçants et ne leur montre pas ce qu'elle prélève leur demande une
     confiance qu'elle ne rend pas. --}}
<h2>Ce que FamFer prélève — {{ $chiffres['periode_jours'] }} derniers jours</h2>
<div class="carte tableau-large" style="margin-bottom:26px"><table>
  <tr>
    <td>Vos ventes abouties</td>
    <td class="mono" style="text-align:right">
      {{ number_format($chiffres['chiffre_affaires'], 0, ',', ' ') }} F
    </td>
    <td style="color:var(--gris);font-size:.86rem">
      {{ $chiffres['commandes_soldees'] }} commande(s) reçue(s) et soldée(s)
    </td>
  </tr>
  <tr>
    <td style="color:var(--forge)">
      Commission FamFer · {{ $vendeur->taux_commission_pour_mille / 10 }} %
    </td>
    <td class="mono" style="text-align:right;color:var(--forge)">
      − {{ number_format($chiffres['commission_versee'], 0, ',', ' ') }} F
    </td>
    <td style="color:var(--gris);font-size:.86rem">
      Sur la marchandise seule — jamais sur les frais de livraison
    </td>
  </tr>
  <tr style="border-top:2px solid var(--bord)">
    <td><strong>Ce qui vous revient</strong></td>
    <td class="mono" style="text-align:right"><strong>
      {{ number_format($chiffres['net_percu'], 0, ',', ' ') }} F
    </strong></td>
    <td style="color:var(--gris);font-size:.86rem">
      Frais de livraison compris, ils sont à vous
    </td>
  </tr>
</table></div>

<p style="color:var(--gris);font-size:.86rem;margin:-14px 0 26px;max-width:72ch">
  Rien n'est prélevé à l'inscription ni à la publication d'une offre, et la
  commission n'est due qu'une fois la commande reçue par l'acheteur : une vente
  annulée, expirée ou remboursée ne vous coûte rien.
</p>

{{-- Où envoyer l'argent. Cette page annonçait « le compte enregistré à votre
     nom » alors qu'aucun champ ne le portait : la phrase promettait ce que la
     base ne pouvait pas tenir. --}}
<h2>Où envoyer mon argent</h2>
<form method="POST" action="{{ route('vendeur.versement') }}" class="carte"
      style="margin-bottom:26px;{{ $vendeur->peutEtreVire() ? '' : 'border-color:var(--forge)' }}">
  @csrf @method('PUT')

  @unless($vendeur->peutEtreVire())
    <p style="color:var(--forge);margin-bottom:14px;max-width:70ch">
      <strong>Aucun compte enregistré.</strong> Tant que nous ne savons pas où
      envoyer les fonds, aucun virement ne peut être préparé — même si des
      commandes vous sont dues.
    </p>
  @endunless

  <div class="grid2">
    <div class="champ"><label>Opérateur</label>
      <select name="versement_operateur" required>
        <option value="wave" @selected($vendeur->versement_operateur === 'wave')>Wave</option>
        <option value="om" @selected($vendeur->versement_operateur === 'om')>Orange Money</option>
      </select></div>

    <div class="champ"><label>Numéro du compte</label>
      <input name="versement_numero" required placeholder="77 000 00 00"
             value="{{ old('versement_numero', $vendeur->versement_numero) }}">
      @error('versement_numero')<div class="erreur">{{ $message }}</div>@enderror</div>
  </div>

  <div class="champ"><label>Nom du titulaire</label>
    <input name="versement_titulaire" required
           value="{{ old('versement_titulaire', $vendeur->versement_titulaire ?? $vendeur->raison_sociale) }}">
    @error('versement_titulaire')<div class="erreur">{{ $message }}</div>@enderror
    <p style="color:var(--gris);font-size:.84rem;margin-top:6px">
      Tel qu'il apparaît chez l'opérateur. Un virement dont le nom ne correspond
      pas au compte est rejeté.
    </p></div>

  <button class="btn">Enregistrer</button>

  @if($vendeur->versement_modifie_le)
    <p style="color:var(--gris);font-size:.84rem;margin-top:10px">
      Dernière modification
      {{ $vendeur->versement_modifie_le->translatedFormat('le j F Y à H\hi') }}.
      Tout changement est signalé par courriel : si vous en recevez un que vous
      n'avez pas demandé, changez votre mot de passe sans attendre.
    </p>
  @endif
</form>

<form method="POST" action="{{ route('vendeur.reversement') }}" class="carte" style="margin-bottom:26px">
  @csrf
  <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center">
    <button class="btn"
      @disabled($litiges > 0 || ! $vendeur->peutEtreVire() || ($solde <= 0 && $aSolder === 0))>
      Demander mon virement
    </button>
    <span style="color:var(--gris);font-size:.88rem">
      @if($vendeur->peutEtreVire())
        Le montant part vers {{ $vendeur->compteDeVersement() }}.
      @else
        Enregistrez d'abord un compte de versement ci-dessus.
      @endif
    </span>
  </div>
</form>

<h2>Mes virements</h2>
@php
  $etats = [
    'prepare' => ['Préparé', 'etiq-ambre'],
    'envoye' => ['Envoyé', 'etiq-vert'],
    'confirme' => ['Confirmé', 'etiq-vert'],
    'echoue' => ['Échoué', 'etiq-rouge'],
  ];
@endphp

@if($reversements->isEmpty())
  <div class="carte vide">
    Aucun virement pour l'instant. Le premier partira dès qu'une commande sera
    reçue par votre client.
  </div>
@else
  <div class="carte tableau-large"><table>
    <tr><th>Date</th><th>Montant</th><th>État</th><th>Référence</th></tr>
    @foreach($reversements as $rev)
      @php [$mot, $classe] = $etats[$rev->etat] ?? [$rev->etat, 'etiq-gris']; @endphp
      <tr>
        <td>{{ ($rev->envoye_le ?? $rev->created_at)->translatedFormat('j F Y à H\hi') }}</td>
        <td class="mono" style="font-weight:700">{{ number_format($rev->montant, 0, ',', ' ') }} F</td>
        <td><span class="etiq {{ $classe }}">{{ $mot }}</span></td>
        <td style="color:var(--gris)">{{ $rev->reference_virement ?? '—' }}</td>
      </tr>
    @endforeach
  </table></div>

  <div style="margin-top:18px">{{ $reversements->links() }}</div>
@endif

<p style="color:var(--gris);font-size:.86rem;margin-top:22px;max-width:70ch">
  Le disponible affiché vient du grand livre de la plateforme, pas d'un cumul de
  commandes : c'est exactement la somme qui sera virée. Tant qu'une commande
  n'est pas reçue par l'acheteur, son montant reste au séquestre et ne vous est
  pas dû — c'est la contrepartie de la garantie offerte à vos clients.
</p>

@endsection
