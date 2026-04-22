<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table price_offers — Négociation de prix (V1.1).
     * En V1, la négociation se fait via WhatsApp (message pré-rempli).
     * Cette table est créée maintenant pour éviter une migration ultérieure.
     *
     * Interface : l'acheteur propose un prix → message WA pré-rempli généré :
     * "Salam ! Je suis intéressé par [titre]. Je propose [X] FCFA. C'est possible ?"
     */
    public function up(): void
    {
        Schema::create('price_offers', function (Blueprint $table) {
            $table->id();

            // Article concerné par l'offre
            $table->foreignId('article_id')
                ->constrained('articles')
                ->cascadeOnDelete();

            // Acheteur ayant fait l'offre
            $table->foreignId('buyer_id')
                ->constrained('users');

            // Prix proposé en FCFA
            $table->unsignedInteger('offered_price');

            // Statut de l'offre
            $table->enum('status', ['pending', 'accepted', 'refused', 'countered'])->default('pending');

            // Contre-offre du vendeur (si status = 'countered')
            $table->unsignedInteger('counter_price')->nullable();

            // Message optionnel accompagnant l'offre
            $table->text('message')->nullable();

            $table->timestamps();

            // Index pour les vues "offres reçues" et "offres envoyées"
            $table->index('article_id');
            $table->index('buyer_id');
            $table->index('status');
        });
    }

    /**
     * Supprime la table price_offers.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_offers');
    }
};
