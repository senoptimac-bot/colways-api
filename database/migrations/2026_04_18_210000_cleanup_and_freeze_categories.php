<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nettoyage de la base de données pour figer les catégories racines
     * Nouvelles catégories exclusives : vetements, chaussures, sacs, montres, casquettes, accessoires.
     */
    public function up(): void
    {
        // On map les anciennes catégories vers les nouvelles si possible
        DB::table('articles')
            ->whereIn('category', ['homme', 'femme', 'enfant'])
            ->update(['category' => 'vetements']);
            
        DB::table('articles')
            ->where('category', 'sacs_accessoires')
            ->update(['category' => 'sacs']);
            
        DB::table('articles')
            ->where('category', 'montres_bijoux')
            ->update(['category' => 'montres']);
            
        DB::table('articles')
            ->whereIn('category', ['sport', 'autre', 'traditionnel'])
            ->update(['category' => 'vetements']); // Fallback
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de retour en arrière possible de manière fiable car la notion d'homme/femme/enfant est perdue
    }
};
