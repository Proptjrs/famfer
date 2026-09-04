<?php

namespace Tests\Feature;

use App\Models\Adresse;
use App\Models\Commande;
use App\Models\Produit;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le parcours d'un client, de la vitrine à la livraison.
 *
 * Ce fichier passe par les pages plutôt que par les services : il vérifie que
 * les écrans sont bien reliés entre eux. Un service juste derrière un bouton
 * qui ne mène nulle part ne sert à rien.
 */
class ParcoursTest extends TestCase
{
    use RefreshDatabase;

    private Produit $produit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);

        $this->produit = Produit::where('stock', '>', 5)->where('actif', true)
            ->whereHas('boutique', fn ($q) => $q->where('statut', 'active'))
            ->orderBy('id')->firstOrFail();
    }

    private function client(): User
    {
        return User::create([
            'name' => 'Awa BA', 'email' => 'awa@essai.sn',
            'password' => 'motdepasse', 'role' => 'client', 'telephone' => '+221 77 000 00 00',
        ]);
    }

    // ── Le panier, sans compte ───────────────────────────────────────────────

    /**
     * On remplit son panier sans être inscrit.
     *
     * Exiger un compte avant d'avoir rempli son panier fait fuir la moitié des
     * visiteurs : l'identité n'est demandée qu'au moment de savoir où livrer.
     */
    public function test_on_remplit_son_panier_sans_compte(): void
    {
        $this->post(route('panier.ajouter', $this->produit), ['quantite' => 2])
            ->assertRedirect();

        $this->get(route('panier'))->assertOk()
            ->assertSee($this->produit->nom)
            ->assertSee('Commander');
    }

    public function test_le_panier_plafonne_au_stock(): void
    {
        $this->post(route('panier.ajouter', $this->produit), [
            'quantite' => $this->produit->stock + 50,
        ]);

        $contenu = $this->get(route('panier'))->assertOk()->viewData('contenu');
        $this->assertSame($this->produit->stock, $contenu->first()['quantite']);
    }

    public function test_un_produit_indisponible_ne_sajoute_pas(): void
    {
        $this->produit->update(['stock' => 0]);

        $this->post(route('panier.ajouter', $this->produit))->assertSessionHas('erreur');
        $this->assertSame(0, array_sum(session('panier', [])));
    }

    public function test_on_modifie_et_on_retire_du_panier(): void
    {
        $this->post(route('panier.ajouter', $this->produit), ['quantite' => 3]);

        $this->put(route('panier.modifier', $this->produit), ['quantite' => 1]);
        $this->assertSame(1, session('panier')[$this->produit->id]);

        $this->delete(route('panier.retirer', $this->produit));
        $this->assertSame([], session('panier', []));
    }

    // ── Commander ────────────────────────────────────────────────────────────

    public function test_commander_exige_un_compte(): void
    {
        $this->post(route('panier.ajouter', $this->produit));

        $this->get(route('commande'))->assertRedirect(route('connexion'));
    }

    public function test_un_panier_vide_renvoie_au_panier(): void
    {
        $this->actingAs($this->client())
            ->get(route('commande'))->assertRedirect(route('panier'));
    }

    /** Le parcours entier : vitrine, panier, adresse, commande, suivi. */
    public function test_de_la_vitrine_a_la_commande(): void
    {
        $client = $this->client();
        $stockAvant = $this->produit->stock;

        $this->get(route('produit', $this->produit))->assertOk();
        $this->post(route('panier.ajouter', $this->produit), ['quantite' => 2]);

        $this->actingAs($client)->get(route('commande'))->assertOk();

        $this->actingAs($client)->post(route('commande.valider'), [
            'destinataire' => 'Awa BA', 'telephone' => '+221 77 000 00 00',
            'region' => 'Dakar', 'ville' => 'Dakar', 'quartier' => 'Grand Yoff',
            'paiement' => 'livraison',
        ])->assertRedirect();

        $commande = Commande::where('utilisateur_id', $client->id)->firstOrFail();
        $this->assertSame('en_preparation', $commande->etat);
        $this->assertFalse($commande->paye);
        $this->assertSame($stockAvant - 2, $this->produit->fresh()->stock);

        // L'adresse saisie au passage est gardée pour la prochaine fois.
        $this->assertSame(1, Adresse::where('utilisateur_id', $client->id)->count());

        // Et le panier est vidé.
        $this->assertSame([], session('panier', []));

        $this->actingAs($client)->get(route('mes-commandes'))->assertOk()
            ->assertSee($commande->reference);
        $this->actingAs($client)->get(route('mes-commandes.detail', $commande))->assertOk()
            ->assertSee('En préparation');
    }

    public function test_le_client_annule_tant_que_rien_nest_parti(): void
    {
        $client = $this->client();
        $stockAvant = $this->produit->stock;
        $commande = $this->commander($client);

        $this->actingAs($client)
            ->post(route('commande.annuler', $commande))->assertRedirect();

        $this->assertSame('annulee', $commande->fresh()->etat);
        $this->assertSame($stockAvant, $this->produit->fresh()->stock);
    }

    public function test_le_client_nannule_plus_une_commande_expediee(): void
    {
        $client = $this->client();
        $commande = $this->commander($client);
        app(\App\Services\PasseCommande::class)->expedier($commande);

        $this->actingAs($client)
            ->post(route('commande.annuler', $commande->fresh()))
            ->assertSessionHas('erreur');

        $this->assertSame('expediee', $commande->fresh()->etat);
    }

    // ── Le vendeur traite la commande ────────────────────────────────────────

    public function test_le_vendeur_expedie_puis_livre(): void
    {
        $client = $this->client();
        $commande = $this->commander($client);
        $vendeur = $this->produit->boutique->utilisateur;

        $this->actingAs($vendeur)->get(route('vendeur.tableau'))->assertOk()
            ->assertSee($commande->reference);

        $this->actingAs($vendeur)->post(route('vendeur.expedier', $commande))->assertRedirect();
        $this->assertSame('expediee', $commande->fresh()->etat);

        // Le code de remise, que le client dicte au livreur en réglant. Sans
        // lui le vendeur déclarerait seul une livraison dont il profite.
        $code = $commande->fresh()->code_livraison;
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->actingAs($vendeur)
            ->post(route('vendeur.livrer', $commande->fresh()), ['code' => $code])
            ->assertRedirect();

        $livree = $commande->fresh();
        $this->assertSame('livree', $livree->etat);
        // Payée à la livraison : les deux vont ensemble.
        $this->assertTrue($livree->paye);
        $this->assertNotNull($livree->code_remis_le);
    }

    // ── Le carnet d'adresses ─────────────────────────────────────────────────

    public function test_la_premiere_adresse_devient_celle_par_defaut(): void
    {
        $client = $this->client();

        $this->actingAs($client)->post(route('adresses.ajouter'), [
            'destinataire' => 'Awa BA', 'telephone' => '+221 77 000 00 00',
            'region' => 'Thiès', 'ville' => 'Thiès', 'quartier' => 'Randoulène',
        ])->assertRedirect();

        $this->assertTrue($client->fresh()->adresses()->first()->par_defaut);
    }

    public function test_on_ne_supprime_pas_ladresse_dun_autre(): void
    {
        $client = $this->client();
        $adresse = Adresse::create([
            'utilisateur_id' => $client->id, 'destinataire' => 'Awa BA',
            'telephone' => '+221 77 000 00 00', 'region' => 'Dakar',
            'ville' => 'Dakar', 'quartier' => 'Grand Yoff',
        ]);

        $intrus = User::create([
            'name' => 'Intrus', 'email' => 'intrus@essai.sn',
            'password' => 'motdepasse', 'role' => 'client', 'telephone' => '+221 70 000 00 00',
        ]);

        $this->actingAs($intrus)
            ->delete(route('adresses.supprimer', $adresse))->assertForbidden();

        $this->assertNotNull($adresse->fresh());
    }

    private function commander(User $client): Commande
    {
        $this->post(route('panier.ajouter', $this->produit), ['quantite' => 2]);

        $this->actingAs($client)->post(route('commande.valider'), [
            'destinataire' => 'Awa BA', 'telephone' => '+221 77 000 00 00',
            'region' => 'Dakar', 'ville' => 'Dakar', 'quartier' => 'Grand Yoff',
            'paiement' => 'livraison',
        ]);

        return Commande::where('utilisateur_id', $client->id)->latest('id')->firstOrFail();
    }
}
