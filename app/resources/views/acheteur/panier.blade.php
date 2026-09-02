@extends('layouts.app')
@section('titre', 'Mon panier')
@section('contenu')
<h1>Mon panier</h1>
<p class="sous">
  Un panier réparti sur plusieurs quincailleries donnera une commande par vendeur —
  chacun livre et est payé pour ce qu'il a fourni.
</p>

@forelse($parVendeur as $vendeurId => $lignes)
  @php
    $vendeur = $offres[$lignes->first()['offre_id']]->vendeur;
    $total = 0;
    $poids = $lignes->sum('pivot');
    $d = $devis[$vendeurId] ?? null;
  @endphp
  <div class="carte" style="margin-bottom:16px">
    <h2 style="margin-bottom:10px">{{ $vendeur->raison_sociale }}</h2>
    <div class="tableau-large"><table>
      <tr><th>Article</th><th>Quantité</th><th style="text-align:right">Montant</th><th></th></tr>
      @foreach($lignes as $l)
        @php
          $o = $offres[$l['offre_id']];
          $facteur = $o->article->unitesVente->firstWhere('unite', $o->unite_affichee)->facteur_vers_pivot;
          $montant = intdiv($l['pivot'] * $o->prix_par_unite, $facteur);
          $total += $montant;
        @endphp
        <tr>
          <td>{{ $o->article->designation }}</td>
          <td>{{ $l['quantite'] }} {{ $l['unite'] }}</td>
          <td class="mono" style="text-align:right">{{ number_format($montant, 0, ',', ' ') }} F</td>
          <td style="text-align:right">
            <form method="POST" action="{{ route('panier.retirer', $o) }}">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-clair">Retirer</button>
            </form>
          </td>
        </tr>
      @endforeach
      <tr><td colspan="2">Marchandise · {{ number_format($poids / 1000, 0, ',', ' ') }} kg</td>
        <td class="mono" style="text-align:right">{{ number_format($total, 0, ',', ' ') }} F</td>
        <td></td></tr>

      {{-- Le devis de livraison, détaillé. Un montant qui tombe sans explication
           passe pour arbitraire ; montrer les trois termes fait comprendre
           pourquoi trois tonnes coûtent plus cher que trois cornières. --}}
      @if($d && isset($d['refus']))
        <tr><td colspan="4" style="color:var(--rouge);font-size:.88rem">{{ $d['refus'] }}</td></tr>
      @elseif($d)
        <tr>
          <td colspan="2" style="color:var(--gris);font-size:.88rem">
            Livraison · {{ number_format($d['distance_km'], 1, ',', ' ') }} km
            <span style="font-size:.84rem">
              ({{ number_format($d['base'], 0, ',', ' ') }} prise en charge
              + {{ number_format($d['part_distance'], 0, ',', ' ') }} distance
              + {{ number_format($d['part_poids'], 0, ',', ' ') }} poids)
            </span>
          </td>
          <td class="mono" style="text-align:right">{{ number_format($d['total'], 0, ',', ' ') }} F</td>
          <td></td>
        </tr>
        <tr><td colspan="2"><strong>Total livré</strong></td>
          <td class="mono" style="text-align:right">
            <strong>{{ number_format($total + $d['total'], 0, ',', ' ') }} F</strong>
          </td>
          <td></td></tr>
      @else
        <tr><td colspan="2"><strong>Total</strong></td>
          <td class="mono" style="text-align:right"><strong>{{ number_format($total, 0, ',', ' ') }} F</strong></td>
          <td></td></tr>
      @endif
    </table></div>
  </div>
@empty
  <div class="carte vide">Votre panier est vide. <a href="{{ route('accueil') }}">Chercher du fer</a></div>
@endforelse

@if($parVendeur->isNotEmpty())
  <form method="POST" action="{{ route('panier.valider') }}" class="carte" id="validation">
    @csrf
    <input type="hidden" name="lat" id="lat" value="{{ $lat }}">
    <input type="hidden" name="lng" id="lng" value="{{ $lng }}">

    <div class="champ"><label>Comment récupérez-vous la marchandise ?</label>
      <select name="mode_remise" id="mode" required>
        <option value="retrait">Retrait au magasin — sans frais</option>
        <option value="livraison" @selected($lat !== null)>Livraison sur chantier</option>
      </select></div>

    <div id="bloc-livraison" style="{{ $lat === null ? 'display:none' : '' }}">
      <div class="champ"><label>Adresse de livraison</label>
        <input name="adresse" placeholder="Quartier, repère" value="{{ old('adresse') }}"></div>

      @if($lat === null)
        <p style="color:var(--gris);font-size:.88rem">
          Le fer se facture au poids et à la distance. Indiquez où livrer pour
          voir le montant avant de valider.
        </p>
      @endif
      <button type="button" class="btn btn-sm btn-clair" id="me-situer">
        Utiliser ma position pour chiffrer la livraison
      </button>
    </div>

    <button class="btn" style="margin-top:14px">Valider — la marchandise sera réservée</button>
    <p style="color:var(--gris);font-size:.86rem;margin-top:10px">
      Vous aurez quinze minutes pour régler. Votre argent est retenu par FamFer
      jusqu'à ce que vous confirmiez la réception.
    </p>
  </form>

  <script>
    // Le bloc livraison n'a pas de sens pour un retrait au comptoir.
    const mode = document.getElementById('mode');
    const bloc = document.getElementById('bloc-livraison');
    mode.addEventListener('change', () => {
      bloc.style.display = mode.value === 'livraison' ? '' : 'none';
    });

    // Se situer recharge la page : c'est le serveur qui chiffre, avec le même
    // service que celui qui facturera. Un devis calculé côté navigateur pourrait
    // afficher un montant que la commande ne retiendrait pas.
    document.getElementById('me-situer').addEventListener('click', function () {
      if (!navigator.geolocation) {
        alert("Votre navigateur ne donne pas la position. Choisissez le retrait au magasin.");
        return;
      }
      this.textContent = 'Recherche de votre position…';
      navigator.geolocation.getCurrentPosition(
        (p) => {
          const u = new URL(window.location.href);
          u.searchParams.set('lat', p.coords.latitude.toFixed(6));
          u.searchParams.set('lng', p.coords.longitude.toFixed(6));
          window.location.href = u.toString();
        },
        () => {
          this.textContent = 'Position refusée — choisissez le retrait au magasin';
        }
      );
    });
  </script>
@endif
@endsection
