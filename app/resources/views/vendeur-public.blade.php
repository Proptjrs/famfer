@extends('layouts.app')
@section('titre', $vendeur->raison_sociale)
@section('contenu')

{{--
  La boutique d'une quincaillerie, telle que l'acheteur la voit.

  On lui demande de confier son argent à un séquestre avant d'avoir vu la
  marchandise : il est en droit de savoir chez qui il commande. D'où la note,
  les avis écrits par de vrais acheteurs, l'ancienneté et la commune.
--}}

<section style="background:linear-gradient(135deg,var(--nuit) 0%,var(--acier-2) 100%);
                border-radius:var(--r);padding:34px 32px;margin-bottom:28px;color:#fff">
  <span class="etiq etiq-vert" style="margin-bottom:12px">Établissement vérifié</span>
  <h1 style="color:#fff;margin:10px 0 8px">{{ $vendeur->raison_sociale }}</h1>
  <p style="color:#C3CCD5;margin-bottom:14px">
    {{-- Beaucoup d'adresses contiennent déjà la commune : « Marché central,
         Guédiawaye · Guédiawaye » se lit mal. --}}
    {{ $vendeur->adresse }}@unless(str_contains(
        mb_strtolower($vendeur->adresse), mb_strtolower($vendeur->commune)
      )) · {{ $vendeur->commune }}@endunless
  </p>
  <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center">
    <span style="background:rgba(255,255,255,.1);padding:6px 12px;border-radius:8px">
      @include('partials.note', ['vendeur' => $vendeur])
    </span>
    <span style="color:#C3CCD5;font-size:.88rem">
      Sur FamFer depuis {{ $vendeur->verifie_le?->translatedFormat('F Y') ?? 'peu' }}
    </span>
    <span style="color:#C3CCD5;font-size:.88rem">{{ $offres->count() }} articles en stock</span>
  </div>
</section>

<h2>Ce qu'il tient en stock</h2>
<div class="grille g3" style="margin-bottom:18px">
  {{-- Vingt-quatre cartes suffisent : une maison qui tient tout le catalogue
       en afficherait cinquante d'un bloc, et l'acheteur cherche de toute
       façon un article précis. --}}
  @forelse($offres->take(24) as $o)
    <a href="{{ route('article', $o->article) }}" class="carte produit"
       style="display:flex;gap:14px;align-items:center;padding:14px">
      <div class="vignette" style="flex:0 0 74px;height:66px">
        @include('partials.dessin', [
          'dessin' => $o->article->caracteristiques['dessin'] ?? 'defaut', 'taille' => 58,
        ])
      </div>
      <div style="flex:1;min-width:0">
        <strong style="display:block;line-height:1.3">{{ $o->article->designation }}</strong>
        <div class="mono" style="color:var(--forge);font-weight:700;margin-top:4px">
          {{ number_format($o->prix_par_unite, 0, ',', ' ') }} F
          <span style="color:var(--gris);font-weight:400;font-size:.82rem">
            {{ \App\Support\Unites::avecDeterminant($o->unite_affichee) }}
          </span>
        </div>
        <div style="color:var(--gris);font-size:.8rem">
          {{ number_format($o->disponiblePivot() / 1000, 0, ',', ' ') }} kg disponibles
        </div>
      </div>
    </a>
  @empty
    <div class="carte vide" style="grid-column:1/-1">
      Cette quincaillerie n'a rien en stock pour le moment.
    </div>
  @endforelse
</div>

@if($offres->count() > 24)
  <p style="margin-bottom:30px;color:var(--gris)">
    … et {{ $offres->count() - 24 }} autres références en stock.
    <a href="{{ route('accueil') }}">Chercher un article précis →</a>
  </p>
@else
  <div style="margin-bottom:30px"></div>
@endif

<h2>Ce qu'en disent les acheteurs</h2>
@forelse($avis as $a)
  <div class="carte" style="margin-bottom:10px">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <span style="color:var(--forge);letter-spacing:1px">
        {{ str_repeat('★', $a->note) }}{{ str_repeat('☆', 5 - $a->note) }}
      </span>
      <span style="color:var(--gris);font-size:.83rem">
        commande {{ $a->commande->reference }} · {{ $a->created_at->translatedFormat('j F Y') }}
      </span>
    </div>
    <p style="margin:8px 0 0">{{ $a->commentaire }}</p>
  </div>
@empty
  <div class="carte vide">
    Aucun avis écrit pour l'instant. Seuls les acheteurs ayant reçu leur
    commande peuvent en laisser un.
  </div>
@endforelse

@endsection
