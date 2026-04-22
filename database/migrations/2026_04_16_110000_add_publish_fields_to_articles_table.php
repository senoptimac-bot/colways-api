<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 11 — Formulaire de publication enrichi
 *
 * Nouveaux champs :
 *   size_fit             — Coupe / Fit (normal | petit | grand | oversize | slim)
 *   defects_list         — Checklist défauts au format JSON (array)
 *   defects_description  — Description libre des défauts (obligatoire si défaut coché)
 *   origin_country       — Pays d'origine (france | belgique | usa | uk | italie | dubai | autre)
 *   is_negotiable        — Prix négociable via WhatsApp (booléen, défaut : true)
 *
 * Note : le champ `condition` est étendu côté validation pour accepter
 *        les nouvelles valeurs (arrivage_neuf, premier_choix) — pas de migration nécessaire
 *        car la colonne est déjà de type string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Coupe / Fit — système anti-erreur de taille
            $table->string('size_fit', 20)->nullable()->after('size');

            // Détecteur de défauts — stocké en JSON pour la flexibilité
            $table->json('defects_list')->nullable()->after('size_fit');

            // Description libre des défauts (obligatoire côté app si défaut coché)
            $table->text('defects_description')->nullable()->after('defects_list');

            // Origine de la marchandise (confiance acheteur)
            $table->string('origin_country', 30)->nullable()->after('defects_description');

            // Prix négociable via WhatsApp (défaut : true — encourage les contacts)
            $table->boolean('is_negotiable')->default(true)->after('origin_country');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn([
                'size_fit',
                'defects_list',
                'defects_description',
                'origin_country',
                'is_negotiable',
            ]);
        });
    }
};
