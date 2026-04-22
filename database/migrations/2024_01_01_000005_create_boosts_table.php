<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table boosts ("Mettre en avant" dans l'interface Colways).
     * V1 : confirmation manuelle par l'admin (payment_method = 'manual').
     * V1.1 : paiement Wave / Orange Money automatique.
     * Modèle freemium : 500 FCFA/24h ou 1 000 FCFA/48h.
     */
    public function up(): void
    {
        Schema::create('boosts', function (Blueprint $table) {
            $table->id();

            // Article mis en avant
            $table->foreignId('article_id')
                ->constrained('articles');

            // Vendeur ayant demandé la mise en avant
            $table->foreignId('user_id')
                ->constrained('users');

            // Durée du boost : 24h (500 FCFA) ou 48h (1 000 FCFA)
            $table->unsignedTinyInteger('duration_hours'); // 24 ou 48

            // Montant en FCFA
            $table->unsignedSmallInteger('amount_fcfa'); // 500 ou 1000

            // Méthode de paiement — 'manual' en V1, Wave/OM en V1.1
            $table->enum('payment_method', ['wave', 'orange_money', 'manual'])->default('manual');

            // Statut du boost
            $table->enum('payment_status', ['pending', 'confirmed', 'expired'])->default('pending');

            // Référence paiement Wave/OM (V1.1)
            $table->string('payment_ref', 100)->nullable();

            // Dates d'activation — remplies quand l'admin confirme
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Traçabilité : quel admin a confirmé et quand
            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users');
            $table->timestamp('confirmed_at')->nullable();

            // Pas de updated_at — les boosts ne sont pas modifiés, seulement confirmés/expirés
            $table->timestamp('created_at')->nullable();

            // Index pour le scheduler (désactivation des boosts expirés) et l'admin
            $table->index('article_id');
            $table->index('payment_status');
            $table->index('expires_at');
        });
    }

    /**
     * Supprime la table boosts.
     */
    public function down(): void
    {
        Schema::dropIfExists('boosts');
    }
};
