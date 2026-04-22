<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Met à jour la liste des catégories autorisées.
     * On passe en string simple pour supprimer la contrainte CHECK rigide de SQLite qui bloque 'autre' et 'accessoires'.
     * La validation est désormais assurée par StoreArticleRequest.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('category')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->enum('category', [
                'homme',
                'femme',
                'enfant',
                'chaussures',
                'montres_bijoux',
                'sacs_accessoires',
                'traditionnel',
                'sport',
            ])->change();
        });
    }
};
