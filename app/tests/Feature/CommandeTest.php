<?php

namespace Tests\Feature;

use App\Models\Adresse;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use App\Services\Panier;
use App\Services\PasseCommande;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Passer commande, et le stock qui va avec.
 *
 * C'est le seul endroit du projet où deux clients peuvent se disputer le même
 * article. Tout ce qui est éprouvé ici tourne autour de cela : le stock baisse
 * quand il faut, remonte quand la commande échoue, et ne descend jamais sous
 * zéro.
 */
class CommandeTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private Adresse $adresse;
    private Produit $produit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);

        $this->client = User::create([
            'name' => 'Awa BA', 'email' => 'awa@essai.sn',
            'password' => 'motdepasse', 'role' => 'client', 'telephone' => '+221 77 000 00 00',
        ]);

        $this->adresse = Adresse::create([
            'utilisateur_id' => $this->client->id, 'destinataire' => 'Awa BA',
            'telephone' => '+221 77 000 00 00', 'region' => 'Dakar',
            'ville' => 'Dakar', 'quartier' => 'Grand Yoff', 'par_defaut' => true,
        ]);

        $this->produit = Produit::where('stock', '>', 5)
            ->whereHas('boutique', fn ($q) => $q->where('statut', 'active'))
            ->orderBy('id')->firstOrFail();
    }

    private function commander(int $quantite = 2): Commande
    {
        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit->fresh(), $quantite);

        return app(PasseCommande::class)->creer($this->client, $this->adresse);
    }

    // ── Le stock ─────────────────────────────────────────────────────────────

    public function test_commander_baisse_le_stock_et_compte_la_vente(): void
    {
        $avant = $this->produit->stock;
        $ventesAvant = $this->produit->nombre_ventes;

        $commande = $this->commander(2);

        $apres = $this->produit->fresh();
        $this->assertSame($avant - 2, $apres->stock);
        $this->assertSame($ventesAvant + 2, $apres->nombre_ventes);
        $this->assertSame('en_preparation', $commande->etat);
    }

    /**
     * On ne commande pas plus que le stock.
     *
     * Le panier plafonne déjà, mais le stock a pu partir entre le moment où le
     * panier a été rempli et la validation : c'est là que le contrôle compte.
     */
    public function test_on_ne_commande_pas_plus_que_le_stock(): void
    {
        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit, 2);

        // Quelqu'un d'autre a vidé le rayon entre-temps.
        $this->produit->update(['stock' => 1]);

        try {
            app(PasseCommande::class)->creer($this->client, $this->adresse);
            $this->fail('Une commande au-delà du stock aurait dû être refusée.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('il n\'en reste que 1', $e->getMessage());
        }

        // Rien n'a bougé : ni le stock, ni la moindre commande.
        $this->assertSame(1, $this->produit->fresh()->stock);
        $this->assertSame(0, Commande::count());
    }

    public function test_annuler_rend_le_stock(): void
    {
        $avant = $this->produit->stock;
        $commande = $this->commander(3);

        $this->assertSame($avant - 3, $this->produit->fresh()->stock);

        app(PasseCommande::class)->annuler($commande, 'Annulée par le client');

        $this->assertSame($avant, $this->produit->fresh()->stock);
        $this->assertSame($this->produit->nombre_ventes, $this->produit->fresh()->nombre_ventes);
    }

    /** Un colis refusé à la porte rentre : la marchandise revient en vente. */
    public function test_un_refus_rend_le_stock(): void
    {
        $avant = $this->produit->stock;
        $commande = $this->commander(2);

        $passe = app(PasseCommande::class);
        $commande = $passe->expedier($commande);
        $commande = $passe->mettreEnLivraison($commande);
        $passe->refuser($commande, 'Le client n\'était pas là');

        $this->assertSame($avant, $this->produit->fresh()->stock);
        $this->assertSame('refusee', $commande->fresh()->etat);
    }

    // ── La machine à états ───────────────────────────────────────────────────

    public function test_le_parcours_complet(): void
    {
        $commande = $this->commander();
        $passe = app(PasseCommande::class);

        $this->assertSame('expediee', $passe->expedier($commande)->etat);
        $this->assertSame('en_livraison', $passe->mettreEnLivraison($commande->fresh())->etat);

        $livree = $passe->livrer($commande->fresh(), $commande->fresh()->code_livraison);
        $this->assertSame('livree', $livree->etat);
        // Payée à la livraison : les deux vont ensemble sur ce mode.
        $this->assertTrue($livree->paye);
        $this->assertNotNull($livree->livree_le);
    }

    public function test_on_ne_livre_pas_une_commande_non_expediee(): void
    {
        $commande = $this->commander();

        $this->expectExceptionMessageMatches('/ne peut pas passer/');
        app(PasseCommande::class)->livrer($commande, $commande->code_livraison);
    }

    public function test_une_commande_livree_ne_sannule_plus(): void
    {
        $commande = $this->commander();
        $passe = app(PasseCommande::class);
        $passe->expedier($commande);
        $passe->mettreEnLivraison($commande->fresh());
        $passe->livrer($commande->fresh(), $commande->fresh()->code_livraison);

        $this->expectException(RuntimeException::class);
        $passe->annuler($commande->fresh(), 'Trop tard');
    }

    // ── Ce qui est figé ──────────────────────────────────────────────────────

    /**
     * Le prix de demain ne réécrit pas la commande d'hier.
     *
     * La ligne recopie le nom et le prix : sans cela, un vendeur qui augmente
     * son tarif changerait après coup ce que le client a accepté.
     */
    public function test_le_prix_est_fige_a_la_commande(): void
    {
        $commande = $this->commander(1);
        $ligne = $commande->lignes->first();
        $prixInitial = $ligne->prix_unitaire;

        $this->produit->update(['prix' => $prixInitial * 3, 'nom' => 'Nom tout à fait différent']);

        $ligne->refresh();
        $this->assertSame($prixInitial, $ligne->prix_unitaire);
        $this->assertNotSame('Nom tout à fait différent', $ligne->nom_produit);
        $this->assertSame($prixInitial, $commande->fresh()->sous_total);
    }

    /** L'adresse est recopiée : corriger son carnet ne réécrit pas l'histoire. */
    public function test_ladresse_est_recopiee(): void
    {
        $commande = $this->commander();
        $ancienne = $commande->adresse_livraison;

        $this->adresse->update(['quartier' => 'Déménagé ailleurs']);

        $this->assertSame($ancienne, $commande->fresh()->adresse_livraison);
    }

    public function test_un_panier_vide_ne_donne_pas_de_commande(): void
    {
        app(Panier::class)->vider();

        $this->expectExceptionMessageMatches('/panier est vide/');
        app(PasseCommande::class)->creer($this->client, $this->adresse);
    }

    /** La référence est unique et lisible au téléphone. */
    public function test_les_references_ne_se_repetent_pas(): void
    {
        $a = $this->commander(1);
        $b = $this->commander(1);

        $this->assertNotSame($a->reference, $b->reference);
        $this->assertMatchesRegularExpression('/^FF-\d{4}-\d{6}$/', $a->reference);
    }

    /**
     * L'application n'accepte que le paiement qu'elle sait mener à terme.
     *
     * « wave » et « om » étaient acceptés par le formulaire de validation, sans
     * qu'aucun code ne les traite : la commande était livrée, « paye » restait
     * faux pour toujours — rien ne le remettait jamais à vrai — et la commission
     * devenait pourtant exigible. Le vendeur devait donc une commission sur un
     * argent qu'il n'avait peut-être jamais encaissé.
     *
     * Une promesse que le logiciel ne tient pas coûte plus cher qu'une option
     * absente. Cet essai empêche de la réintroduire par distraction.
     */
    public function test_seul_le_paiement_a_la_livraison_est_accepte(): void
    {
        $client = User::create([
            'name' => 'Awa BA', 'email' => 'awa-paiement@essai.sn',
            'password' => 'motdepasse', 'role' => 'client', 'telephone' => '+221 77 000 00 00',
        ]);
        $adresse = Adresse::create([
            'utilisateur_id' => $client->id, 'destinataire' => 'Awa BA',
            'telephone' => '+221 77 000 00 00', 'region' => 'Dakar',
            'ville' => 'Dakar', 'quartier' => 'Grand Yoff', 'par_defaut' => true,
        ]);

        foreach (['wave', 'om', 'carte'] as $refuse) {
            app(Panier::class)->vider();
            app(Panier::class)->ajouter($this->produit->fresh(), 1);

            $this->actingAs($client)->post(route('commande.valider'), [
                'adresse_id' => $adresse->id,
                'paiement' => $refuse,
            ])->assertSessionHasErrors('paiement');
        }

        $this->assertSame(0, Commande::where('utilisateur_id', $client->id)->count(),
            'Aucune commande ne doit naître d\'un mode de paiement non traité.');
    }
}
