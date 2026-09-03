@extends('layouts.app')
@section('titre', 'Valider ma commande')
@section('contenu')

<h1>Valider ma commande</h1>

<form method="POST" action="{{ route('commande.valider') }}"
      style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start;margin-top:14px">
  @csrf

  <div style="flex:1 1 420px;min-width:0">

    <div class="bloc">
      <div class="bloc-tete"><h2>Où livrer</h2></div>
      <div class="bloc-corps">
        @forelse($adresses as $a)
          <label style="display:flex;gap:10px;padding:12px;border:1px solid var(--bord);
                        border-radius:var(--r);margin-bottom:8px;font-weight:400;cursor:pointer">
            <input type="radio" name="adresse_id" value="{{ $a->id }}"
                   @checked($loop->first) style="width:auto;margin-top:3px"
                   data-region="{{ $a->region }}">
            <span>
              <strong>{{ $a->destinataire }}</strong> · {{ $a->telephone }}
              @if($a->par_defaut)<span class="etiq etiq-gris">par défaut</span>@endif
              <br><span style="color:var(--gris-fonce)">{{ $a->enUneLigne() }}</span>
            </span>
          </label>
        @empty
          <p style="color:var(--gris);margin-bottom:12px">
            Aucune adresse enregistrée. Renseignez-la ci-dessous.
          </p>
        @endforelse

        <details @if($adresses->isEmpty()) open @endif style="margin-top:8px">
          <summary style="cursor:pointer;font-weight:600;color:var(--bleu)">
            Livrer à une nouvelle adresse
          </summary>
          <div style="margin-top:12px">
            <div class="grille g2">
              <div class="champ"><label>Destinataire</label>
                <input name="destinataire" value="{{ old('destinataire', auth()->user()->name) }}">
                @error('destinataire')<div class="erreur">{{ $message }}</div>@enderror</div>
              <div class="champ"><label>Téléphone</label>
                <input name="telephone" value="{{ old('telephone', auth()->user()->telephone) }}">
                @error('telephone')<div class="erreur">{{ $message }}</div>@enderror</div>
            </div>
            <div class="grille g2">
              <div class="champ"><label>Région</label>
                <select name="region" id="region">
                  @foreach($regions as $r)
                    <option value="{{ $r }}" @selected(old('region') === $r)>{{ $r }}</option>
                  @endforeach
                  <option value="Autre">Autre région</option>
                </select></div>
              <div class="champ"><label>Ville</label>
                <input name="ville" value="{{ old('ville') }}"></div>
            </div>
            <div class="champ"><label>Quartier</label>
              <input name="quartier" value="{{ old('quartier') }}"></div>
            <div class="champ"><label>Repère <span style="color:var(--gris)">(facultatif)</span></label>
              <input name="repere" value="{{ old('repere') }}"
                     placeholder="En face de la pharmacie, portail bleu…"></div>
          </div>
        </details>
      </div>
    </div>

    <div class="bloc">
      <div class="bloc-tete"><h2>Comment payer</h2></div>
      <div class="bloc-corps">
        <label style="display:flex;gap:10px;padding:12px;border:1px solid var(--bord);
                      border-radius:var(--r);margin-bottom:8px;font-weight:400;cursor:pointer">
          <input type="radio" name="paiement" value="livraison" checked style="width:auto;margin-top:3px">
          <span>
            <strong>À la livraison, en espèces</strong>
            <br><span style="color:var(--gris-fonce)">
              Vous réglez au livreur, au moment de recevoir votre commande.
            </span>
          </span>
        </label>
        <label style="display:flex;gap:10px;padding:12px;border:1px solid var(--bord);
                      border-radius:var(--r);margin-bottom:8px;font-weight:400;cursor:pointer">
          <input type="radio" name="paiement" value="wave" style="width:auto;margin-top:3px">
          <span><strong>Wave</strong>
            <br><span style="color:var(--gris-fonce)">Vous serez contacté pour le règlement.</span></span>
        </label>
        <label style="display:flex;gap:10px;padding:12px;border:1px solid var(--bord);
                      border-radius:var(--r);font-weight:400;cursor:pointer">
          <input type="radio" name="paiement" value="om" style="width:auto;margin-top:3px">
          <span><strong>Orange Money</strong>
            <br><span style="color:var(--gris-fonce)">Vous serez contacté pour le règlement.</span></span>
        </label>
      </div>
    </div>
  </div>

  <div class="carte" style="flex:0 0 300px;position:sticky;top:150px">
    <h2 style="margin-bottom:12px">Ma commande</h2>

    @foreach($contenu as $ligne)
      <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:7px;font-size:.86rem">
        <span>{{ $ligne['quantite'] }} × {{ Str::limit($ligne['produit']->nom, 30) }}</span>
        <span class="mono">{{ number_format($ligne['montant'], 0, ',', ' ') }} F</span>
      </div>
    @endforeach

    <hr style="border:0;border-top:1px solid var(--bord);margin:12px 0">

    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
      <span>Sous-total</span>
      <strong class="mono">{{ number_format($sousTotal, 0, ',', ' ') }} F</strong>
    </div>
    <div style="display:flex;justify-content:space-between;margin-bottom:6px;color:var(--gris-fonce)">
      <span>Livraison</span>
      <span class="mono">
        @if($fraisParRegion->every(fn ($f) => $f === 0))
          offerte
        @else
          selon la région
        @endif
      </span>
    </div>

    <div style="background:var(--fond);border-radius:var(--r);padding:10px;
                font-size:.82rem;color:var(--gris-fonce);margin:10px 0">
      @foreach($fraisParRegion as $region => $frais)
        <div style="display:flex;justify-content:space-between">
          <span>{{ $region }}</span>
          <span class="mono">{{ $frais === 0 ? 'offerte' : number_format($frais, 0, ',', ' ') . ' F' }}</span>
        </div>
      @endforeach
      <div style="display:flex;justify-content:space-between">
        <span>Autre région</span>
        <span class="mono">{{ $fraisAutre === 0 ? 'offerte' : number_format($fraisAutre, 0, ',', ' ') . ' F' }}</span>
      </div>
    </div>

    <button class="btn" style="width:100%">Confirmer la commande</button>
    <p style="color:var(--gris);font-size:.8rem;margin-top:10px;text-align:center">
      En confirmant, vous acceptez les
      <a href="{{ route('conditions') }}" style="color:var(--bleu)">conditions générales</a>.
    </p>
  </div>
</form>

@endsection
