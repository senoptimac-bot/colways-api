<?php

namespace App\Providers;

use App\Models\Article;
use App\Observers\ArticleObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * Enregistrement des observers — ils écoutent les événements des modèles.
     */
    public function boot(): void
    {
        // ArticleObserver maintient le compteur articles_count sur les étals
        // et nettoie Cloudinary lors de la suppression d'articles
        Article::observe(ArticleObserver::class);
    }
}
