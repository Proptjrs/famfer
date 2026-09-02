<?php

namespace Database\Seeders;

use App\Models\Acheteur;
use App\Models\Commande;
use App\Models\Offre;
use App\Models\User;
use App\Models\Vendeur;
use App\Services\CommandeService;
use App\Services\EvaluationService;
use App\Services\PaiementService;
use Illuminate\Database\Seeder;

/**
 * Un passé pour la place de marché : des commandes menées jusqu'au bout, notées.
 *
 * La note des vendeurs était auparavant semée en dur, ce qui la rendait fausse
 * par construction : rien ne l'adossait à une vente. Ici, chaque étoile provient
 * d'une commande réellement créée, payée, acceptée, remise, reçue et notée par
 * les services de l'application — les mêmes que ceux qu'un acheteur emprunte.
 *
 * La conséquence est visible : le grand livre est équilibré après ce seeder, les
 * stocks des offres concernées ont réellement baissé, et la moyenne affichée se
 * recalcule si l'on ajoute un avis. Rien n'est décoratif.
 */
class HistoriqueSeeder extends Seeder
{
    /** Les clients de la démonstration, et l'avis qu'ils laisseront. */
    private const CLIENTS = [
        ['Entreprise Ndiaye BTP', 'ndiaye.btp@chantier.sn', 'entreprise', 5,
            'Fer conforme, pesé devant moi. Livré le matin même.'],
        ['Coumba SARR', 'coumba.sarr@gmail.com', 'particulier', 4,
            'Bon prix et bon accueil. Une barre de retard sur la commande, réglé le lendemain.'],
        ['Chantier Keur Massar', 'keur.massar@chantier.sn', 'chantier', 5,
            'Trois commandes chez eux, jamais un souci sur la quantité.'],
        ['Ibrahima DIOP', 'ibrahima.diop@gmail.com', 'particulier', 3,
            'La marchandise est bonne mais j\'ai attendu deux jours de plus que prévu.'],
        ['Sococim Travaux', 'achats@sococim-travaux.sn', 'entreprise', 5,
            'Le seul qui tient la tôle bac en quantité. Facturation nette.'],
        ['Ateliers Fatick', 'ateliers.fatick@gmail.com', 'entreprise', 4,
            'Correct. Prix un peu au-dessus du marché mais le stock est réel.'],
    ];

    public function run(): void
    {
        if (Commande::count() > 0) {
            $this->command?->warn('Des commandes existent déjà : historique non semé.');

            return;
        }

        $commandes = app(CommandeService::class);
        $paiements = app(PaiementService::class);
        $evaluations = app(EvaluationService::class);

        $vendeurs = Vendeur::where('statut', 'verifie')->orderBy('id')->get();
        if ($vendeurs->isEmpty()) {
            $this->command?->warn('Aucun vendeur vérifié : lancez VendeursSeeder d\'abord.');

            return;
        }

        $notees = 0;

        foreach (self::CLIENTS as $rang => [$nom, $email, $genre, $note, $mot]) {
            $acheteur = $this->acheteur($nom, $email, $genre);

            // Chaque client passe chez un vendeur différent, à tour de rôle :
            // c'est ce qui répartit les avis sur toutes les maisons.
            $vendeur = $vendeurs[$rang % $vendeurs->count()];

            $offre = Offre::with('article.unitesVente')
                ->where('vendeur_id', $vendeur->id)
                ->where('actif', true)
                ->orderBy('id')
                ->get()
                ->first(fn (Offre $o) => $o->disponiblePivot() > 0);

            if (! $offre) {
                continue;
            }

            $commande = $commandes->creer($acheteur, [[
                'offre' => $offre,
                'quantite' => '2',
                'unite' => $offre->unite_affichee,
            ]]);

            // Le parcours complet, par les services : le paiement entre au
            // séquestre, la remise sort le stock, la réception libère le vendeur.
            $paiements->traiterRappel(
                $commande, 'wave', 'HIST-' . $commande->reference, $commande->montant_total,
                fraisOperateur: (int) round($commande->montant_total * 0.015)
            );

            $utilisateurVendeur = $vendeur->utilisateur_id;
            $commandes->accepter($commande->fresh(), $utilisateurVendeur);
            $commandes->marquerPrete($commande->fresh(), $utilisateurVendeur);
            $commandes->remettre($commande->fresh(), $utilisateurVendeur);

            // En retrait au comptoir, la remise vaut réception.
            if ($commande->fresh()->etat === 'en_livraison') {
                $commandes->confirmerReception($commande->fresh(), $acheteur->utilisateur_id);
            }

            $evaluations->noter($commande->fresh(), $note, $mot);
            $notees++;
        }

        $this->command?->info(sprintf(
            '%d commandes menées jusqu\'à la note. Moyennes recalculées : %s',
            $notees,
            Vendeur::where('statut', 'verifie')->orderBy('id')->get()
                ->map(fn (Vendeur $v) => $v->raison_sociale . ' ' . ($v->noteSurCinq() ?? '—'))
                ->implode(' · ')
        ));
    }

    private function acheteur(string $nom, string $email, string $genre): Acheteur
    {
        $utilisateur = User::firstOrCreate(
            ['email' => $email],
            ['name' => $nom, 'password' => 'password']
        );

        return Acheteur::firstOrCreate(
            ['utilisateur_id' => $utilisateur->id],
            ['genre' => $genre, 'telephone' => '+221 76 000 00 00']
        );
    }
}
