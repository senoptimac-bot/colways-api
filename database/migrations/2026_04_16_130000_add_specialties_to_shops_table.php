<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 11 — Crédibilité Vendeur
 * Ajoute le champ specialties à la table shops.
 * Affiché sur le profil public uniquement si type = 'grossiste'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('specialties', 200)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('specialties');
        });
    }
};
