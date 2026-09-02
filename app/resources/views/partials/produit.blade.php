{{--
  La carte d'un article dans une liste.

  Elle porte le prix le plus bas du marché plutôt que le prix d'un vendeur : ce
  que l'acheteur veut savoir en parcourant une liste, c'est à partir de combien
  il peut avoir la marchandise. Le détail vendeur par vendeur vient ensuite.

  Variables : $a (Article), $compact (bool, facultatif)
--}}
@php
  $offres = $a->offresVisibles();
  $meilleure = $offres->sortBy(fn ($o) => $o->prixParPivot())->first();
  $dessin = $a->caracteristiques['dessin'] ?? 'defaut';
@endphp

<a href="{{ route('article', $a) }}" class="carte produit"
   style="display:flex;flex-direction:column;gap:14px;padding:16px">

  <div class="vignette" style="width:100%;aspect-ratio:16/10">
    @include('partials.dessin', ['dessin' => $dessin, 'taille' => $compact ?? false ? 84 : 96])
  </div>

  <div style="flex:1;min-width:0">
    <strong style="display:block;line-height:1.35">{{ $a->designation }}</strong>
    <div style="color:var(--gris);font-size:.83rem;margin-top:3px">
      {{ $a->reference }}
      @if($m = $a->caracteristiques['masse_lineique_g_m'] ?? null)
        · {{ number_format($m / 1000, 3, ',', ' ') }} kg/m
      @endif
    </div>
  </div>

  <div style="border-top:1px solid var(--bord);padding-top:12px;
              display:flex;align-items:baseline;gap:8px;flex-wrap:wrap">
    @if($meilleure)
      <span style="color:var(--gris);font-size:.8rem">dès</span>
      <span class="mono" style="font-family:var(--titre);font-size:1.5rem;font-weight:700;
                                color:var(--forge);line-height:1">
        {{ number_format($meilleure->prix_par_unite, 0, ',', ' ') }} F
      </span>
      <span style="color:var(--gris);font-size:.82rem">
        {{ \App\Support\Unites::avecDeterminant($meilleure->unite_affichee) }}
      </span>
      <span class="etiq etiq-vert" style="margin-left:auto">
        {{ $offres->count() }} vendeur{{ $offres->count() > 1 ? 's' : '' }}
      </span>
    @else
      <span class="etiq etiq-gris">Aucun vendeur en stock</span>
    @endif
  </div>
</a>
