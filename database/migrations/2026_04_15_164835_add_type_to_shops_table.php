<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration NON-DESTRUCTIVE — Sprint 8 B2B Grossiste
 * Ajoute le champ `type` sur la table shops.
 *
 * Impact sur les données existantes : ZÉRO
 * Tous les shops actuels → type = 'particulier' (valeur par défaut)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->enum('type', ['particulier', 'grossiste'])
                  ->default('particulier')
                  ->after('is_colobane_verified');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
