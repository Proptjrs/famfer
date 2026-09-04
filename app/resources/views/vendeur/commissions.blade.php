@extends('layouts.app')
@section('titre', 'Ma commission')
@section('contenu')

@php($taux = rtrim(rtrim(number_format($compte['taux'], 1, ',', ' '), '0'), ','))

<h1 style="font-size:1.35rem;margin-bottom:4px">Ma commission FamFer</h1>
<p style="color:var(--gris);font-size:.9rem;margin-bottom:16px">
  Vous encaissez vous-même les espèces à la livraison. FamFer ne retient donc rien
  sur vos ventes : la plateforme vous facture après coup la commission des
  commandes <strong>effectivement livrées</strong>.
</p>

<div class="grille g4" style="margin-bottom:16px">
  <div class="carte">
    <div class="mono" style="font-size:1.5rem;font-weight:800">
      {{ number_format($compte['encaisse'], 0, ',', ' ') }} F
    </div>
    <div style="color:var(--gris);font-size:.84rem">
      Encaissé à la livraison<br>
      <span style="font-size:.78rem">
        dont {{ number_format($compte['port'], 0, ',', ' ') }} F de port
      </span>
    </div>
  </div>
  <div class="carte" style="border:1px solid var(--orange)">
    <div class="mono" style="font-size:1.5rem;font-weight:800">
      − {{ number_format($compte['commission'], 0, ',', ' ') }} F
    </div>
    <div style="color:var(--gris);font-size:.84rem">
      Commission FamFer <span class="etiq etiq-gris">{{ $taux }} %</span>
    </div>
  </div>
  <div class="carte" style="border:1px solid var(--vert)">
    <div class="mono" style="font-size:1.5rem;font-weight:800">
      {{ number_format($compte['net'], 0, ',', ' ') }} F
    </div>
    <div style="color:var(--gris);font-size:.84rem">Ce qui vous reste</div>
  </div>
  <div class="carte">
    <div style="font-size:1.5rem;font-weight:800">{{ $compte['ventes'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">Articles livrés</div>
  </div>
</div>

<div class="avis">
  <strong>Comment c'est calculé.</strong>
  La commission porte sur la <strong>marchandise seule</strong> :
  {{ number_format($compte['marchandise'], 0, ',', ' ') }} F × {{ $taux }} % =
  {{ number_format($compte['commission'], 0, ',', ' ') }} F.
  Les frais de livraison vous reviennent entièrement — c'est vous qui faites la
  tournée. Et une commande <strong>refusée à la porte, annulée ou retournée ne
  vous coûte rien</strong> : le déplacement vous a déjà coûté assez.
</div>

<div class="bloc">
  <div class="bloc-tete"><h2>Relevé mensuel</h2></div>
  <div class="bloc-corps" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.9rem">
      <thead>
        <tr style="text-align:left;color:var(--gris);border-bottom:1px solid var(--bord)">
          <th style="padding:8px 6px">Mois</th>
          <th style="padding:8px 6px">Commandes</th>
          <th style="padding:8px 6px;text-align:right">Marchandise</th>
          <th style="padding:8px 6px;text-align:right">Commission</th>
          <th style="padding:8px 6px;text-align:right">Net</th>
        </tr>
      </thead>
      <tbody>
        @forelse($releve as $l)
          <tr style="border-bottom:1px solid var(--bord)">
            <td class="mono" style="padding:8px 6px">{{ $l->periode }}</td>
            <td style="padding:8px 6px">{{ $l->commandes }}</td>
            <td class="mono" style="padding:8px 6px;text-align:right">
              {{ number_format((int) $l->marchandise, 0, ',', ' ') }} F
            </td>
            <td class="mono" style="padding:8px 6px;text-align:right">
              − {{ number_format((int) $l->commission, 0, ',', ' ') }} F
            </td>
            <td class="mono" style="padding:8px 6px;text-align:right;font-weight:700">
              {{ number_format((int) $l->marchandise - (int) $l->commission, 0, ',', ' ') }} F
            </td>
          </tr>
        @empty
          <tr><td colspan="5" style="padding:14px 6px;color:var(--gris)">
            Aucune commande livrée pour l'instant : vous ne devez rien.
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
