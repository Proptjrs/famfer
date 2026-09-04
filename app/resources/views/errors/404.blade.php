@include('errors._page', [
  'code' => '404',
  'titre' => 'Cette page n\'existe pas',
  'texte' => 'Le lien est peut-être erroné, ou le produit a été retiré de la vente
              par sa boutique. Les commandes déjà passées ne sont pas affectées.',
  'conseils' => [
    'Cherchez l\'article dans la barre du haut : il est peut-être vendu par une autre boutique.',
    'Parcourez les rayons — 1 854 produits chez plusieurs quincailliers.',
    'Vérifiez l\'adresse si vous l\'avez tapée à la main.',
  ],
  'actions' => '<a href="' . route('accueil') . '" class="btn btn-lg">Retour à l\'accueil</a>'
    . '<a href="' . route('recherche') . '" class="btn btn-lg btn-clair">Voir le catalogue</a>',
])
