<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les colonnes taille et couleur aux articles.
     *
     * Optionnelles — les anciens articles restent valides (null).
     * Utilisées pour le filtrage ultra-spécifique (Sprint 10).
     *
     * size  : XS | S | M | L | XL | XXL | XXXL | 36..45 | 2A..14A | Unique
     * color : blanc | noir | gris | rouge | rose | orange | jaune |
     *         vert | bleu | violet | marron | beige | multicolore
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Après "condition" — nullable pour rétrocompatibilité
            $table->string('size',  20)->nullable()->after('condition')
                  ->comment('Taille du vêtement — libre ou valeur normalisée');
            $table->string('color', 30)->nullable()->after('size')
                  ->comment('Couleur principale — slug normalisé (ex: rouge, multicolore)');

            // Index pour les requêtes de filtrage
            $table->index('size');
            $table->index('color');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['size']);
            $table->dropIndex(['color']);
            $table->dropColumn(['size', 'color']);
        });
    }
};
