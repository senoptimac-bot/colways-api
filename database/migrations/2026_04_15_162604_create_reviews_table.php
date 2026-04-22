<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table reviews — Avis vendeurs
 *
 * Un utilisateur peut laisser un avis par étal.
 * L'avis est optionnellement lié à un article (article_id nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            // Étal évalué
            $table->foreignId('shop_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Auteur de l'avis (acheteur)
            $table->foreignId('reviewer_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Article à l'origine du contact (optionnel)
            $table->foreignId('article_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            // Note (1 à 5)
            $table->unsignedTinyInteger('rating');  // 1-5

            // Commentaire texte libre (optionnel, max 500 chars)
            $table->string('comment', 500)->nullable();

            $table->timestamps();

            // Un utilisateur = 1 avis par étal (unicité)
            $table->unique(['shop_id', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
