@extends('layouts.app')
@section('titre', 'Mes produits')
@section('contenu')

@include('partials.entete', [
  'titre' => 'Mes produits',
  'sous' => $produits->total() . ' produit(s) au catalogue de votre boutique.',
  'fil' => [
    ['libelle' => 'Ma boutique', 'url' => route('vendeur.tableau')],
    ['libelle' => 'Mes produits'],
  ],
  'actions' => '<a href="' . route('vendeur.produit.nouveau') . '" class="btn">Ajouter un produit</a>',
])

@if($produits->isEmpty())
  <div class="bloc">
    @include('partials.vide', [
      'icone' => 'boite',
      'titre' => 'Aucun produit publié',
      'texte' => 'Votre boutique n\'apparaîtra dans les rayons qu\'une fois qu\'elle contient des articles.',
      'action' => '<a href="' . route('vendeur.produit.nouveau') . '" class="btn">Publier le premier</a>',
    ])
  </div>
@else
  <div class="bloc">
    <div class="bloc-corps serre defile-x">
      <table class="tableau">
        <thead>
          <tr>
            <th scope="col">Produit</th>
            <th scope="col">Catégorie</th>
            <th scope="col" class="num">Prix</th>
            <th scope="col" class="num">Stock</th>
            <th scope="col" class="num">Ventes</th>
            <th scope="col"><span class="visuellement-cache">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach($produits as $p)
            <tr>
              <td>
                <div class="rang-serre" style="gap:var(--s3)">
                  {{-- La vignette dans la liste : c'est ce qui fait voir d'un
                       coup d'œil quels produits n'ont pas encore de photo. --}}
                  <span style="flex:0 0 2.75rem;height:2.75rem;background:var(--surface-2);
                               border-radius:var(--r-sm);display:grid;place-items:center;
                               overflow:hidden">
                    @include('partials.image', ['p' => $p, 'taille' => 34])
                  </span>
                  <span style="min-width:0">
                    <a href="{{ route('vendeur.produit.editer', $p) }}"
                       style="font-weight:650">{{ $p->nom }}</a>
                    <span class="rang-sm" style="gap:var(--s1);margin-top:var(--s1)">
                      @unless($p->actif)<span class="jeton jeton-neutre">retiré</span>@endunless
                      @if($p->remise())
                        <span class="jeton jeton-alerte">−{{ $p->remise() }} %</span>
                      @endif
                      @if($p->photos->isEmpty())
                        <span class="jeton jeton-info">sans photo</span>
                      @endif
                    </span>
                  </span>
                </div>
              </td>

              <td class="secondaire">{{ $p->categorie->nom }}</td>

              <td class="num">
                {{ number_format($p->prix, 0, ',', ' ') }} F
                @if($p->prix_barre)
                  <div class="mini secondaire" style="text-decoration:line-through">
                    {{ number_format($p->prix_barre, 0, ',', ' ') }} F
                  </div>
                @endif
              </td>

              <td class="num" style="{{ $p->stock === 0 ? 'color:var(--grave-ink);font-weight:700' : '' }}">
                {{ $p->stock }}
                @if($p->stock === 0)
                  <div class="mini">rupture</div>
                @elseif($p->stock <= 5)
                  <div class="mini" style="color:var(--alerte-ink)">bientôt</div>
                @endif
              </td>

              <td class="num">{{ $p->nombre_ventes }}</td>

              <td style="text-align:right;white-space:nowrap">
                {{-- Retirer plutôt que supprimer : effacer le produit emporterait
                     les lignes de commande qui le désignent, donc l'historique. --}}
                <div class="rang-sm" style="justify-content:flex-end;gap:var(--s2)">
                  <a href="{{ route('vendeur.produit.editer', $p) }}"
                     class="btn btn-sm btn-clair">Modifier</a>
                  <form method="POST" action="{{ route('vendeur.produit.bascule', $p) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-fantome">
                      {{ $p->actif ? 'Retirer' : 'Remettre' }}
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @if($produits->hasPages())
    <div style="margin-top:var(--s6)">{{ $produits->links() }}</div>
  @endif
@endif

@endsection
