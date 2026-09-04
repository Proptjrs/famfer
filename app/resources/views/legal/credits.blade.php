@extends('layouts.app')
@section('titre', 'Crédits des images')
@section('contenu')

@include('partials.entete', [
  'titre' => 'Crédits des images',
  'sous' => "Citer l'auteur d'une image sous licence libre n'est pas une politesse : c'est une obligation de la licence.",
  'fil' => [
    ['libelle' => 'Accueil', 'url' => route('accueil')],
    ['libelle' => 'Crédits des images'],
  ],
])

<div>
  <p class="prose secondaire" style="margin-bottom:var(--s5)">
    Les illustrations de rayon viennent de
    <a href="https://commons.wikimedia.org" rel="noopener">Wikimedia Commons</a>,
    sous licence libre. Les photos des produits, elles, appartiennent aux
    boutiques qui les téléversent.
  </p>

  <div class="bloc">
    <div class="bloc-corps serre defile-x">
    <table class="tableau">
      <thead>
        <tr>
          <th scope="col">Rayon</th><th scope="col">Auteur</th>
          <th scope="col">Licence</th><th scope="col">Source</th>
        </tr>
      </thead>
      <tbody>
      @foreach($illustrations as $c)
        <tr>
          <td>
            <a href="{{ route('rayon', $c) }}" class="lien">{{ $c->nom }}</a>
            @if($c->parente)
              <div class="mini secondaire">{{ $c->parente->nom }}</div>
            @endif
          </td>
          <td>{{ $c->image_auteur }}</td>
          <td><span class="jeton jeton-neutre">{{ $c->image_licence }}</span></td>
          <td>
            @if($c->image_source)
              <a href="{{ $c->image_source }}" rel="nofollow noopener"
                 style="color:var(--info-ink);font-size:.82rem">page du fichier</a>
            @endif
          </td>
        </tr>
      @endforeach
          </tbody>
    </table>
    </div>
  </div>

  <p style="color:var(--ink-3);font-size:.86rem;margin-top:16px">
    Les licences citées — CC0, domaine public, CC BY et CC BY-SA — autorisent la
    réutilisation, y compris commerciale, à condition de créditer l'auteur.
    Les fichiers sous CC BY-SA imposent en outre que toute œuvre dérivée soit
    partagée aux mêmes conditions ; ces images sont ici reproduites telles
    quelles, sans modification.
  </p>
</div>

@endsection
