<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les commandes.
 *
 * Une commande ne concerne qu'un seul vendeur. Un panier réparti sur trois
 * quincailleries produit trois commandes : trois livraisons, trois séquestres,
 * trois reversements. C'est plus simple à écrire, et surtout plus juste — le
 * vendeur qui a livré est payé, même si un autre a fait défaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acheteurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('users')->cascadeOnDelete();
            $table->enum('genre', ['particulier', 'chantier', 'entreprise'])->default('particulier');
            $table->string('telephone', 20);
            $table->string('adresse_defaut', 200)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();          // FF-2026-000418
            $table->foreignId('acheteur_id')->constrained('acheteurs')->restrictOnDelete();
            $table->foreignId('vendeur_id')->constrained('vendeurs')->restrictOnDelete();

            $table->string('etat', 24)->default('en_attente_paiement');
            $table->enum('mode_remise', ['retrait', 'livraison'])->default('retrait');
            $table->string('adresse_livraison', 200)->nullable();

            // Les montants sont figés à la commande : si le vendeur change son
            // tarif le lendemain, la commande passée n'en dépend pas.
            $table->unsignedBigInteger('montant_articles');
            $table->unsignedBigInteger('frais_livraison')->default(0);
            $table->unsignedBigInteger('montant_total');

            // Le taux est recopié depuis le vendeur au moment de la commande.
            // Renégocier la commission ne doit pas changer le passé.
            $table->unsignedSmallInteger('taux_commission_pour_mille');
            $table->unsignedBigInteger('montant_commission');

            // Chaque transition laisse son horodatage : c'est ce qui permet de
            // mesurer les délais réels et de trancher un litige.
            $table->timestamp('payee_le')->nullable();
            $table->timestamp('acceptee_le')->nullable();
            $table->timestamp('prete_le')->nullable();
            $table->timestamp('livree_le')->nullable();
            $table->timestamp('receptionnee_le')->nullable();
            $table->timestamp('soldee_le')->nullable();
            $table->timestamp('annulee_le')->nullable();
            $table->string('motif_annulation', 200)->nullable();

            // Les échéances des trois délais. Une tâche planifiée les balaie ;
            // elles ne dépendent pas d'une visite sur le site.
            $table->timestamp('expire_le')->nullable();             // 15 min pour payer
            $table->timestamp('acceptation_due_le')->nullable();    // 2 h pour accepter
            $table->timestamp('reception_due_le')->nullable();      // 72 h pour confirmer

            $table->timestamps();

            $table->index(['vendeur_id', 'etat']);
            $table->index(['acheteur_id', 'etat']);
            $table->index('etat');
        });

        Schema::create('lignes_commande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->foreignId('offre_id')->constrained('offres')->restrictOnDelete();

            // On conserve la quantité pivot — qui fait foi — et l'expression que
            // l'acheteur a vue : « 20 barres ». Recalculer l'affichage plus tard
            // donnerait un libellé différent si l'unité de l'offre a changé.
            $table->unsignedBigInteger('quantite_pivot');
            $table->string('unite_affichee', 20);
            $table->string('quantite_affichee', 20);

            $table->unsignedBigInteger('prix_unitaire_fige');
            $table->unsignedBigInteger('montant');
            $table->timestamps();
        });

        Schema::create('transitions_commande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->string('etat_depart', 24);
            $table->string('etat_arrivee', 24);
            $table->string('motif', 200)->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['commande_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transitions_commande');
        Schema::dropIfExists('lignes_commande');
        Schema::dropIfExists('commandes');
        Schema::dropIfExists('acheteurs');
    }
};
