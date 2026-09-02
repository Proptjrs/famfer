<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les vendeurs et leurs offres.
 *
 * Le référentiel dit ce qu'est un T10 ; l'offre dit à quel prix ce vendeur-ci le
 * propose, combien il en a, et où il se trouve. C'est cette séparation qui rend
 * la comparaison possible : trois quincailleries, un même article, trois prix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendeurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('users')->cascadeOnDelete();
            $table->string('raison_sociale', 160);
            $table->string('ninea', 20)->nullable()->unique();
            $table->string('telephone', 20);
            $table->string('adresse', 200);
            $table->string('commune', 80);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            // Un vendeur n'est visible des acheteurs qu'une fois vérifié :
            // pièce d'identité, NINEA, justificatif d'activité. Laisser un
            // inconnu encaisser par notre intermédiaire nous rendrait complices.
            $table->enum('statut', ['en_attente', 'verifie', 'suspendu'])->default('en_attente');
            $table->timestamp('verifie_le')->nullable();
            $table->foreignId('verifie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif_suspension', 200)->nullable();

            // Le taux est porté par le vendeur, non codé en dur : une enseigne
            // qui apporte du volume peut négocier. Il est figé sur chaque
            // commande au moment où elle est passée.
            $table->unsignedSmallInteger('taux_commission_pour_mille')->default(80);   // 8,0 %

            $table->unsignedInteger('nombre_evaluations')->default(0);
            $table->unsignedSmallInteger('note_sur_cent')->nullable();
            $table->timestamps();

            $table->index(['statut', 'commune']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('offres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendeur_id')->constrained('vendeurs')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();

            // Le prix est saisi dans l'unité que le vendeur emploie au comptoir —
            // « 4 200 F la barre » — et non dans l'unité pivot, qui ne parle à
            // personne. La comparaison se fait ensuite au pivot.
            $table->unsignedBigInteger('prix_par_unite');          // francs CFA, entier
            $table->string('unite_affichee', 20);

            $table->unsignedBigInteger('quantite_pivot')->default(0);
            $table->unsignedBigInteger('quantite_reservee_pivot')->default(0);

            $table->unsignedSmallInteger('delai_preparation_h')->default(2);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            // Un vendeur ne propose qu'une offre par article : deux prix pour le
            // même T10 chez le même marchand n'aurait aucun sens.
            $table->unique(['vendeur_id', 'article_id']);
            $table->index(['article_id', 'actif']);
        });

        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offre_id')->constrained('offres')->cascadeOnDelete();

            $table->enum('type', [
                'approvisionnement', 'reservation', 'liberation',
                'sortie_vente', 'retour', 'regularisation_inventaire',
            ]);

            // Signée : positive pour ce qui entre, négative pour ce qui sort. Le
            // stock est la somme de ces lignes, jamais un compteur que l'on
            // modifie — c'est ce qui permet de répondre à « où en étais-je le
            // 12 mars ? ».
            $table->bigInteger('quantite_pivot');

            $table->string('motif', 200)->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['offre_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
        Schema::dropIfExists('offres');
        Schema::dropIfExists('vendeurs');
    }
};
