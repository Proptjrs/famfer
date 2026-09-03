{{--
  La carte d'un produit dans une grille.

  Elle porte tout ce qui décide d'un clic sur une place de marché : l'image, le
  nom, le prix, le prix barré avec sa remise, la note, et le fait que ce soit
  en stock ou non. Rien d'autre — une carte qui en dit trop ne se lit plus.

  Variable : $p (Produit)
--}}
<a href="{{ route('produit', $p) }}" class="produit">
  @if($remise = $p->remise())
    <span class="remise">−{{ $remise }} %</span>
  @endif

  <div class="image">
    @include('partials.image', ['p' => $p, 'taille' => 96])
  </div>

  <div class="nom">{{ $p->nom }}</div>

  <div>
    <span class="prix">{{ number_format($p->prix, 0, ',', ' ') }} F</span>
    @if($p->prix_barre && $p->prix_barre > $p->prix)
      <span class="barre">{{ number_format($p->prix_barre, 0, ',', ' ') }} F</span>
    @endif
  </div>

  <div style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--gris)">
    @if($p->nombre_avis)
      <span class="etoiles">{{ str_repeat('★', (int) round($p->noteSurCinq())) }}{{ str_repeat('☆', 5 - (int) round($p->noteSurCinq())) }}</span>
      <span>({{ $p->nombre_avis }})</span>
    @else
      <span>Pas encore d'avis</span>
    @endif
  </div>

  @if(! $p->enStock())
    <span class="etiq etiq-rouge">Rupture de stock</span>
  @elseif($p->stock <= 5)
    {{-- La rareté annoncée n'est pas un artifice ici : c'est le stock réel,
         et il change le comportement d'achat à juste titre. --}}
    <span class="etiq etiq-orange">Plus que {{ $p->stock }} en stock</span>
  @elseif($p->boutique->officielle)
    <span class="etiq etiq-officielle">Boutique officielle</span>
  @endif
</a>
