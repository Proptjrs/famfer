@extends('layouts.app')
@section('titre', 'Crédits des images')
@section('contenu')

<div style="max-width:80ch">
  <h1>Crédits des images</h1>
  <p style="color:var(--gris);margin-bottom:16px">
    Les illustrations de rayon viennent de
    <a href="https://commons.wikimedia.org" style="color:var(--bleu)">Wikimedia Commons</a>,
    sous licence libre. Citer leur auteur n'est pas une politesse : c'est une
    obligation de ces licences. Les photos des produits, elles, appartiennent
    aux boutiques qui les téléversent.
  </p>

  <div class="carte large">
    <table>
      <tr><th>Rayon</th><th>Auteur</th><th>Licence</th><th>Source</th></tr>
      @foreach($illustrations as $c)
        <tr>
          <td>
            <a href="{{ route('rayon', $c) }}" style="color:var(--bleu)">{{ $c->nom }}</a>
            @if($c->parente)
              <br><span style="color:var(--gris);font-size:.8rem">{{ $c->parente->nom }}</span>
            @endif
          </td>
          <td>{{ $c->image_auteur }}</td>
          <td><span class="etiq etiq-gris">{{ $c->image_licence }}</span></td>
          <td>
            @if($c->image_source)
              <a href="{{ $c->image_source }}" rel="nofollow noopener"
                 style="color:var(--bleu);font-size:.82rem">page du fichier</a>
            @endif
          </td>
        </tr>
      @endforeach
    </table>
  </div>

  <p style="color:var(--gris);font-size:.86rem;margin-top:16px">
    Les licences citées — CC0, domaine public, CC BY et CC BY-SA — autorisent la
    réutilisation, y compris commerciale, à condition de créditer l'auteur.
    Les fichiers sous CC BY-SA imposent en outre que toute œuvre dérivée soit
    partagée aux mêmes conditions ; ces images sont ici reproduites telles
    quelles, sans modification.
  </p>
</div>

@endsection
