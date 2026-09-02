<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le drapeau d'administration.
 *
 * Les rôles « acheteur » et « vendeur » se déduisent des tables du même nom :
 * on est vendeur parce qu'on a une fiche vendeur. L'administration n'a pas de
 * table propre, il lui faut donc une colonne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('est_admin')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('est_admin'));
    }
};
