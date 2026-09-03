<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Où envoyer l'argent d'un vendeur.
 *
 * La page « Mon argent » annonçait depuis le début que le virement partait
 * « vers le compte Wave ou Orange Money enregistré à votre nom ». Aucun champ
 * ne portait ce compte : la phrase promettait ce que la base ne pouvait pas
 * tenir, et un reversement préparé n'avait aucune destination.
 *
 * Trois colonnes plutôt qu'une chaîne libre. L'opérateur, parce que le numéro
 * seul ne dit pas chez qui virer. Le titulaire, parce qu'il arrive qu'il diffère
 * de la raison sociale — un gérant qui encaisse sur son propre compte — et
 * qu'une banque refuse un virement dont le nom ne correspond pas. Et la date de
 * dernière modification, parce qu'un changement de compte de versement est
 * exactement ce qu'un intrus ferait après avoir pris un compte : elle donne de
 * quoi le repérer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendeurs', function (Blueprint $table) {
            $table->enum('versement_operateur', ['wave', 'om'])->nullable()->after('taux_commission_pour_mille');
            $table->string('versement_numero', 20)->nullable()->after('versement_operateur');
            $table->string('versement_titulaire', 160)->nullable()->after('versement_numero');
            $table->timestamp('versement_modifie_le')->nullable()->after('versement_titulaire');
        });
    }

    public function down(): void
    {
        Schema::table('vendeurs', function (Blueprint $table) {
            $table->dropColumn([
                'versement_operateur', 'versement_numero',
                'versement_titulaire', 'versement_modifie_le',
            ]);
        });
    }
};
