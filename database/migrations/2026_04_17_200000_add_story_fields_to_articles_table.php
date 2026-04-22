<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : Story fields
 *
 * Ajoute deux colonnes à la table `articles` pour permettre
 * aux vendeurs de mettre leurs articles en Story sur la page d'accueil.
 *
 *  is_story        — booléen, true = l'article apparaît dans le carrousel Stories
 *  story_added_at  — timestamp nullable, date de la dernière mise en Story
 *
 * Les Stories expirent automatiquement après 24 h (géré côté app / cron).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Flag Story — par défaut false, index pour les requêtes du feed Stories
            $table->boolean('is_story')
                  ->default(false)
                  ->after('is_boosted');

            // Horodatage de la mise en Story
            $table->timestamp('story_added_at')
                  ->nullable()
                  ->after('is_story');

            // Index pour accélérer la requête "stories actives" côté FeedScreen
            $table->index(['is_story', 'story_added_at'], 'idx_articles_story');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('idx_articles_story');
            $table->dropColumn(['is_story', 'story_added_at']);
        });
    }
};
