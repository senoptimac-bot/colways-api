<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 11 — Fix colonne condition
 *
 * Problème : condition était défini comme enum('neuf','tres_bon_etat','bon_etat')
 * → SQLite génère un CHECK constraint qui rejette les nouvelles valeurs
 *   arrivage_neuf et premier_choix.
 *
 * Solution : recréer la colonne en string(30) via un échange de colonnes
 * compatible SQLite (pas de ALTER COLUMN → copie + rename).
 */
return new class extends Migration
{
    public function up(): void
    {
        // On sécurise au cas où la migration a planté au milieu la première fois
        if (!Schema::hasColumn('articles', 'condition_new')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->string('condition_new', 30)->nullable()->after('condition');
            });
        }

        // Copier toutes les valeurs existantes (Backticks obligatoires sur MariaDB/MySQL car 'condition' est un mot réservé)
        DB::statement('UPDATE articles SET condition_new = `condition`');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('condition');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->renameColumn('condition_new', 'condition');
        });

        // S'assurer que la colonne n'a pas de valeur nulle résiduelle
        DB::statement("UPDATE articles SET `condition` = 'bon_etat' WHERE `condition` IS NULL");
    }

    public function down(): void
    {
        // En cas de rollback : repasse en string (on ne remet pas l'enum
        // pour éviter de bloquer les données qui auraient les nouvelles valeurs)
        Schema::table('articles', function (Blueprint $table) {
            $table->string('condition', 30)->nullable()->change();
        });
    }
};
