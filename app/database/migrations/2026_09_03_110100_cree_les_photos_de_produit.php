<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les photos des produits.
 *
 * Jusqu'ici un produit ne portait qu'une clé de dessin — un tracé vectoriel
 * choisi dans une liste. C'est net et léger, mais ce n'est pas la marchandise :
 * un client qui achète une tôle veut voir la tôle, pas son schéma.
 *
 * Une table plutôt qu'une colonne, parce qu'un produit se photographie sous
 * plusieurs angles, et que la première photo n'est pas forcément celle qu'on
 * garde. Le rang décide de l'ordre, et la première du rang sert de vignette.
 *
 * Le fichier n'est pas stocké en base : seul son chemin l'est. Mettre des
 * octets d'image dans PostgreSQL grossit les sauvegardes, ralentit chaque
 * requête qui touche la table, et empêche le serveur web de servir le fichier
 * directement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos_produit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();

            // Le chemin relatif au disque public, par exemple
            // « produits/17/a3f9c2.webp ».
            $table->string('chemin', 255);
            $table->string('description', 160)->nullable();
            $table->unsignedSmallInteger('rang')->default(0);
            $table->timestamps();

            $table->index(['produit_id', 'rang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos_produit');
    }
};
