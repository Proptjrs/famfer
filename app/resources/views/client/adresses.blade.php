@extends('layouts.app')
@section('titre', 'Mes adresses')
@section('contenu')

<h1>Mes adresses</h1>
<p style="color:var(--gris);margin-bottom:16px">
  La région détermine les frais de livraison. Ils sont offerts dès 50 000 F d'achat.
</p>

<div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
  <div style="flex:1 1 380px;min-width:0">
    @forelse($adresses as $a)
      <div class="carte" style="margin-bottom:12px;display:flex;gap:12px;flex-wrap:wrap">
        <div style="flex:1 1 220px">
          <strong>{{ $a->destinataire }}</strong>
          @if($a->par_defaut)<span class="etiq etiq-vert">par défaut</span>@endif
          <div style="color:var(--gris-fonce);margin-top:4px">
            {{ $a->telephone }}<br>{{ $a->enUneLigne() }}
          </div>
        </div>
        <form method="POST" action="{{ route('adresses.supprimer', $a) }}">
          @csrf @method('DELETE')
          <button class="btn btn-sm btn-clair">Supprimer</button>
        </form>
      </div>
    @empty
      <div class="carte vide">
        Aucune adresse enregistrée. Ajoutez-en une pour commander plus vite.
      </div>
    @endforelse
  </div>

  <form method="POST" action="{{ route('adresses.ajouter') }}" class="carte"
        style="flex:0 0 320px">
    @csrf
    <h2 style="margin-bottom:12px">Ajouter une adresse</h2>

    <div class="champ"><label>Destinataire</label>
      <input name="destinataire" value="{{ old('destinataire', auth()->user()->name) }}" required>
      @error('destinataire')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Téléphone</label>
      <input name="telephone" value="{{ old('telephone', auth()->user()->telephone) }}" required>
      @error('telephone')<div class="erreur">{{ $message }}</div>@enderror</div>

    <div class="champ"><label>Région</label>
      <select name="region" required>
        @foreach($regions as $r)
          <option value="{{ $r }}" @selected(old('region') === $r)>{{ $r }}</option>
        @endforeach
        <option value="Autre">Autre région</option>
      </select></div>

    <div class="champ"><label>Ville</label>
      <input name="ville" value="{{ old('ville') }}" required></div>

    <div class="champ"><label>Quartier</label>
      <input name="quartier" value="{{ old('quartier') }}" required></div>

    <div class="champ"><label>Repère <span style="color:var(--gris)">(facultatif)</span></label>
      <input name="repere" value="{{ old('repere') }}"
             placeholder="En face de la pharmacie, portail bleu…"></div>

    <label style="display:flex;gap:8px;align-items:center;font-weight:400;margin-bottom:14px">
      <input type="checkbox" name="par_defaut" value="1" style="width:auto">
      En faire mon adresse par défaut
    </label>

    <button class="btn" style="width:100%">Enregistrer</button>
  </form>
</div>

@endsection
