<?php

use App\Models\Article;
use App\Models\Boost;
use App\Models\Shop;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler Colways — Tâches automatiques
|--------------------------------------------------------------------------
*/

// Désactiver les boosts expirés — toutes les heures
// Si un article était boosté et que le boost a expiré, on le remet à false
Schedule::call(function () {
    $boostsExpires = Boost::where('payment_status', 'confirmed')
        ->where('expires_at', '<', now())
        ->get();

    foreach ($boostsExpires as $boost) {
        // Marquer le boost comme expiré
        $boost->update(['payment_status' => 'expired']);

        // Désactiver le boost sur l'article
        $boost->article?->update([
            'is_boosted'       => false,
            'boost_expires_at' => null,
        ]);
    }

    // Invalider le cache de la carte si des boosts ont expiré
    if ($boostsExpires->count() > 0) {
        Cache::forget('map_quartiers');
    }
})->hourly()->name('colways:expirer-boosts')->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Nettoyage des articles vendus — toutes les heures
|--------------------------------------------------------------------------
|
| Règle Colways : un article marqué "Vendu" reste visible 24h pour la
| preuve sociale (les acheteurs voient que la pépite s'est écoulée vite).
| Passé ce délai, l'article est supprimé définitivement de la base.
| Les images Cloudinary sont supprimées par l'ArticleObserver@deleting.
|
*/
Schedule::call(function () {
    // Articles dont le statut est 'sold' ET dont la mise à jour date de > 24h
    $expired = Article::where('status', 'sold')
        ->where('updated_at', '<', now()->subHours(24))
        ->get();

    $count = $expired->count();

    if ($count === 0) {
        return; // Rien à faire
    }

    foreach ($expired as $article) {
        // La suppression Cloudinary est déclenchée automatiquement
        // par ArticleObserver@deleting — pas besoin d'appel explicite ici
        $article->delete();
    }

    \Illuminate\Support\Facades\Log::info("Colways GC: {$count} article(s) vendu(s) supprimé(s) après 24h.");
})->hourly()->name('colways:supprimer-articles-vendus')->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| V2 — Rétrogradation automatique des abonnements expirés
|--------------------------------------------------------------------------
|
| Vérifie toutes les heures si des vendeurs Discovery/Élite ont un
| abonnement expiré. Si oui, leur tier est remis à 'standard'.
|
*/
Schedule::call(function () {
    $downgraded = Shop::whereIn('account_tier', ['discovery', 'elite'])
        ->whereNotNull('tier_expires_at')
        ->where('tier_expires_at', '<', now())
        ->get();

    $count = 0;
    foreach ($downgraded as $shop) {
        if ($shop->downgradeIfExpired()) {
            $count++;
        }
    }

    if ($count > 0) {
        \Illuminate\Support\Facades\Log::info("Colways V2: {$count} boutique(s) rétrogradée(s) au niveau Standard (abonnement expiré).");
    }
})->hourly()->name('colways:downgrade-expired-tiers')->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| V2 — Reset des compteurs Story (Impressions journalières)
|--------------------------------------------------------------------------
|
| À minuit pile, remet à zéro les compteurs d'impressions Story
| pour garantir une rotation équitable entre les vendeurs Élite.
|
*/
Schedule::call(function () {
    Shop::where('daily_impressions', '>', 0)
        ->update(['daily_impressions' => 0]);
})->dailyAt('00:00')->name('colways:reset-story-impressions');

/*
|--------------------------------------------------------------------------
| V2 — Nettoyage des articles non-vendus des comptes Standard (15 jours)
|--------------------------------------------------------------------------
|
| Les articles des comptes Standard qui n'ont pas été vendus en 15 jours
| sont supprimés automatiquement pour libérer l'espace Cloud.
| Les comptes Discovery (30j) et Élite (jamais) ne sont pas concernés.
|
*/
Schedule::call(function () {
    $expired = Article::where('status', 'available')
        ->where('created_at', '<', now()->subDays(15))
        ->whereHas('shop', function ($q) {
            $q->where('account_tier', 'standard');
        })
        ->get();

    $count = $expired->count();

    if ($count === 0) return;

    foreach ($expired as $article) {
        $article->delete();
    }

    \Illuminate\Support\Facades\Log::info("Colways V2 GC: {$count} article(s) Standard non-vendu(s) supprimé(s) après 15 jours.");
})->dailyAt('03:00')->name('colways:gc-standard-articles')->withoutOverlapping();
