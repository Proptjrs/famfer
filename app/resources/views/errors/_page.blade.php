{{--
  Le corps commun des pages d'erreur qui peuvent s'appuyer sur l'application.

  404, 403 et 419 surviennent alors que tout fonctionne : la base répond, le
  gabarit se rend, la navigation reste utile. Elles héritent donc du site
  complet — l'en-tête, les rayons, le pied — parce qu'une page d'erreur dont on
  ne peut pas repartir est un cul-de-sac.

  500 et 503 ne peuvent pas : le gabarit interroge la base pour la barre des
  rayons, et le faire depuis une page d'erreur causée par une panne de base
  déclencherait une seconde erreur à l'intérieur de la première. Celles-là sont
  autonomes.

  Variables : $code, $titre, $texte, $conseils (array), $actions (?string)
--}}
@extends('layouts.app')
@section('titre', $titre)
@section('contenu')

<div class="page-moyenne" style="padding-block:var(--s10)">
  <div style="text-align:center">
    <div class="chiffre" style="font-size:var(--t-4xl);font-weight:600;
                color:var(--ink-faint);letter-spacing:.06em">{{ $code }}</div>

    <h1 style="margin-top:var(--s2)">{{ $titre }}</h1>

    <p class="secondaire" style="margin:var(--s3) auto 0;max-width:48ch">
      {{ $texte }}
    </p>
  </div>

  @if(!empty($conseils))
    <div class="bloc" style="margin-top:var(--s8)">
      <div class="bloc-tete"><h2>Ce que vous pouvez faire</h2></div>
      <div class="bloc-corps">
        <ul class="pile-sm" style="list-style:none;padding:0">
          @foreach($conseils as $conseil)
            <li class="rang-serre" style="align-items:flex-start;gap:var(--s3)">
              <span style="color:var(--brand-strong);flex:none;margin-top:.125rem">
                @include('partials.symbole', ['nom' => 'fleche-droite', 'taille' => 15])
              </span>
              <span>{!! $conseil !!}</span>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif

  <div class="rang" style="justify-content:center;margin-top:var(--s8)">
    {!! $actions ?? '<a href="' . route('accueil') . '" class="btn btn-lg">Retour à l\'accueil</a>' !!}
  </div>
</div>

@endsection
