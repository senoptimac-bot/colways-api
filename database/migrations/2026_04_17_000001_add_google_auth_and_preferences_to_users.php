<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les colonnes nécessaires au Lazy Auth & Onboarding.
     *
     * google_id    — identifiant unique Google (sub du token Google)
     * email        — email optionnel (récupéré via Google)
     * preferences  — JSON des préférences onboarding (search_type, categories, want_to_sell)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Connexion Google One Tap
            $table->string('google_id')->nullable()->unique()->after('password');
            $table->string('email')->nullable()->unique()->after('google_id');

            // Préférences onboarding (JSON)
            $table->json('preferences')->nullable()->after('role');

            // Index Google ID pour les lookups rapides
            $table->index('google_id');
        });
    }

    /**
     * Annule les modifications.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['google_id']);
            $table->dropColumn(['google_id', 'email', 'preferences']);
        });
    }
};
