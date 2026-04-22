<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration NON-DESTRUCTIVE — Sprint 8 B2B Grossiste
 * Ajoute les champs palette sur la table articles.
 *
 * Impact sur les données existantes : ZÉRO
 * Tous les articles B2C actuels → champs palette = null (nullable)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Poids total de la palette en kg
            $table->integer('poids_kg')->nullable()->after('price');

            // Quantité estimée de pièces dans la palette
            $table->integer('quantite_estimee')->nullable()->after('poids_kg');

            // Pays d'origine de la marchandise
            $table->string('origine_pays', 50)->nullable()->after('quantite_estimee');
            // Valeurs possibles : france, belgique, uk, usa, dubai, chine, maroc, autre
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['poids_kg', 'quantite_estimee', 'origine_pays']);
        });
    }
};
