{{--
  L'état vide.

  L'ancien disait « aucun résultat » et s'arrêtait là. Un écran vide qui ne
  propose rien est un cul-de-sac : l'utilisateur y arrive, ne comprend pas s'il
  s'est trompé ou s'il n'y a réellement rien, et repart.

  Celui-ci dit trois choses : ce qui est vide, pourquoi ça l'est, et quoi faire
  ensuite. Le lien d'action n'est pas obligatoire — parfois il n'y a rien à
  faire — mais son absence doit être un choix.

  Variables :
    $titre  (string)
    $texte  (?string)
    $icone  (?string)   — clé de partials.symbole
    $action (?string)   — HTML déjà échappé
--}}
<div class="vide">
  <span class="icone-vide" aria-hidden="true">
    @include('partials.symbole', ['nom' => $icone ?? 'boite', 'taille' => 22])
  </span>

  <h3>{{ $titre }}</h3>

  @if(!empty($texte))
    <p>{{ $texte }}</p>
  @endif

  @if(!empty($action))
    <div class="rang-sm" style="justify-content:center">{!! $action !!}</div>
  @endif
</div>
