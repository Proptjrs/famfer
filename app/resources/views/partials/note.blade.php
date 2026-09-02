{{--
  La note d'un vendeur, en étoiles.

  Un vendeur sans aucun avis n'est pas un vendeur à zéro étoile : c'est un
  vendeur dont on ne sait rien. Le dire ainsi vaut mieux que d'afficher une
  moyenne inventée, qui le condamnerait avant sa première vente.

  Variables : $vendeur (Vendeur), $lien (bool, facultatif)
--}}
@php
  $sur5 = $vendeur->noteSurCinq();
  // La demi-étoile est dessinée, pas citée : le caractère « ⯨ » manque à la
  // plupart des polices Android et s'affiche alors en carré vide.
  $part = $sur5 === null ? 0 : max(0, min(100, $sur5 / 5 * 100));
@endphp

<span style="display:inline-flex;align-items:center;gap:6px;font-size:.86rem">
  @if($sur5 === null)
    <span class="etiq etiq-gris">Nouveau vendeur</span>
  @else
    <span title="{{ $sur5 }} sur 5" aria-label="{{ $sur5 }} étoiles sur 5"
          style="position:relative;display:inline-block;line-height:1;letter-spacing:1px;
                 color:#C9D1D9;white-space:nowrap">
      ★★★★★
      <span aria-hidden="true"
            style="position:absolute;inset:0;width:{{ $part }}%;overflow:hidden;
                   color:var(--forge);white-space:nowrap">★★★★★</span>
    </span>
    <span class="mono" style="font-weight:600">{{ number_format($sur5, 1, ',', '') }}</span>
    <span style="color:var(--gris)">
      ({{ $vendeur->nombre_evaluations }} avis)
    </span>
  @endif

  @if($lien ?? false)
    <a href="{{ route('vendeur.public', $vendeur) }}" style="font-size:.84rem">Voir la boutique →</a>
  @endif
</span>
