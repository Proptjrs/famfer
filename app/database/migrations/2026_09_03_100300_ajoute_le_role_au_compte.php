<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le rôle porté par un compte.
 *
 * Trois valeurs, et une seule colonne : sur une place de marché grand public,
 * un compte est client, vendeur ou administration. Le vendeur reste client —
 * il achète aussi — mais c'est son rôle qui décide de ce qu'il voit en entrant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['client', 'vendeur', 'admin'])->default('client')->after('email');
            $table->string('telephone', 20)->nullable()->after('role');
            $table->timestamp('bloque_le')->nullable()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'telephone', 'bloque_le']);
        });
    }
};
