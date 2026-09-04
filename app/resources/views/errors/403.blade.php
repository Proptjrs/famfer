@include('errors._page', [
  'code' => '403',
  'titre' => 'Cet écran ne vous est pas ouvert',
  'texte' => 'Vous êtes connecté, mais votre rôle ne donne pas accès à cette page.
              C\'est voulu : sur une place de marché, un vendeur ne doit jamais voir
              le commerce d\'un autre, ni un compte quelconque atteindre
              l\'administration.',
  'conseils' => [
    'Si vous tenez une boutique, passez par <strong>Ma boutique</strong> dans le menu du compte.',
    'Si vous cherchez une de vos commandes, elle est dans <strong>Mes commandes</strong>.',
    'Si vous pensez que c\'est une erreur, écrivez à l\'administration de FamFer.',
  ],
  'actions' => '<a href="' . route('accueil') . '" class="btn btn-lg">Retour à l\'accueil</a>'
    . (auth()->check()
        ? '<a href="' . route('compte') . '" class="btn btn-lg btn-clair">Mon compte</a>'
        : '<a href="' . route('connexion') . '" class="btn btn-lg btn-clair">Se connecter</a>'),
])
