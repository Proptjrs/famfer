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

  <p class="prose petit secondaire" style="margin-top:var(--s4)">
    Les licences citées — CC0, domaine public, CC BY et CC BY-SA — autorisent la
    réutilisation, y compris commerciale, à condition de créditer l'auteur.
    Les fichiers sous CC BY-SA imposent en outre que toute œuvre dérivée soit
    partagée aux mêmes conditions ; ces images sont ici reproduites telles
    quelles, sans modification.
  </p>

  {{-- Les polices sont servies par l'application plutôt que par Google Fonts.
       La licence SIL Open Font l'autorise explicitement, à la même condition
       que les images : citer les auteurs. --}}
  <div class="bloc" style="margin-top:var(--s8)">
    <div class="bloc-tete">
      <h2>Les polices de caractères</h2>
      <span class="sous">servies par l'application, sous licence SIL Open Font</span>
    </div>
    <div class="bloc-corps serre defile-x">
      <table class="tableau">
        <thead>
          <tr>
            <th scope="col">Police</th><th scope="col">Rôle sur le site</th>
            <th scope="col">Auteurs</th><th scope="col">Licence</th>
          </tr>
        </thead>
        <tbody>
          @foreach([
            ['Inter', 'Interface et texte courant',
             'Rasmus Andersson et la fonderie Inter',
             'https://github.com/rsms/inter'],
            ['Archivo', 'Titres et logotype',
             'Omnibus-Type — Hector Gatti et coll.',
             'https://github.com/Omnibus-Type/Archivo'],
            ['IBM Plex Mono', 'Prix, références et codes de remise',
             'Mike Abbink, Paul van der Laan, Pieter van Rosmalen pour IBM',
             'https://github.com/IBM/plex'],
          ] as [$nom, $role, $auteurs, $source])
            <tr>
              <td><strong>{{ $nom }}</strong></td>
              <td class="secondaire">{{ $role }}</td>
              <td class="petit">{{ $auteurs }}</td>
              <td>
                <span class="jeton jeton-neutre">SIL OFL 1.1</span>
                <div class="mini" style="margin-top:var(--s1)">
                  <a href="{{ $source }}" rel="nofollow noopener" class="lien">
                    dépôt d'origine
                  </a>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="bloc-pied">
      La licence SIL Open Font autorise l'usage, la modification et la
      redistribution, y compris commerciale, tant que les fontes ne sont pas
      vendues seules et que la notice de licence les accompagne.
    </div>
  </div>
</div>

@endsection
