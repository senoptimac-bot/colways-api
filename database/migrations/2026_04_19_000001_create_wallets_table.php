<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ── Portefeuille de Jetons Colways ──────────────────────────────────────
     *
     * Chaque utilisateur possède un wallet avec un solde de crédits virtuels.
     * Les transactions sont tracées pour l'audit et le support client.
     *
     * Modèle économique :
     *   - 50 jetons offerts à la complétion du profil (gamification)
     *   - Rechargement via WhatsApp/Wave (MVP Concierge) puis PayDunya (V2.1)
     *   - Dépenses : Boost "Coup de Pioche", accès Stories, etc.
     */
    public function up(): void
    {
        // ── Table principale du portefeuille ─────────────────────────────────
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained()
                  ->cascadeOnDelete();

            $table->unsignedInteger('credits')->default(0)
                  ->comment('Solde actuel de jetons Colways');

            $table->boolean('seed_bonus_claimed')->default(false)
                  ->comment('True si la bourse de bienvenue (50 jetons) a déjà été attribuée');

            $table->timestamps();
        });

        // ── Historique des mouvements de crédits ─────────────────────────────
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->enum('type', ['credit', 'debit'])
                  ->comment('credit = rechargement / bonus | debit = achat de boost');

            $table->unsignedInteger('amount')
                  ->comment('Nombre de jetons concernés par cette opération');

            $table->unsignedInteger('balance_after')
                  ->comment('Solde du wallet APRÈS cette opération (snapshot)');

            $table->string('reason', 100)
                  ->comment('Ex: seed_bonus, manual_topup, boost_coup_de_pioche, boost_story');

            $table->string('reference')->nullable()
                  ->comment('ID externe (ex: article_id boosté, ou ref PayDunya future)');

            $table->text('note')->nullable()
                  ->comment('Note libre admin (ex: "Rechargement Wave reçu le 19/04")');

            $table->timestamps();

            // ── Index pour les requêtes fréquentes ───────────────────────────
            $table->index(['wallet_id', 'created_at']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('wallets');
    }
};
