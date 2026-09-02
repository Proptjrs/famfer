<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le référentiel national des articles.
 *
 * C'est le bien commun de la place de marché : un T10 est un T10, quel que soit
 * le vendeur qui le propose. Sans ce socle partagé, chacun saisirait « fer 10 »,
 * « T10 » ou « FA T10 12m », et l'acheteur ne pourrait comparer aucun prix.
 * Les vendeurs ne le modifient pas ; ils s'y rattachent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('familles', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 80)->unique();
            $table->string('code', 20)->unique();
            $table->foreignId('parente_id')->nullable()->constrained('familles')->nullOnDelete();
            $table->unsignedSmallInteger('rang')->default(0);   // ordre d'affichage
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('famille_id')->constrained('familles')->restrictOnDelete();
            $table->string('designation', 160);
            $table->string('reference', 40)->unique();          // T10-12M, TOLE-BAC-6M…

            // Les mots que les acheteurs emploient vraiment : « fer 10 », « fer à
            // béton 10 ». La recherche s'appuie dessus, pas seulement sur la
            // désignation officielle.
            $table->text('synonymes')->nullable();

            // Unité dans laquelle TOUTES les quantités sont stockées, en entier.
            // Le gramme pour l'acier, le millimètre pour les longueurs vendues au
            // mètre : jamais de flottant sur des tonnes de fer.
            $table->string('unite_pivot', 12);

            $table->json('caracteristiques')->nullable();       // diamètre, longueur, masse linéique
            $table->string('photo')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['famille_id', 'actif']);
        });

        Schema::create('unites_vente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('unite', 20);                        // barre, kilogramme, tonne, feuille…

            // Combien d'unités pivot vaut UNE unité de vente. Une barre de T10
            // pèse 7 404 g : le facteur est donc 7404, en entier lui aussi.
            $table->unsignedBigInteger('facteur_vers_pivot');

            $table->boolean('par_defaut')->default(false);      // l'unité proposée en premier
            $table->timestamps();

            $table->unique(['article_id', 'unite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unites_vente');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('familles');
    }
};
