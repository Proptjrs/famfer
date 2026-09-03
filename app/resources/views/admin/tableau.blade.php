@extends('layouts.app')
@section('titre', 'Administration')
@section('contenu')

<div style="display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
  <h1 style="font-size:1.35rem">Administration</h1>
  <a href="{{ route('admin.boutiques') }}" class="btn btn-sm btn-clair" style="margin-left:auto">
    Les boutiques
  </a>
  <a href="{{ route('admin.commandes') }}" class="btn btn-sm btn-clair">Les commandes</a>
</div>

<div class="grille g4" style="margin-bottom:16px">
  <div class="carte">
    <div class="mono" style="font-size:1.5rem;font-weight:800">
      {{ number_format($chiffres['volume_livre'], 0, ',', ' ') }} F
    </div>
    <div style="color:var(--gris);font-size:.84rem">Volume livré</div>
  </div>
  <div class="carte">
    <div style="font-size:1.5rem;font-weight:800">{{ $chiffres['commandes'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">Commandes</div>
  </div>
  <div class="carte" style="{{ $chiffres['boutiques_en_attente'] ? 'border:1px solid var(--orange)' : '' }}">
    <div style="font-size:1.5rem;font-weight:800">{{ $chiffres['boutiques_en_attente'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">Boutiques à valider</div>
  </div>
  <div class="carte">
    <div style="font-size:1.5rem;font-weight:800">{{ $chiffres['boutiques_actives'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">Boutiques actives</div>
  </div>
  <div class="carte">
    <div style="font-size:1.5rem;font-weight:800">{{ $chiffres['produits'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">Produits en vente</div>
  </div>
  <div class="carte">
    <div style="font-size:1.5rem;font-weight:800">{{ $chiffres['clients'] }}</div>
    <div style="color:var(--gris);font-size:.84rem">Clients</div>
  </div>
  <div class="carte">
    <div style="font-size:1.5rem;font-weight:800">
      {{ $chiffres['a_expedier'] }}<span style="font-size:1rem;color:var(--gris)"> / {{ $chiffres['en_route'] }}</span>
    </div>
    <div style="color:var(--gris);font-size:.84rem">À expédier / en route</div>
  </div>
  <div class="carte" style="{{ $chiffres['taux_refus'] > 10 ? 'border:1px solid var(--rouge)' : '' }}">
    <div style="font-size:1.5rem;font-weight:800">{{ $chiffres['taux_refus'] }} %</div>
    <div style="color:var(--gris);font-size:.84rem">
      Colis refusés
      {{-- L'indicateur qui dit si le paiement à la livraison tient : chaque
           refus est une tournée payée pour rien. --}}
      <br><span style="font-size:.78rem">{{ $chiffres['refusees'] }} refus au total</span>
    </div>
  </div>
</div>

<div class="bloc">
  <div class="bloc-tete">
    <h2>Boutiques à valider</h2>
    <span style="color:var(--gris);font-size:.85rem">
      Personne ne s'auto-valide : c'est ce qui protège les acheteurs.
    </span>
  </div>
  <div class="bloc-corps">
    @forelse($aValider as $b)
      <div style="display:flex;gap:12px;align-items:center;padding:10px 0;flex-wrap:wrap;
                  border-bottom:1px solid var(--bord)">
        <div style="flex:1 1 240px">
          <strong>{{ $b->nom }}</strong>
          <div style="color:var(--gris);font-size:.85rem">
            {{ $b->utilisateur->name }} · {{ $b->telephone }}<br>
            {{ $b->adresse }}, {{ $b->ville }}
          </div>
        </div>
        <form method="POST" action="{{ route('admin.activer', $b) }}">
          @csrf <button class="btn btn-sm btn-vert">Valider</button>
        </form>
        <details>
          <summary class="btn btn-sm btn-clair" style="list-style:none">Refuser</summary>
          <form method="POST" action="{{ route('admin.suspendre', $b) }}" class="carte"
                style="margin-top:8px;min-width:260px">
            @csrf
            <div class="champ"><label>Motif</label>
              <textarea name="motif" rows="2" required minlength="5"></textarea></div>
            <button class="btn btn-sm btn-rouge">Refuser</button>
          </form>
        </details>
      </div>
    @empty
      <div class="vide">Aucune boutique en attente.</div>
    @endforelse
  </div>
</div>

@if($meilleures->isNotEmpty())
  <div class="bloc">
    <div class="bloc-tete"><h2>Les boutiques les mieux notées</h2></div>
    <div class="bloc-corps large">
      <table>
        <tr><th>Boutique</th><th>Ville</th><th>Note</th><th>Avis</th></tr>
        @foreach($meilleures as $b)
          <tr>
            <td>
              <a href="{{ route('boutique', $b) }}" style="color:var(--bleu)">{{ $b->nom }}</a>
              @if($b->officielle)<span class="etiq etiq-officielle">Officielle</span>@endif
            </td>
            <td style="color:var(--gris)">{{ $b->ville }}</td>
            <td>
              @if($b->nombre_avis)
                <span class="etoiles">{{ str_repeat('★', (int) round($b->noteSurCinq())) }}</span>
                {{ $b->noteSurCinq() }}
              @else
                <span style="color:var(--gris)">—</span>
              @endif
            </td>
            <td class="mono">{{ $b->nombre_avis }}</td>
          </tr>
        @endforeach
      </table>
    </div>
  </div>
@endif

@endsection
