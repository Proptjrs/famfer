<?php

namespace Database\Seeders;

use App\Models\Adresse;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use App\Services\Notation;
use App\Services\PasseCommande;
use App\Services\Panier;
use Illuminate\Database\Seeder;

/**
 * Des clients, des commandes livrées, et les avis qui en découlent.
 *
 * Les commandes passent par les services de l'application, pas par des
 * insertions directes : les stocks baissent réellement, les compteurs de vente
 * montent, et les notes affichées proviennent d'achats effectifs. Un semis qui
 * écrirait les notes à la main afficherait des étoiles que rien ne soutient.
 */
class ClientsSeeder extends Seeder
{
    private const CLIENTS = [
        ['Entreprise Ndiaye BTP', 'ndiaye.btp@chantier.sn', 'Dakar', 'Plateau', 5,
            'Livraison rapide', 'Commandé le matin, reçu l\'après-midi. Fer conforme.'],
        ['Coumba SARR', 'coumba.sarr@gmail.com', 'Thiès', 'Randoulène', 4,
            'Bon rapport qualité-prix', 'Correct, mais une journée de retard sur la livraison.'],
        ['Chantier Keur Massar', 'keur.massar@chantier.sn', 'Dakar', 'Keur Massar', 5,
            'Rien à redire', 'Troisième commande, jamais un souci sur les quantités.'],
        ['Ibrahima DIOP', 'ibrahima.diop@gmail.com', 'Dakar', 'Grand Yoff', 3,
            'Moyen', 'La marchandise est bonne, l\'emballage laisse à désirer.'],
        ['Sococim Travaux', 'achats@sococim-travaux.sn', 'Thiès', 'Rufisque', 5,
            'Fournisseur fiable', 'Le seul à tenir la tôle bac en quantité.'],
        ['Ateliers Fatick', 'ateliers.fatick@gmail.com', 'Kaolack', 'Centre', 4,
            'Satisfait', 'Prix un peu au-dessus du marché, mais le stock est réel.'],
    ];

    public function run(): void
    {
        if (Commande::count() > 0) {
            $this->command?->warn('Des commandes existent déjà.');

            return;
        }

        $passe = app(PasseCommande::class);
        $panier = app(Panier::class);
        $notation = app(Notation::class);

        $produits = Produit::where('stock', '>', 0)
            ->whereHas('boutique', fn ($q) => $q->where('statut', 'active'))
            ->orderBy('id')->get();

        if ($produits->isEmpty()) {
            $this->command?->warn('Aucun produit : lancez CatalogueSeeder d\'abord.');

            return;
        }

        $livrees = 0;
        foreach (self::CLIENTS as $rang => [$nom, $email, $region, $quartier, $note, $titre, $mot]) {
            $client = User::firstOrCreate(
                ['email' => $email],
                ['name' => $nom, 'password' => 'password', 'role' => 'client',
                 'telephone' => '+221 76 000 00 0' . $rang]
            );

            $adresse = Adresse::firstOrCreate(
                ['utilisateur_id' => $client->id],
                ['destinataire' => $nom, 'telephone' => $client->telephone,
                 'region' => $region, 'ville' => $region, 'quartier' => $quartier,
                 'par_defaut' => true]
            );

            // Deux produits par commande, pris à des rangs différents pour que
            // les ventes se répartissent sur plusieurs boutiques.
            $panier->vider();
            foreach ([$rang * 3, $rang * 3 + 1] as $i) {
                $panier->ajouter($produits[$i % $produits->count()], 1 + $rang % 3);
            }

            $commande = $passe->creer($client, $adresse);

            $passe->expedier($commande->fresh());
            $passe->mettreEnLivraison($commande->fresh());
            $passe->livrer($commande->fresh());

            foreach ($commande->fresh('lignes')->lignes as $ligne) {
                $notation->noter($commande->fresh('lignes'), $ligne->produit, $note, $titre, $mot);
            }

            $livrees++;
        }

        $panier->vider();

        $this->command?->info(sprintf(
            '%d clients, %d commandes livrées et notées.', count(self::CLIENTS), $livrees
        ));
    }
}
