<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le catalogue : boutiques, catégories, produits.
 *
 * Modèle d'une place de marché grand public. Chaque produit appartient à une
 * boutique et porte son propre prix : il n'y a plus de référentiel national
 * partagé, plus d'unité pivot, plus de comparaison au gramme. Un produit est
 * un produit, comme sur Jumia.
 *
 * Le prix se garde en francs entiers. C'est la seule chose que la version
 * précédente avait raison de faire et qu'il serait absurde de défaire : un prix
 * en virgule flottante finit toujours par afficher 14 999,999999.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Les boutiques ────────────────────────────────────────────────────
        Schema::create('boutiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('users')->cascadeOnDelete();
            $table->string('nom', 160);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('telephone', 20);
            $table->string('adresse', 200);
            $table->string('ville', 80);

            // « Boutique officielle » : la mise en avant que toute place de
            // marché réserve aux enseignes qu'elle a démarchées elle-même.
            $table->boolean('officielle')->default(false);
            $table->enum('statut', ['en_attente', 'active', 'suspendue'])->default('en_attente');
            $table->string('motif_suspension', 200)->nullable();

            // Note moyenne sur cinq, en centièmes pour rester entière.
            $table->unsignedSmallInteger('note_sur_cent')->nullable();
            $table->unsignedInteger('nombre_avis')->default(0);
            $table->timestamps();

            $table->index('statut');
        });

        // ── Les catégories ───────────────────────────────────────────────────
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parente_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('nom', 120);
            $table->string('slug', 140)->unique();
            $table->string('icone', 40)->nullable();
            $table->unsignedSmallInteger('rang')->default(0);
            $table->timestamps();

            $table->index(['parente_id', 'rang']);
        });

        // ── Les produits ─────────────────────────────────────────────────────
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained('boutiques')->cascadeOnDelete();
            $table->foreignId('categorie_id')->constrained('categories')->restrictOnDelete();

            $table->string('nom', 200);
            $table->string('slug', 220)->unique();
            $table->text('description')->nullable();
            $table->string('marque', 80)->nullable();

            // Le prix affiché, et le prix barré au-dessus. La remise n'est pas
            // stockée : elle se calcule des deux, ce qui interdit d'annoncer
            // « -40 % » sur des chiffres qui ne le disent pas.
            $table->unsignedInteger('prix');
            $table->unsignedInteger('prix_barre')->nullable();

            $table->unsignedInteger('stock')->default(0);
            $table->string('dessin', 40)->default('defaut');
            $table->boolean('actif')->default(true);

            $table->unsignedSmallInteger('note_sur_cent')->nullable();
            $table->unsignedInteger('nombre_avis')->default(0);
            $table->unsignedInteger('nombre_ventes')->default(0);
            $table->timestamps();

            $table->index(['categorie_id', 'actif']);
            $table->index(['boutique_id', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('boutiques');
    }
};
