<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ── Système de Niveaux de Comptes (Tiers) ───────────────────────────────
     *
     * Ajoute les colonnes de statut permettant à l'algorithme de différencier
     * les vendeurs Standard, Découverte et Élite.
     *
     * Hiérarchie :
     *   standard  → Vendeur occasionnel (gratuit, quotas limités)
     *   discovery → Abonné "Coup de Pioche" (bump quotidien, articles similaires)
     *   elite     → Vendeur Renommé / Grossiste (stories, badge certifié, illimité)
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->enum('account_tier', ['standard', 'discovery', 'elite'])
                  ->default('standard')
                  ->after('type')
                  ->comment('Niveau du compte vendeur pour l\'algorithme de visibilité');

            $table->boolean('is_verified_seller')->default(false)
                  ->after('is_colobane_verified')
                  ->comment('Vendeur Renommé certifié manuellement par l\'équipe Colways');

            $table->timestamp('tier_expires_at')->nullable()
                  ->after('account_tier')
                  ->comment('Date d\'expiration de l\'abonnement (null = permanent / gratuit)');

            $table->unsignedInteger('daily_impressions')->default(0)
                  ->after('articles_count')
                  ->comment('Compteur de vues Story journalières (reset à minuit par le scheduler)');

            // ── Index pour l'algorithme ──────────────────────────────────────
            $table->index('account_tier');
            $table->index('is_verified_seller');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropIndex(['account_tier']);
            $table->dropIndex(['is_verified_seller']);
            $table->dropColumn([
                'account_tier',
                'tier_expires_at',
                'is_verified_seller',
                'daily_impressions',
            ]);
        });
    }
};
