<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table articles — cœur de la marketplace Colways.
     * Les articles boostés actifs apparaissent en premier dans le feed.
     * audio_url et video_url sont prévus pour V1.1 (description vocale + vidéo courte).
     * share_count comptabilise les partages vers WhatsApp Status.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnDelete();

            // Redondance intentionnelle pour éviter une jointure supplémentaire
            $table->foreignId('user_id')
                ->constrained('users');

            // Informations de l'article
            $table->string('title', 200);
            $table->text('description')->nullable();

            // Prix en FCFA — entiers uniquement, pas de centimes au Sénégal
            $table->unsignedInteger('price');

            // Catégorie — 8 catégories Colways
            $table->enum('category', [
                'homme',
                'femme',
                'enfant',
                'chaussures',
                'montres_bijoux',
                'sacs_accessoires',
                'traditionnel',
                'sport',
            ]);

            // État du vêtement
            $table->enum('condition', ['neuf', 'tres_bon_etat', 'bon_etat']);

            // Statut de vente
            $table->enum('status', ['available', 'sold'])->default('available');

            // Boost ("Mettre en avant" dans l'interface) — 500 FCFA/24h ou 1000 FCFA/48h
            $table->boolean('is_boosted')->default(false);
            $table->timestamp('boost_expires_at')->nullable();

            // Description vocale V1.1 — max 30 secondes, stockée sur Cloudinary
            $table->string('audio_url', 500)->nullable();
            $table->smallInteger('audio_duration')->nullable(); // Durée en secondes

            // Vidéo de démonstration V1.1 — max 30 secondes, stockée sur Cloudinary
            $table->string('video_url', 500)->nullable();
            $table->tinyInteger('video_duration')->nullable(); // Durée en secondes (max 30)

            // Compteurs analytiques — incrémentés en arrière-plan (silencieux)
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('whatsapp_clicks')->default(0);

            // Partages vers WhatsApp Status — compteur utilisé pour les analytics
            $table->unsignedInteger('share_count')->default(0);

            $table->timestamps();

            // Index pour les requêtes du feed (filtres + tri boost/chrono)
            $table->index('shop_id');
            $table->index('category');
            $table->index('status');
            $table->index('is_boosted');
            $table->index('created_at');
        });
    }

    /**
     * Supprime la table articles.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
