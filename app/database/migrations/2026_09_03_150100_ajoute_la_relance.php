<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La trace des relances.
 *
 * Le temps est la troisième source de vérité, après le code de remise et la
 * parole du client. Elle ne coûte rien et n'exige aucun tiers : une commande
 * dont plus personne ne parle finit par dire quelque chose.
 *
 * Deux silences comptent, et ils ne veulent pas dire la même chose.
 *
 * Le silence du **vendeur** est suspect : un colis expédié il y a une semaine et
 * jamais clos signifie soit qu'il dort dans un magasin, soit qu'il a été remis
 * sans être déclaré. On demande alors au client, qui n'a aucune raison de mentir
 * dans ce sens.
 *
 * Le silence du **client** vaut acceptation : sans quoi un vendeur honnête
 * n'aurait jamais de certitude, et un refus pourrait lui être reproché des mois
 * plus tard. La fenêtre de contestation se ferme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            // Une seule relance par commande : au-delà, on harcèle.
            $table->timestamp('relance_le')->nullable()->after('etat_conteste');
            // La date à laquelle plus personne ne peut contester.
            $table->timestamp('close_le')->nullable()->after('relance_le');
        });
    }

    public function down(): void
    {
        Schema::table('commandes', fn (Blueprint $t) => $t->dropColumn(['relance_le', 'close_le']));
    }
};
