<?php

use App\Http\Controllers\{AcheteurController, AdminController, AuthController,
    CompteController, MotDePasseController, PanierController, RappelPaiementController,
    RechercheController, VendeurController};
use Illuminate\Support\Facades\Route;

/* ---- Ouvert à tous ----
 | La recherche et la comparaison des prix se font sans compte : c'est ce qui
 | amène les acheteurs. On ne demande d'identité qu'au moment de commander,
 | c'est-à-dire au moment où l'argent entre en jeu. */
Route::get('/', [RechercheController::class, 'accueil'])->name('accueil');
Route::get('/article/{article}', [RechercheController::class, 'article'])->name('article');
// La fiche d'une quincaillerie : ses articles, sa note, les avis de ses clients.
Route::get('/vendeur/{vendeur}', [RechercheController::class, 'vendeur'])->name('vendeur.public');

// Ce que la plateforme fait de l'argent qu'elle retient. Une place de marché
// qui séquestre des fonds sans le dire nulle part se passerait de l'expliquer.
Route::view('/conditions', 'legal.conditions')->name('conditions');

/* ---- Le rappel de l'opérateur de paiement ----
 | Hors de tout groupe : l'opérateur n'ouvre pas de session, ne porte pas de
 | jeton CSRF et ne suit pas les redirections. Seule sa signature l'autorise. */
Route::post('/rappel-paiement/{operateur}', RappelPaiementController::class)
    ->whereIn('operateur', ['wave', 'om'])
    ->name('paiement.rappel');

/* ---- Entrée et sortie ---- */
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'formulaireConnexion'])->name('connexion');
    // Cinq tentatives par minute et par adresse IP. Sans cela, un robot essaie
    // des mots de passe indéfiniment sur une plateforme qui détient l'argent
    // des gens, et rien dans le journal ne le distingue d'un client distrait.
    Route::post('/connexion', [AuthController::class, 'connecter'])
        ->middleware('throttle:5,1');
    Route::get('/inscription', [AuthController::class, 'formulaireInscription'])->name('inscription');
    // L'inscription aussi : c'est par là qu'on découvre quelles adresses sont
    // déjà prises, une réponse à la fois.
    Route::post('/inscription', [AuthController::class, 'inscrire'])
        ->middleware('throttle:5,1');

    // Un commerçant qui perd son mot de passe perd son stock et son argent :
    // ce chemin n'est pas une finition.
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

/* ---- Le panier et les commandes ---- */
Route::middleware('auth')->group(function () {
    Route::get('/panier', [PanierController::class, 'voir'])->name('panier.voir');
    Route::post('/panier/valider', [PanierController::class, 'valider'])->name('panier.valider');
    // « whereNumber » est indispensable : sans lui, « /panier/valider » est
    // capturé par « /panier/{offre} », déclaré plus haut, et « valider » part
    // à la base comme identifiant d'offre.
    Route::post('/panier/{offre}', [PanierController::class, 'ajouter'])
        ->whereNumber('offre')->name('panier.ajouter');
    Route::delete('/panier/{offre}', [PanierController::class, 'retirer'])
        ->whereNumber('offre')->name('panier.retirer');

    Route::get('/mon-compte', [CompteController::class, 'profil'])->name('compte');
    Route::put('/mon-compte', [CompteController::class, 'majProfil'])->name('compte.maj');
    Route::put('/mon-compte/mot-de-passe', [CompteController::class, 'majMotDePasse'])
        ->name('compte.mdp');

    Route::get('/mes-commandes', [AcheteurController::class, 'commandes'])->name('acheteur.commandes');
    Route::post('/commande/{commande}/payer', [AcheteurController::class, 'payer'])->name('acheteur.payer');
    Route::post('/commande/{commande}/recue', [AcheteurController::class, 'confirmerReception'])
        ->name('acheteur.recue');
    Route::post('/commande/{commande}/litige', [AcheteurController::class, 'ouvrirLitige'])
        ->name('acheteur.litige');
    Route::post('/commande/{commande}/noter', [AcheteurController::class, 'noter'])
        ->name('acheteur.noter');
});

/* ---- L'espace du vendeur ----
 | Chaque action vérifie que la commande ou l'offre lui appartient : sur une
 | place de marché, un vendeur ne doit jamais voir le commerce d'un autre. */
Route::middleware('auth')->prefix('commerce')->name('vendeur.')->group(function () {
    // La demande précède tout : ces deux routes sont les seules accessibles à
    // qui n'est pas encore vendeur.
    Route::get('/devenir-vendeur', [VendeurController::class, 'formulaireDemande'])->name('demande');
    Route::post('/devenir-vendeur', [VendeurController::class, 'demander']);

    Route::get('/', [VendeurController::class, 'tableau'])->name('tableau');
    Route::get('/offres/nouvelle', [VendeurController::class, 'nouvelleOffre'])->name('offre.nouvelle');
    Route::post('/offres', [VendeurController::class, 'publier'])->name('offre.publier');
    Route::put('/offre/{offre}', [VendeurController::class, 'modifierOffre'])->name('offre.modifier');
    Route::post('/offre/{offre}/bascule', [VendeurController::class, 'basculerOffre'])->name('offre.bascule');
    Route::get('/offres', [VendeurController::class, 'offres'])->name('offres');
    Route::post('/offre/{offre}/stock', [VendeurController::class, 'approvisionner'])->name('stock');
    Route::post('/commande/{commande}/accepter', [VendeurController::class, 'accepter'])->name('accepter');
    Route::post('/commande/{commande}/refuser', [VendeurController::class, 'refuser'])->name('refuser');
    Route::post('/commande/{commande}/prete', [VendeurController::class, 'preparer'])->name('prete');
    Route::post('/commande/{commande}/remettre', [VendeurController::class, 'remettre'])->name('remettre');
    Route::get('/commandes', [VendeurController::class, 'commandes'])->name('commandes');
    Route::get('/argent', [VendeurController::class, 'argent'])->name('argent');
    Route::get('/offre/{offre}/journal', [VendeurController::class, 'journal'])->name('journal');
    Route::put('/versement', [VendeurController::class, 'enregistrerVersement'])->name('versement');
    Route::post('/reversement', [VendeurController::class, 'demanderReversement'])->name('reversement');
});

/* ---- L'administration ---- */
Route::middleware(['auth', 'admin'])->prefix('administration')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'tableau'])->name('tableau');
    Route::post('/vendeur/{vendeur}/verifier', [AdminController::class, 'verifier'])->name('verifier');
    Route::post('/vendeur/{vendeur}/refuser', [AdminController::class, 'refuser'])->name('refuser.vendeur');
    Route::put('/vendeur/{vendeur}/commission', [AdminController::class, 'fixerCommission'])
        ->name('commission');
    Route::post('/litige/{litige}/trancher', [AdminController::class, 'trancher'])->name('trancher');
});
