<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La commission de la plateforme.
 *
 * Elle manquait entièrement : FamFer ne gagnait rien. Une place de marché sans
 * revenu n'est pas une place de marché, c'est un annuaire.
 *
 * Le paiement à la livraison change le sens du flux par rapport à un séquestre.
 * Avec séquestre, la plateforme encaisse et reverse au vendeur le montant
 * diminué de sa commission. Ici, c'est le **vendeur** qui encaisse les espèces
 * du client — la plateforme ne voit jamais l'argent. Elle lui facture donc la
 * commission après coup : ce n'est plus une retenue, c'est une créance.
 *
 * Trois conséquences portées par ce schéma :
 *
 * - le taux est **par boutique** et se négocie ; une enseigne qui apporte du
 *   volume n'a aucune raison de payer le même taux qu'un nouveau venu ;
 * - il est **figé sur chaque commande** à sa création : un taux renégocié
 *   demain ne réécrit pas ce qui a été vendu hier ;
 * - la commission n'est **due qu'à la livraison** — une commande refusée à la
 *   porte, annulée ou retournée ne coûte rien au vendeur, qui a déjà perdu la
 *   tournée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boutiques', function (Blueprint $table) {
            // En pour mille : 80 = 8 %. L'entier évite les arrondis flottants
            // sur une valeur qui multiplie tous les montants de la plateforme.
            $table->unsignedSmallInteger('taux_commission_pour_mille')
                ->default(80)->after('officielle');
        });

        Schema::table('commandes', function (Blueprint $table) {
            $table->unsignedSmallInteger('taux_commission_pour_mille')
                ->default(80)->after('total');
            $table->unsignedInteger('commission')->default(0)->after('taux_commission_pour_mille');
        });

        Schema::table('lignes_commande', function (Blueprint $table) {
            // La part de commission portée par chaque ligne : c'est ce qui
            // permet de dire à une boutique ce qu'elle doit sur une commande
            // partagée avec d'autres.
            $table->unsignedInteger('commission')->default(0)->after('montant');
        });
    }

    public function down(): void
    {
        Schema::table('lignes_commande', fn (Blueprint $t) => $t->dropColumn('commission'));
        Schema::table('commandes', fn (Blueprint $t) => $t->dropColumn(
            ['taux_commission_pour_mille', 'commission']
        ));
        Schema::table('boutiques', fn (Blueprint $t) => $t->dropColumn('taux_commission_pour_mille'));
    }
};
