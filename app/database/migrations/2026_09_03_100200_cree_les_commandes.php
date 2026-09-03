<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le carnet d'adresses, les commandes et les avis.
 *
 * Le paiement se fait à la livraison : le client règle au livreur, en espèces.
 * C'est le mode dominant au Sénégal, et il change tout par rapport à un
 * séquestre — la plateforme ne détient jamais l'argent du client, donc il n'y a
 * ni compte de séquestre, ni écritures comptables, ni gel de reversement.
 *
 * La contrepartie est un risque réel : le colis part avant d'être payé, et un
 * client peut refuser à la livraison. C'est pourquoi la commande porte un état
 * « refusee » distinct de « annulee », et pourquoi le nombre de refus d'un
 * client mérite d'être suivi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Le carnet d'adresses ─────────────────────────────────────────────
        Schema::create('adresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('users')->cascadeOnDelete();
            $table->string('destinataire', 160);
            $table->string('telephone', 20);
            $table->string('region', 80);
            $table->string('ville', 80);
            $table->string('quartier', 120);
            $table->string('repere', 200)->nullable();
            $table->boolean('par_defaut')->default(false);
            $table->timestamps();

            $table->index(['utilisateur_id', 'par_defaut']);
        });

        // ── Les commandes ────────────────────────────────────────────────────
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('utilisateur_id')->constrained('users')->restrictOnDelete();

            // L'adresse est recopiée, pas référencée : un client qui corrige son
            // carnet ne doit pas réécrire l'histoire d'une commande déjà livrée.
            $table->string('destinataire', 160);
            $table->string('telephone', 20);
            $table->string('adresse_livraison', 400);

            $table->enum('etat', [
                'en_preparation', 'expediee', 'en_livraison',
                'livree', 'refusee', 'annulee', 'retournee',
            ])->default('en_preparation');

            $table->enum('paiement', ['livraison', 'wave', 'om'])->default('livraison');
            $table->boolean('paye')->default(false);

            $table->unsignedInteger('sous_total');
            $table->unsignedInteger('frais_livraison');
            $table->unsignedInteger('total');

            $table->timestamp('expediee_le')->nullable();
            $table->timestamp('livree_le')->nullable();
            $table->timestamp('cloturee_le')->nullable();
            $table->string('motif', 200)->nullable();
            $table->timestamps();

            $table->index(['utilisateur_id', 'etat']);
            $table->index('etat');
        });

        Schema::create('lignes_commande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->restrictOnDelete();
            $table->foreignId('boutique_id')->constrained('boutiques')->restrictOnDelete();

            // Le nom et le prix sont figés à la commande. Un vendeur qui change
            // son tarif le lendemain ne change pas ce que le client a accepté.
            $table->string('nom_produit', 200);
            $table->unsignedInteger('prix_unitaire');
            $table->unsignedInteger('quantite');
            $table->unsignedInteger('montant');
            $table->timestamps();

            $table->index('boutique_id');
        });

        // ── Les avis ─────────────────────────────────────────────────────────
        Schema::create('avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->foreignId('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->unsignedTinyInteger('note');
            $table->string('titre', 160)->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            // Un produit ne se note qu'une fois par commande : sans cette
            // contrainte, un même achat gonflerait la note autant de fois qu'on
            // rechargerait le formulaire.
            $table->unique(['commande_id', 'produit_id']);
            $table->index('produit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis');
        Schema::dropIfExists('lignes_commande');
        Schema::dropIfExists('commandes');
        Schema::dropIfExists('adresses');
    }
};
