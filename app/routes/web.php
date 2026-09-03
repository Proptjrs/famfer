<?php

use App\Http\Controllers\{AdminController, AuthController, BoutiqueController,
    CatalogueController, ClientController, CompteController, MotDePasseController,
    PanierController, VendeurController};
use Illuminate\Support\Facades\Route;

/* ---- Ouvert à tous ----
 | Le catalogue se parcourt sans compte : c'est ce qui amène les clients. On ne
 | demande d'identité qu'au moment de commander. */
Route::get('/', [CatalogueController::class, 'accueil'])->name('accueil');
Route::get('/recherche', [CatalogueController::class, 'recherche'])->name('recherche');
Route::get('/rayon/{categorie:slug}', [CatalogueController::class, 'rayon'])->name('rayon');
Route::get('/produit/{produit:slug}', [CatalogueController::class, 'produit'])->name('produit');
Route::get('/boutique/{boutique:slug}', [BoutiqueController::class, 'vitrine'])->name('boutique');
Route::view('/conditions', 'legal.conditions')->name('conditions');

/* ---- Entrée et sortie ---- */
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'formulaireConnexion'])->name('connexion');
    // Cinq tentatives par minute : sans cela, un robot essaie des mots de passe
    // indéfiniment sur les comptes des commerçants.
    Route::post('/connexion', [AuthController::class, 'connecter'])->middleware('throttle:5,1');
    Route::get('/inscription', [AuthController::class, 'formulaireInscription'])->name('inscription');
    Route::post('/inscription', [AuthController::class, 'inscrire'])->middleware('throttle:5,1');

    Route::get('/mot-de-passe-oublie', [MotDePasseController::class, 'formulaireDemande'])
        ->name('mdp.oubli');
    Route::post('/mot-de-passe-oublie', [MotDePasseController::class, 'envoyer'])
        ->middleware('throttle:6,1');
    Route::get('/mot-de-passe/{token}', [MotDePasseController::class, 'formulaireReinitialisation'])
        ->name('password.reset');
    Route::post('/mot-de-passe', [MotDePasseController::class, 'reinitialiser'])->name('mdp.reinitialiser');
});
Route::post('/deconnexion', [AuthController::class, 'deconnecter'])
    ->middleware('auth')->name('deconnexion');

/* ---- Le panier ----
 | Il vit en session et n'exige pas de compte : demander à s'inscrire avant
 | d'avoir rempli son panier fait fuir la moitié des visiteurs. */
Route::get('/panier', [PanierController::class, 'voir'])->name('panier');
Route::post('/panier/{produit}', [PanierController::class, 'ajouter'])
    ->whereNumber('produit')->name('panier.ajouter');
Route::put('/panier/{produit}', [PanierController::class, 'modifier'])
    ->whereNumber('produit')->name('panier.modifier');
Route::delete('/panier/{produit}', [PanierController::class, 'retirer'])
    ->whereNumber('produit')->name('panier.retirer');

/* ---- L'espace du client ---- */
Route::middleware('auth')->group(function () {
    Route::get('/commande', [ClientController::class, 'formulaireCommande'])->name('commande');
    Route::post('/commande', [ClientController::class, 'valider'])->name('commande.valider');

    Route::get('/mes-commandes', [ClientController::class, 'commandes'])->name('mes-commandes');
    Route::get('/mes-commandes/{commande}', [ClientController::class, 'commande'])
        ->name('mes-commandes.detail');
    Route::post('/mes-commandes/{commande}/annuler', [ClientController::class, 'annuler'])
        ->name('commande.annuler');
    Route::post('/mes-commandes/{commande}/noter', [ClientController::class, 'noter'])
        ->name('commande.noter');

    Route::get('/mon-compte', [CompteController::class, 'profil'])->name('compte');
    Route::put('/mon-compte', [CompteController::class, 'majProfil'])->name('compte.maj');
    Route::put('/mon-compte/mot-de-passe', [CompteController::class, 'majMotDePasse'])->name('compte.mdp');

    Route::get('/mes-adresses', [ClientController::class, 'adresses'])->name('adresses');
    Route::post('/mes-adresses', [ClientController::class, 'ajouterAdresse'])->name('adresses.ajouter');
    Route::delete('/mes-adresses/{adresse}', [ClientController::class, 'supprimerAdresse'])
        ->name('adresses.supprimer');
});

/* ---- L'espace du vendeur ----
 | Chaque action vérifie que le produit ou la commande le concerne : sur une
 | place de marché, un vendeur ne doit jamais voir le commerce d'un autre. */
Route::middleware('auth')->prefix('vendeur')->name('vendeur.')->group(function () {
    Route::get('/ouvrir-boutique', [VendeurController::class, 'formulaireBoutique'])->name('ouvrir');
    Route::post('/ouvrir-boutique', [VendeurController::class, 'ouvrir']);

    Route::get('/', [VendeurController::class, 'tableau'])->name('tableau');
    Route::get('/produits', [VendeurController::class, 'produits'])->name('produits');
    Route::get('/produits/nouveau', [VendeurController::class, 'nouveauProduit'])->name('produit.nouveau');
    Route::post('/produits', [VendeurController::class, 'publier'])->name('produit.publier');
    Route::get('/produit/{produit}', [VendeurController::class, 'editerProduit'])->name('produit.editer');
    Route::put('/produit/{produit}', [VendeurController::class, 'modifier'])->name('produit.modifier');
    Route::post('/produit/{produit}/photos', [VendeurController::class, 'televerser'])
        ->name('produit.photos');
    Route::delete('/photo/{photo}', [VendeurController::class, 'supprimerPhoto'])
        ->name('photo.supprimer');
    Route::post('/produit/{produit}/bascule', [VendeurController::class, 'basculer'])->name('produit.bascule');

    Route::get('/commandes', [VendeurController::class, 'commandes'])->name('commandes');
    Route::post('/commande/{commande}/expedier', [VendeurController::class, 'expedier'])->name('expedier');
    Route::post('/commande/{commande}/livrer', [VendeurController::class, 'livrer'])->name('livrer');

    Route::get('/ma-boutique', [VendeurController::class, 'maBoutique'])->name('boutique');
    Route::put('/ma-boutique', [VendeurController::class, 'majBoutique'])->name('boutique.maj');
});

/* ---- L'administration ----
 | Fermée au rôle, et pas seulement à l'authentification : sans ce garde-fou,
 | tout compte connecté pourrait valider sa propre boutique. */
Route::middleware(['auth', 'admin'])->prefix('administration')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'tableau'])->name('tableau');
    Route::get('/boutiques', [AdminController::class, 'boutiques'])->name('boutiques');
    Route::post('/boutique/{boutique}/activer', [AdminController::class, 'activer'])->name('activer');
    Route::post('/boutique/{boutique}/suspendre', [AdminController::class, 'suspendre'])->name('suspendre');
    Route::post('/boutique/{boutique}/officielle', [AdminController::class, 'officielle'])
        ->name('officielle');
    Route::get('/commandes', [AdminController::class, 'commandes'])->name('commandes');
});
