<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le grand livre en partie double.
 *
 * Aucune colonne « solde » n'existe nulle part : un solde se calcule, il ne se
 * range pas. Ce qui est rangé, ce sont des écritures — et elles ne bougent
 * jamais. Une erreur se corrige par une écriture inverse, qui laisse les deux
 * traces ; corriger en modifiant effacerait l'histoire, et l'histoire est
 * précisément ce qu'on demandera de produire le jour d'un litige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecritures', function (Blueprint $table) {
            $table->id();

            // Toutes les lignes d'un même mouvement partagent cet identifiant.
            // C'est sur lui que se vérifie l'équilibre : débits = crédits.
            $table->uuid('operation_id')->index();
            $table->string('operation', 40);        // encaissement, reversement…

            $table->string('compte', 40);           // sequestre, vendeur:12, commission…
            $table->enum('sens', ['debit', 'credit']);

            // Des francs CFA entiers. Un centime n'existe pas ici, et un flottant
            // finirait par faire mentir la balance.
            $table->unsignedBigInteger('montant');

            $table->foreignId('commande_id')->nullable()->constrained('commandes')->nullOnDelete();
            $table->string('libelle', 200);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['compte', 'created_at']);
            $table->index('commande_id');
        });

        // Une écriture ne se modifie ni ne s'efface. La règle est posée dans la
        // base et non dans le code : une commande SQL lancée à la main depuis un
        // client d'administration doit se heurter au même mur.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION ecritures_immuables() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Une écriture ne se modifie ni ne se supprime : passez par une écriture inverse.';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER ecritures_sans_modification
            BEFORE UPDATE OR DELETE ON ecritures
            FOR EACH ROW EXECUTE FUNCTION ecritures_immuables();
        SQL);

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')->constrained('commandes')->cascadeOnDelete();
            $table->string('operateur', 20);                    // wave, orange_money…

            // La garde contre le rappel envoyé deux fois. C'est la contrainte
            // d'unicité qui protège, pas un « if » : entre la vérification et
            // l'écriture, un second rappel peut passer.
            $table->string('cle_idempotence', 120)->unique();

            $table->string('reference_externe', 120)->nullable();
            $table->unsignedBigInteger('montant');
            $table->unsignedBigInteger('frais_operateur')->default(0);
            $table->enum('etat', ['en_attente', 'confirme', 'echoue', 'rembourse'])->default('en_attente');
            $table->json('charge_utile')->nullable();           // le rappel brut, pour l'enquête
            $table->timestamps();

            $table->index(['commande_id', 'etat']);
        });

        Schema::create('reversements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendeur_id')->constrained('vendeurs')->restrictOnDelete();
            $table->unsignedBigInteger('montant');
            $table->enum('etat', ['prepare', 'envoye', 'echoue'])->default('prepare');
            $table->string('reference_virement', 120)->nullable();
            $table->timestamp('envoye_le')->nullable();
            $table->timestamps();

            $table->index(['vendeur_id', 'etat']);
        });
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS ecritures_sans_modification ON ecritures');
        DB::statement('DROP FUNCTION IF EXISTS ecritures_immuables()');
        Schema::dropIfExists('reversements');
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('ecritures');
    }
};
