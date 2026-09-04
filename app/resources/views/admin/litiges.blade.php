@extends('layouts.app')
@section('titre', 'Litiges')
@section('contenu')

<h1 style="font-size:1.35rem;margin-bottom:4px">Litiges à trancher</h1>
<p style="color:var(--gris);font-size:.9rem;margin-bottom:16px">
  C'est le seul endroit où la plateforme décide à la place des parties. Cela doit
  rester rare : une place de marché qui arbitre tous les jours a un problème de
  vendeurs, pas de logiciel.
</p>

<div class="bloc">
  <div class="bloc-tete"><h2>{{ $liste->count() }} litige(s) ouvert(s)</h2></div>
  <div class="bloc-corps">
    @forelse($liste as $c)
      <div style="padding:14px 0;border-bottom:1px solid var(--bord)">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
          <strong>{{ $c->reference }}</strong>
          <span class="etiq etiq-gris">
            état contesté : {{ $c->etat_conteste }}
          </span>
          <span class="etiq etiq-orange">
            ouvert par {{ $c->litige_par }}
          </span>
          <span style="color:var(--gris);font-size:.85rem">
            {{ $c->litige_le?->format('d/m/Y') }}
          </span>
          <span class="mono" style="margin-left:auto;font-weight:700">
            {{ number_format($c->total, 0, ',', ' ') }} F
          </span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
                    gap:12px;margin-bottom:10px;font-size:.9rem">
          <div>
            <div style="color:var(--gris);font-size:.8rem">Client</div>
            {{ $c->destinataire }} — {{ $c->telephone }}
          </div>
          <div>
            <div style="color:var(--gris);font-size:.8rem">Boutique</div>
            {{ $c->lignes->first()?->boutique?->nom ?? '—' }}
          </div>
          <div>
            {{-- Le code n'a jamais été remis : c'est l'indice le plus fort du
                 dossier. S'il l'a été, la livraison a matériellement eu lieu. --}}
            <div style="color:var(--gris);font-size:.8rem">Code de remise</div>
            @if($c->code_remis_le)
              <span style="color:var(--vert)">remis le {{ $c->code_remis_le->format('d/m/Y') }}</span>
            @elseif($c->code_livraison)
              <span style="color:var(--rouge)">jamais remis</span>
            @else
              —
            @endif
          </div>
          <div>
            <div style="color:var(--gris);font-size:.8rem">Confirmé par le client</div>
            {{ $c->confirmee_le ? $c->confirmee_le->format('d/m/Y') : 'non' }}
          </div>
        </div>

        <div class="avis" style="margin-bottom:10px">
          <strong>Motif invoqué :</strong> {{ $c->litige_motif }}
        </div>

        <form method="POST" action="{{ route('admin.trancher', $c) }}"
              style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          @csrf
          <select name="vers" required
                  style="padding:7px 9px;border:1px solid var(--bord);border-radius:var(--r)">
            <option value="livree">Livrée — la vente a eu lieu, la commission est due</option>
            <option value="refusee">Refusée — le colis est revenu</option>
            <option value="annulee">Annulée — la commande est effacée</option>
          </select>
          <input name="motif" required minlength="10" maxlength="300"
                 placeholder="Motivez la décision (10 caractères minimum)"
                 style="flex:1 1 280px;padding:7px 9px;border:1px solid var(--bord);
                        border-radius:var(--r)">
          <button class="btn btn-sm">Trancher</button>
        </form>
      </div>
    @empty
      <p style="color:var(--gris)">Aucun litige ouvert.</p>
    @endforelse
  </div>
</div>

<div class="bloc">
  <div class="bloc-tete">
    <h2>Commandes dormantes</h2>
    <span style="color:var(--gris);font-size:.85rem">
      expédiées depuis plus de {{ \App\Services\Veille::JOURS_AVANT_RELANCE }} jours
    </span>
  </div>
  <div class="bloc-corps">
    {{-- Le silence du vendeur est la troisième source. Un colis expédié il y a
         une semaine et jamais clos dort dans un magasin, ou bien il a été remis
         sans être déclaré — et la seconde hypothèse est celle qui l'arrange.
         La veille quotidienne a déjà posé la question au client. --}}
    @forelse($dormantes as $c)
      <div style="display:flex;gap:12px;align-items:center;padding:9px 0;flex-wrap:wrap;
                  border-bottom:1px solid var(--bord)">
        <strong>{{ $c->reference }}</strong>
        @include('partials.etat', ['etat' => $c->etat])
        <span style="color:var(--gris);font-size:.86rem">
          {{ $c->lignes->first()?->boutique?->nom ?? '—' }}
        </span>
        <span style="color:var(--rouge);font-size:.86rem">
          {{ (int) $c->expediee_le?->diffInDays() }} jours
        </span>
        <span style="color:var(--gris);font-size:.86rem">
          {{ $c->relance_le ? 'client relancé' : 'pas encore relancé' }}
        </span>
        <span class="mono" style="margin-left:auto;font-weight:700">
          {{ number_format($c->total, 0, ',', ' ') }} F
        </span>
      </div>
    @empty
      <p style="color:var(--gris)">Aucune commande ne traîne.</p>
    @endforelse
  </div>
</div>

<div class="bloc">
  <div class="bloc-tete"><h2>Taux de refus par boutique</h2></div>
  <div class="bloc-corps">
    {{-- Le détecteur. La preuve par code couvre une commande ; ce tableau
         couvre le commerçant. Un vendeur qui encaisse puis déclare « refusée »
         pour éviter la commission fait monter ce taux, seul, pendant que ses
         concurrents restent bas. --}}
    <p style="color:var(--gris);font-size:.88rem;margin-top:0">
      Un taux nettement au-dessus des autres est le signe d'une boutique qui
      déclare des refus n'ayant pas eu lieu. Les boutiques de moins de cinq
      commandes closes ne figurent pas ici : le chiffre n'y voudrait rien dire.
    </p>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.9rem">
        <thead>
          <tr style="text-align:left;color:var(--gris);border-bottom:1px solid var(--bord)">
            <th style="padding:8px 6px">Boutique</th>
            <th style="padding:8px 6px;text-align:right">Livrées</th>
            <th style="padding:8px 6px;text-align:right">Refusées</th>
            <th style="padding:8px 6px;text-align:right">Taux de refus</th>
          </tr>
        </thead>
        <tbody>
          @forelse($suspects as $b)
            <tr style="border-bottom:1px solid var(--bord)">
              <td style="padding:8px 6px">{{ $b->nom }}</td>
              <td class="mono" style="padding:8px 6px;text-align:right">{{ $b->nb_livrees }}</td>
              <td class="mono" style="padding:8px 6px;text-align:right">{{ $b->nb_refusees }}</td>
              <td class="mono" style="padding:8px 6px;text-align:right;font-weight:700;
                         color:{{ $b->taux_refus >= 30 ? 'var(--rouge)' : 'inherit' }}">
                {{ number_format($b->taux_refus, 1, ',', ' ') }} %
              </td>
            </tr>
          @empty
            <tr><td colspan="4" style="padding:14px 6px;color:var(--gris)">
              Aucune boutique n'a encore cinq commandes closes.
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@endsection
