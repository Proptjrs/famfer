<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La preuve de la livraison.
 *
 * Jusqu'ici, « livrée » et « refusée » étaient toutes deux déclarées par le
 * vendeur, et par lui seul. Il était donc l'unique témoin d'un fait sur lequel
 * il avait intérêt à mentir : livrer, encaisser les espèces, puis déclarer
 * « refusée ». Il gardait l'argent, le stock lui revenait, et la règle « un
 * refus ne coûte rien » lui offrait la commission par-dessus le marché.
 *
 * Le paiement à la livraison n'a pas de tiers de confiance — c'est ce qui le
 * rend accessible, et c'est ce qui le rend fragile. À défaut de séquestre, on
 * fait témoigner les deux parties :
 *
 * - un **code de remise** est tiré à l'expédition et communiqué au client seul.
 *   Le vendeur ne peut clore la commande sans le code, que le client ne donne
 *   qu'en recevant le colis. Le code prouve la remise ;
 * - le **client peut confirmer** de son côté. S'il déclare avoir reçu, la vente
 *   est acquise, quoi que dise le vendeur ;
 * - **la divergence ouvre un litige** que l'administration tranche. C'est le
 *   seul cas où un tiers décide, et il faut qu'il reste rare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            // Six chiffres tirés au sort. Ce n'est pas un secret durable : c'est
            // un jeton de remise à usage unique, que le client lit sur son
            // écran et dicte au livreur.
            $table->char('code_livraison', 6)->nullable()->after('motif');
            $table->timestamp('code_remis_le')->nullable()->after('code_livraison');

            // La déclaration du client, distincte de celle du vendeur.
            $table->timestamp('confirmee_le')->nullable()->after('code_remis_le');

            $table->string('litige_par', 10)->nullable()->after('confirmee_le');
            $table->string('litige_motif', 300)->nullable()->after('litige_par');
            $table->timestamp('litige_le')->nullable()->after('litige_motif');
            $table->string('etat_conteste', 20)->nullable()->after('litige_le');
        });

        // « etat » est un enum : PostgreSQL l'a traduit en contrainte CHECK, qui
        // ignore le nouvel état. On la réécrit plutôt que de recréer la colonne,
        // ce qui perdrait les commandes existantes.
        $etats = ['en_preparation', 'expediee', 'en_livraison', 'livree',
                  'refusee', 'annulee', 'retournee', 'litige'];

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE commandes DROP CONSTRAINT IF EXISTS commandes_etat_check');
            DB::statement(sprintf(
                "ALTER TABLE commandes ADD CONSTRAINT commandes_etat_check CHECK (etat IN ('%s'))",
                implode("', '", $etats)
            ));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE commandes DROP CONSTRAINT IF EXISTS commandes_etat_check');
            DB::statement(
                "ALTER TABLE commandes ADD CONSTRAINT commandes_etat_check CHECK (etat IN ("
                . "'en_preparation', 'expediee', 'en_livraison', 'livree', "
                . "'refusee', 'annulee', 'retournee'))"
            );
        }

        Schema::table('commandes', fn (Blueprint $t) => $t->dropColumn([
            'code_livraison', 'code_remis_le', 'confirmee_le',
            'litige_par', 'litige_motif', 'litige_le', 'etat_conteste',
        ]));
    }
};
