{{--
  La page d'expiration.

  Laravel affiche « Page Expired », en anglais et sans explication : c'est le
  message le plus déroutant du cadriciel, et il tombe précisément quand
  quelqu'un vient de remplir un long formulaire. Il mérite une phrase qui dise
  ce qui s'est passé et ce que la personne n'a pas perdu.
--}}
@include('errors._page', [
  'code' => '419',
  'titre' => 'Votre session a expiré',
  'texte' => 'La page est restée ouverte trop longtemps, et le jeton de sécurité
              qui protège vos envois n\'est plus valable. Rien n\'a été enregistré,
              et rien n\'a été perdu de votre compte.',
  'conseils' => [
    'Revenez en arrière puis renvoyez le formulaire : votre saisie y est encore.',
    'Si vous étiez déconnecté entre-temps, reconnectez-vous d\'abord.',
    'Votre panier est conservé.',
  ],
  'actions' => '<a href="' . url()->previous() . '" class="btn btn-lg">Revenir en arrière</a>'
    . '<a href="' . route('accueil') . '" class="btn btn-lg btn-clair">Accueil</a>',
])
