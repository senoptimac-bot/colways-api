<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table article_images.
     * Chaque article peut avoir de 1 à 5 photos, stockées sur Cloudinary.
     * position = 0 correspond à la photo principale affichée dans le feed.
     * cloudinary_id est indispensable pour supprimer les fichiers via l'API Cloudinary.
     */
    public function up(): void
    {
        Schema::create('article_images', function (Blueprint $table) {
            $table->id();

            // Suppression en cascade — si l'article est supprimé, ses images le sont aussi
            $table->foreignId('article_id')
                ->constrained('articles')
                ->cascadeOnDelete();

            // URL publique Cloudinary pour l'affichage
            $table->string('image_url', 500);

            // Identifiant Cloudinary — nécessaire pour appeler l'API de suppression
            $table->string('cloudinary_id', 200);

            // Ordre d'affichage — 0 = photo principale (affichée dans le feed)
            $table->unsignedTinyInteger('position')->default(0);

            // Pas de updated_at — les images ne sont pas modifiées, seulement créées/supprimées
            $table->timestamp('created_at')->nullable();

            // Index pour récupérer rapidement la photo principale (position=0)
            $table->index(['article_id', 'position']);
        });
    }

    /**
     * Supprime la table article_images.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_images');
    }
};
