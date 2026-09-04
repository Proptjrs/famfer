@extends('layouts.app')
@section('titre', 'Valider ma commande')
@section('contenu')

@include('partials.entete', [
  'titre' => 'Valider ma commande',
  'sous' => 'Dernière étape. Rien n\'est prélevé : vous paierez au livreur, en espèces, à la réception.',
  'fil' => [
    ['libelle' => 'Accueil', 'url' => route('accueil')],
    ['libelle' => 'Mon panier', 'url' => route('panier')],
    ['libelle' => 'Valider'],
  ],
])

<form method="POST" action="{{ route('commande.valider') }}" class="deux-colonnes">
  @csrf

  <div class="pile-lg">

    <div class="bloc">
      <div class="bloc-tete">
        <h2>Où livrer</h2>
        <span class="sous">le livreur appellera ce numéro</span>
      </div>
      <div class="bloc-corps pile">
        @forelse($adresses as $a)
          <label class="case" style="padding:var(--s3) var(--s4);
                 border:1px solid var(--line);border-radius:var(--r-sm);
                 background:var(--surface)">
            <input type="radio" name="adresse_id" value="{{ $a->id }}"
                   @checked($loop->first) data-region="{{ $a->region }}">
            <span style="flex:1">
              <span class="rang-sm">
                <strong style="color:var(--ink)">{{ $a->destinataire }}</strong>
                <span class="chiffre petit secondaire">{{ $a->telephone }}</span>
                @if($a->par_defaut)<span class="jeton jeton-neutre">par défaut</span>@endif
              </span>
              <span class="petit secondaire" style="display:block">{{ $a->enUneLigne() }}</span>
            </span>
          </label>
        @empty
          <p class="petit secondaire">
            Aucune adresse enregistrée. Renseignez-la ci-dessous : elle sera
            conservée pour vos prochaines commandes.
          </p>
        @endforelse

        <details @if($adresses->isEmpty()) open @endif>
          <summary style="cursor:pointer;font-weight:650;color:var(--brand-strong);
                          padding:var(--s2) 0">
            Livrer à une nouvelle adresse
          </summary>

          <div class="pile" style="margin-top:var(--s4)">
            <div class="grille g2">
              <div class="champ">
                <label for="destinataire">Destinataire</label>
                <input id="destinataire" name="destinataire"
                       value="{{ old('destinataire', auth()->user()->name) }}"
                       @error('destinataire') aria-invalid="true" @enderror>
                @error('destinataire')<div class="erreur">{{ $message }}</div>@enderror
              </div>
              <div class="champ">
                <label for="telephone">Téléphone</label>
                <input id="telephone" name="telephone" type="tel" class="chiffre"
                       value="{{ old('telephone', auth()->user()->telephone) }}"
                       placeholder="77 123 45 67"
                       @error('telephone') aria-invalid="true" @enderror>
                <div class="aide">Le livreur vous appellera à ce numéro.</div>
                @error('telephone')<div class="erreur">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="grille g2">
              <div class="champ">
                <label for="region">Région</label>
                <select id="region" name="region">
                  @foreach($regions as $r)
                    <option value="{{ $r }}" @selected(old('region') === $r)>{{ $r }}</option>
                  @endforeach
                  <option value="Autre" @selected(old('region') === 'Autre')>Autre région</option>
                </select>
                <div class="aide">Elle détermine les frais de livraison.</div>
              </div>
              <div class="champ">
                <label for="ville">Ville</label>
                <input id="ville" name="ville" value="{{ old('ville') }}"
                       @error('ville') aria-invalid="true" @enderror>
                @error('ville')<div class="erreur">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="champ">
              <label for="quartier">Quartier</label>
              <input id="quartier" name="quartier" value="{{ old('quartier') }}"
                     @error('quartier') aria-invalid="true" @enderror>
              @error('quartier')<div class="erreur">{{ $message }}</div>@enderror
            </div>

            <div class="champ">
              <label for="repere">Repère <span class="facultatif">— facultatif</span></label>
              <input id="repere" name="repere" value="{{ old('repere') }}"
                     placeholder="En face de la pharmacie, portail bleu…">
              <div class="aide">
                Beaucoup d'adresses au Sénégal se trouvent au repère plutôt qu'au
                numéro. Cette ligne évite un appel au livreur.
              </div>
            </div>
          </div>
        </details>
      </div>
    </div>

    <div class="bloc">
      <div class="bloc-tete">
        <h2>Comment payer</h2>
        <span class="sous">après avoir vu le colis</span>
      </div>
      <div class="bloc-corps pile">
        <label class="case" style="padding:var(--s4);border:1px solid var(--brand-line);
               border-radius:var(--r-sm);background:var(--brand-soft)">
          <input type="radio" name="paiement" value="livraison" checked>
          <span style="flex:1">
            <strong style="color:var(--ink)">À la livraison, en espèces</strong>
            <span class="petit secondaire" style="display:block">
              Vous réglez au livreur au moment de recevoir la commande, et vous
              lui donnez alors le code de remise à six chiffres qui vous sera
              envoyé. C'est ce code qui atteste la livraison.
            </span>
          </span>
        </label>

        {{-- Wave et Orange Money étaient proposés et acceptés par le formulaire,
             alors qu'aucun code ne les traite : la commande était livrée, jamais
             marquée payée, et générait pourtant une commission que le vendeur
             devait sur un argent qu'il n'avait peut-être jamais encaissé.
             Ils restent affichés — la demande existe — mais désactivés tant
             qu'aucun contrat marchand n'est signé avec l'opérateur. --}}
        <div class="case" style="padding:var(--s4);border:1px dashed var(--line-strong);
             border-radius:var(--r-sm);opacity:.72">
          <input type="radio" disabled aria-disabled="true">
          <span style="flex:1">
            <span class="rang-sm">
              <strong style="color:var(--ink-2)">Wave et Orange Money</strong>
              <span class="jeton jeton-neutre">bientôt</span>
            </span>
            <span class="petit secondaire" style="display:block">
              Le paiement mobile suppose un contrat marchand avec l'opérateur.
              Tant qu'il n'est pas signé, nous préférons ne pas le proposer
              plutôt que de vous promettre un règlement que nous ne savons pas
              encore encaisser.
            </span>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="colonne-fixe">
    <div class="bloc">
      <div class="bloc-tete"><h2>Ma commande</h2></div>
      <div class="bloc-corps pile">

        <div class="pile-sm">
          @foreach($contenu as $ligne)
            <div class="rang-serre petit">
              <span class="chiffre secondaire" style="flex:none">{{ $ligne['quantite'] }} ×</span>
              <span class="tronque-1">{{ $ligne['produit']->nom }}</span>
              <span class="chiffre pousse">{{ number_format($ligne['montant'], 0, ',', ' ') }} F</span>
            </div>
          @endforeach
        </div>

        <hr>

        <div class="rang-serre">
          <span>Sous-total</span>
          <strong class="chiffre pousse">{{ number_format($sousTotal, 0, ',', ' ') }} F</strong>
        </div>

        <div class="rang-serre secondaire">
          <span>Livraison</span>
          <span class="chiffre pousse">
            @if($fraisParRegion->every(fn ($f) => $f === 0))
              offerte
            @else
              selon la région
            @endif
          </span>
        </div>

        {{-- Le barème complet, plutôt qu'un montant qui apparaîtrait après
             validation. Un frais découvert trop tard fait abandonner le panier
             — et il fait surtout perdre confiance. --}}
        <details style="border:1px solid var(--line);border-radius:var(--r-sm);
                 padding:var(--s3) var(--s4)">
          <summary style="cursor:pointer;font-weight:650;font-size:var(--t-xs)">
            Le barème par région
          </summary>
          <div class="pile-sm" style="margin-top:var(--s3)">
            @foreach($fraisParRegion as $region => $frais)
              <div class="rang-serre petit">
                <span class="secondaire">{{ $region }}</span>
                <span class="chiffre pousse">
                  {{ $frais === 0 ? 'offerte' : number_format($frais, 0, ',', ' ') . ' F' }}
                </span>
              </div>
            @endforeach
            <div class="rang-serre petit">
              <span class="secondaire">Autre région</span>
              <span class="chiffre pousse">
                {{ $fraisAutre === 0 ? 'offerte' : number_format($fraisAutre, 0, ',', ' ') . ' F' }}
              </span>
            </div>
          </div>
        </details>

        <button type="submit" class="btn btn-lg btn-bloc">Confirmer la commande</button>

        <p class="mini secondaire" style="text-align:center">
          En confirmant, vous acceptez les
          <a href="{{ route('conditions') }}" class="lien">conditions générales</a>.
          Vous pouvez encore annuler tant que le colis n'est pas parti.
        </p>
      </div>
    </div>
  </div>
</form>

@endsection
