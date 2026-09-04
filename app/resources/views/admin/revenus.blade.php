@extends('layouts.app')
@section('titre', 'Revenus de la plateforme')
@section('contenu')

<h1 style="font-size:1.35rem;margin-bottom:4px">Ce que gagne FamFer</h1>
<p style="color:var(--gris);font-size:.9rem;margin-bottom:16px">
  La plateforme ne touche jamais l'argent du client : le vendeur encaisse à la
  livraison, puis reverse sa commission. Ce tableau est donc un état de créances,
  pas un solde bancaire.
</p>

<div class="grille g4" style="margin-bottom:16px">
  <div class="carte">
    <div class="mono" style="font-size:1.5rem;font-weight:800">
      {{ number_format($chiffres['volume'], 0, ',', ' ') }} F
    </div>
    <div style="color:var(--gris);font-size:.84rem">Marchandise livrée</div>
  </div>
  <div class="carte" style="border:1px solid var(--vert)">
    <div class="mono" style="font-size:1.5rem;font-weight:800">
      {{ number_format($chiffres['commission'], 0, ',', ' ') }} F
    </div>
    <div style="color:var(--gris);font-size:.84rem">Commission acquise</div>
  </div>
  <div class="carte">
    <div style="font-size:1.5rem;font-weight:800">
      {{ number_format($chiffres['taux_moyen'], 2, ',', ' ') }} %
    </div>
    <div style="color:var(--gris);font-size:.84rem">
      Taux moyen obtenu<br>
      <span style="font-size:.78rem">et non le taux affiché : les enseignes négocient</span>
    </div>
  </div>
  <div class="carte" style="{{ $chiffres['perdue_sur_refus'] ? 'border:1px solid var(--rouge)' : '' }}">
    <div class="mono" style="font-size:1.5rem;font-weight:800">
      {{ number_format($chiffres['perdue_sur_refus'], 0, ',', ' ') }} F
    </div>
    <div style="color:var(--gris);font-size:.84rem">
      Perdue sur refus et retours<br>
      <span style="font-size:.78rem">le coût du paiement à la livraison</span>
    </div>
  </div>
</div>

<div class="bloc">
  <div class="bloc-tete"><h2>Par boutique</h2></div>
  <div class="bloc-corps" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.9rem">
      <thead>
        <tr style="text-align:left;color:var(--gris);border-bottom:1px solid var(--bord)">
          <th style="padding:8px 6px">Boutique</th>
          <th style="padding:8px 6px;text-align:right">Commission due</th>
          <th style="padding:8px 6px">Taux négocié</th>
        </tr>
      </thead>
      <tbody>
        @forelse($classement as $b)
          <tr style="border-bottom:1px solid var(--bord)">
            <td style="padding:8px 6px">
              <strong>{{ $b->nom }}</strong>
              @if($b->officielle)<span class="etiq etiq-officielle">Officielle</span>@endif
            </td>
            <td class="mono" style="padding:8px 6px;text-align:right;font-weight:700">
              {{ number_format((int) $b->commission_due, 0, ',', ' ') }} F
            </td>
            <td style="padding:8px 6px">
              {{-- Le taux se renégocie : une enseigne qui apporte du volume n'a
                   aucune raison de payer comme un nouveau venu. Les commandes
                   déjà passées gardent le leur, figé. --}}
              <form method="POST" action="{{ route('admin.taux', $b) }}"
                    style="display:flex;gap:6px;align-items:center">
                @csrf
                <input type="number" name="taux" step="0.1" min="0" max="30"
                       value="{{ $b->tauxPourCent() }}"
                       style="width:76px;padding:5px 7px;border:1px solid var(--bord);border-radius:6px">
                <span style="color:var(--gris)">%</span>
                <button class="btn btn-sm btn-clair">Appliquer</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="3" style="padding:14px 6px;color:var(--gris)">Aucune boutique.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
