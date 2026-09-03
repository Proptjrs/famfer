@extends('layouts.app')
@section('titre', 'Mes commandes')
@section('contenu')
@php
$libelles = [
 'en_attente_paiement' => ['À régler', 'etiq-ambre'],
 'payee' => ['Payée — en attente du vendeur', 'etiq-ambre'],
 'acceptee' => ['Acceptée', 'etiq-vert'],
 'prete' => ['Prête', 'etiq-vert'],
 'en_livraison' => ['En livraison', 'etiq-vert'],
 'receptionnee' => ['Reçue', 'etiq-vert'],
 'soldee' => ['Terminée', 'etiq-gris'],
 'en_litige' => ['Litige en cours', 'etiq-rouge'],
 'annulee' => ['Annulée', 'etiq-gris'],
 'expiree' => ['Expirée', 'etiq-gris'],
 'remboursee' => ['Remboursée', 'etiq-gris'],
];
@endphp

<h1>Mes commandes</h1>
<p class="sous">Votre argent est retenu par FamFer jusqu'à ce que vous confirmiez la réception.</p>

@forelse($liste as $c)
  @php [$mot, $classe] = $libelles[$c->etat] ?? [$c->etat, 'etiq-gris']; @endphp
  <div class="carte" style="margin-bottom:14px">
    <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:baseline">
      <strong>{{ $c->reference }}</strong>
      <span class="etiq {{ $classe }}">{{ $mot }}</span>
      <span style="color:var(--gris)">{{ $c->vendeur->raison_sociale }}</span>
      <span class="mono" style="margin-left:auto;font-weight:700">
        {{ number_format($c->montant_total, 0, ',', ' ') }} F
      </span>
    </div>

    <div style="color:var(--gris);font-size:.88rem;margin-top:6px">
      @foreach($c->lignes as $l)
        {{ $l->quantite_affichee }} {{ $l->unite_affichee }} — {{ $l->offre->article->designation }}<br>
      @endforeach
    </div>

    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
      @if($c->etat === 'en_attente_paiement')
        <form method="POST" action="{{ route('acheteur.payer', $c) }}">
          @csrf
          <button class="btn btn-sm">Régler {{ number_format($c->montant_total, 0, ',', ' ') }} F</button>
        </form>
        {{-- Dire ce qui se passe vraiment. Laisser croire à un encaissement
             réel serait le seul mensonge de l'application. --}}
        @if(config('paiement.simule'))
          <span class="etiq etiq-ambre" style="align-self:center">
            Paiement simulé — aucun franc ne quitte votre compte
          </span>
        @endif
      @endif

      @if($c->etat === 'en_livraison')
        <form method="POST" action="{{ route('acheteur.recue', $c) }}">
          @csrf
          <button class="btn btn-sm btn-vert">J'ai bien reçu</button>
        </form>
      @endif

      @if(in_array($c->etat, ['prete', 'en_livraison', 'receptionnee']))
        <details>
          <summary class="btn btn-sm btn-clair" style="list-style:none">Signaler un problème</summary>
          <form method="POST" action="{{ route('acheteur.litige', $c) }}" class="carte" style="margin-top:10px">
            @csrf
            <div class="champ">
              <label>Motif</label>
              <select name="motif" required>
                <option value="non_livre">Rien n'est arrivé</option>
                <option value="quantite_manquante">Quantité manquante</option>
                <option value="article_non_conforme">Article non conforme</option>
                <option value="marchandise_abimee">Marchandise abîmée</option>
                <option value="autre">Autre</option>
              </select>
            </div>
            <div class="champ">
              <label>Ce qui s'est passé</label>
              <textarea name="description" rows="3" required minlength="10"></textarea>
            </div>
            <button class="btn btn-sm btn-rouge">Ouvrir le litige</button>
            <p style="color:var(--gris);font-size:.84rem;margin-top:8px">
              Le reversement au vendeur sera gelé le temps de l'arbitrage.
            </p>
          </form>
        </details>
      @endif

      {{-- La note n'apparaît qu'après réception, et une seule fois : c'est ce
           qui fait qu'un avis sur FamFer vaut quelque chose. --}}
      @if(in_array($c->etat, ['receptionnee', 'soldee']) && ! $c->evaluation)
        <details>
          <summary class="btn btn-sm btn-clair" style="list-style:none">Noter le vendeur</summary>
          <form method="POST" action="{{ route('acheteur.noter', $c) }}" class="carte" style="margin-top:10px">
            @csrf
            <div class="champ">
              <label>Votre note sur {{ $c->vendeur->raison_sociale }}</label>
              <select name="note" required>
                <option value="5">★★★★★ — parfait</option>
                <option value="4">★★★★☆ — bien</option>
                <option value="3">★★★☆☆ — correct</option>
                <option value="2">★★☆☆☆ — décevant</option>
                <option value="1">★☆☆☆☆ — mauvais</option>
              </select>
            </div>
            <div class="champ">
              <label>Un mot pour les autres acheteurs <span style="color:var(--gris)">(facultatif)</span></label>
              <textarea name="commentaire" rows="2" maxlength="1000"></textarea>
            </div>
            <button class="btn btn-sm">Publier mon avis</button>
          </form>
        </details>
      @elseif($c->evaluation)
        <span class="etiq etiq-gris" style="align-self:center">
          Noté {{ str_repeat('★', $c->evaluation->note) }}{{ str_repeat('☆', 5 - $c->evaluation->note) }}
        </span>
      @endif
    </div>
  </div>
@empty
  <div class="carte vide">Aucune commande. <a href="{{ route('accueil') }}">Chercher du fer</a></div>
@endforelse
@endsection
