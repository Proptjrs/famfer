<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les litiges.
 *
 * Un litige gèle le reversement. C'est tout l'intérêt du séquestre : tant que
 * la plateforme détient l'argent, elle peut encore trancher. Une fois reversé,
 * il faudrait réclamer au vendeur — autant dire rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('litiges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->foreignId('ouvert_par')->constrained('users')->restrictOnDelete();

            $table->enum('motif', [
                'non_livre', 'quantite_manquante', 'article_non_conforme',
                'marchandise_abimee', 'autre',
            ]);
            $table->text('description');
            $table->json('pieces_jointes')->nullable();

            $table->enum('etat', ['ouvert', 'tranche_acheteur', 'tranche_vendeur'])->default('ouvert');
            $table->text('decision')->nullable();
            $table->foreignId('arbitre_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tranche_le')->nullable();

            $table->timestamps();

            // Une commande n'a qu'un litige ouvert à la fois : deux procédures
            // parallèles sur le même argent ne pourraient pas être tranchées.
            $table->index(['commande_id', 'etat']);
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->unique()->constrained('commandes')->cascadeOnDelete();
            $table->foreignId('vendeur_id')->constrained('vendeurs')->cascadeOnDelete();
            $table->unsignedTinyInteger('note');                 // 1 à 5
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->index('vendeur_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('litiges');
    }
};
