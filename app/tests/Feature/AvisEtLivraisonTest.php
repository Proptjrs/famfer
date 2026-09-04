<?php

namespace Tests\Feature;

use App\Models\Adresse;
use App\Models\Avis;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use App\Services\Livraison;
use App\Services\Notation;
use App\Services\Panier;
use App\Services\PasseCommande;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Les frais de livraison, et les avis.
 *
 * Deux mécaniques sans rapport, sauf qu'elles partagent la même exigence : le
 * chiffre affiché doit être celui qui s'applique. Un barème que le client ne
 * peut pas deviner, ou une note que le vendeur peut écrire lui-même, ne valent
 * rien ni l'un ni l'autre.
 */
class AvisEtLivraisonTest extends TestCase
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

    // ── La livraison ─────────────────────────────────────────────────────────

    public function test_le_forfait_depend_de_la_region(): void
    {
        $l = app(Livraison::class);

        $this->assertSame(1_500, $l->frais('Dakar', 10_000));
        $this->assertSame(5_000, $l->frais('Ziguinchor', 10_000));
        // Une région hors barème n'est pas gratuite pour autant.
        $this->assertSame(Livraison::FORFAIT_AUTRE, $l->frais('Région inventée', 10_000));
    }

    public function test_la_livraison_est_offerte_au_dessus_du_seuil(): void
    {
        $l = app(Livraison::class);

        $this->assertSame(0, $l->frais('Ziguinchor', Livraison::SEUIL_GRATUIT));
        $this->assertGreaterThan(0, $l->frais('Ziguinchor', Livraison::SEUIL_GRATUIT - 1));

        $this->assertNull($l->resteAvantGratuite(Livraison::SEUIL_GRATUIT));
        $this->assertSame(1, $l->resteAvantGratuite(Livraison::SEUIL_GRATUIT - 1));
    }

    /** Le montant annoncé au panier est celui que la commande retient. */
    public function test_les_frais_de_la_commande_sont_ceux_du_bareme(): void
    {
        $commande = $this->commander(1);

        $this->assertSame(
            app(Livraison::class)->frais('Dakar', $commande->sous_total),
            $commande->frais_livraison
        );
        $this->assertSame(
            $commande->sous_total + $commande->frais_livraison,
            $commande->total
        );
    }

    // ── Les avis ─────────────────────────────────────────────────────────────

    /** Sans livraison, une note ne vaut rien : c'est ce qui protège le classement. */
    public function test_on_ne_note_pas_une_commande_non_livree(): void
    {
        $commande = $this->commander();

        $this->expectExceptionMessageMatches('/commande livrée/');
        app(Notation::class)->noter($commande, $this->produit, 5);
    }

    public function test_noter_recalcule_le_produit_et_la_boutique(): void
    {
        $this->assertNull($this->produit->note_sur_cent);

        app(Notation::class)->noter($this->livree(), $this->produit, 4);
        $this->assertSame(80, $this->produit->fresh()->note_sur_cent);
        $this->assertSame(80, $this->produit->boutique->fresh()->note_sur_cent);

        app(Notation::class)->noter($this->livree(), $this->produit, 5);

        // Moyenne de 4 et 5 : 4,5 sur cinq, donc 90 sur cent.
        $this->assertSame(90, $this->produit->fresh()->note_sur_cent);
        $this->assertSame(2, $this->produit->fresh()->nombre_avis);
        $this->assertSame(4.5, $this->produit->fresh()->noteSurCinq());
    }

    public function test_un_produit_ne_se_note_quune_fois_par_commande(): void
    {
        $commande = $this->livree();
        app(Notation::class)->noter($commande, $this->produit, 5);

        try {
            app(Notation::class)->noter($commande->fresh('lignes'), $this->produit, 1);
            $this->fail('Une seconde note sur la même commande aurait dû être refusée.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('déjà noté', $e->getMessage());
        }

        $this->assertSame(1, Avis::count());
        $this->assertSame(100, $this->produit->fresh()->note_sur_cent);
    }

    public function test_la_note_reste_dans_le_bareme(): void
    {
        foreach ([0, 6, -1] as $horsBareme) {
            try {
                app(Notation::class)->noter($this->livree(), $this->produit, $horsBareme);
                $this->fail("La note {$horsBareme} aurait dû être refusée.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('1 à 5', $e->getMessage());
            }
        }
    }

    /** On ne note pas un produit qui n'était pas dans la commande. */
    public function test_on_ne_note_pas_un_produit_absent_de_la_commande(): void
    {
        $autre = Produit::where('id', '!=', $this->produit->id)->firstOrFail();

        $this->expectExceptionMessageMatches('/ne figure pas/');
        app(Notation::class)->noter($this->livree(), $autre, 5);
    }

    /** Le parcours web complet, du bouton à l'étoile affichée. */
    public function test_le_client_note_depuis_sa_commande(): void
    {
        $commande = $this->livree();

        $this->actingAs($this->client)
            ->post(route('commande.noter', $commande), [
                'produit_id' => $this->produit->id,
                'note' => 5,
                'titre' => 'Conforme',
                'commentaire' => 'Livré le lendemain, rien à redire.',
            ])->assertRedirect();

        $this->assertSame(100, $this->produit->fresh()->note_sur_cent);

        $this->get(route('produit', $this->produit))->assertOk()
            ->assertSee('Livré le lendemain, rien à redire.');
    }

    public function test_on_ne_note_pas_la_commande_dun_autre(): void
    {
        $commande = $this->livree();

        $intrus = User::create([
            'name' => 'Intrus', 'email' => 'intrus@essai.sn',
            'password' => 'motdepasse', 'role' => 'client', 'telephone' => '+221 70 000 00 00',
        ]);

        $this->actingAs($intrus)
            ->post(route('commande.noter', $commande), [
                'produit_id' => $this->produit->id, 'note' => 1,
            ])->assertForbidden();

        $this->assertSame(0, Avis::count());
    }

    // ── Outils ───────────────────────────────────────────────────────────────

    private function commander(int $quantite = 1): Commande
    {
        app(Panier::class)->vider();
        app(Panier::class)->ajouter($this->produit->fresh(), $quantite);

        return app(PasseCommande::class)->creer($this->client, $this->adresse);
    }

    private function livree(): Commande
    {
        $c = $this->commander();
        $passe = app(PasseCommande::class);
        $passe->expedier($c);
        $passe->mettreEnLivraison($c->fresh());

        return $passe->livrer($c->fresh(), $c->fresh()->code_livraison)->load('lignes');
    }
}
