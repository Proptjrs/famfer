<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'image d'illustration d'une catégorie.
 *
 * Elle sert de repli pour les produits que personne n'a encore photographiés :
 * mieux vaut une photo de la famille — des barres d'acier pour un fer à béton,
 * une clé plate pour un jeu de clés — qu'un dessin au trait ou un cadre vide.
 *
 * L'ordre d'affichage devient : photo du produit, puis image de sa catégorie,
 * puis dessin. Chaque échelon est plus général que le précédent, et le dernier
 * ne manque jamais.
 *
 * Les colonnes d'attribution ne sont pas décoratives : ces images viennent de
 * Wikimedia Commons sous licence CC BY, CC BY-SA ou domaine public, et citer
 * l'auteur est une obligation de ces licences.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image', 255)->nullable()->after('icone');
            $table->string('image_auteur', 160)->nullable()->after('image');
            $table->string('image_licence', 60)->nullable()->after('image_auteur');
            $table->string('image_source', 400)->nullable()->after('image_licence');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['image', 'image_auteur', 'image_licence', 'image_source']);
        });
    }
};
