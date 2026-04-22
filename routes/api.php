<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\BoostController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\PriceOfferController;
use App\Http\Controllers\Admin\AdminReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Colways
|--------------------------------------------------------------------------
*/

// ─── Authentification ─────────────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login',    [AuthController::class, 'login'])->name('login');

    // Google One Tap — public (token vient du frontend)
    Route::post('/google',   [AuthController::class, 'googleLogin'])->name('google');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout',           [AuthController::class, 'logout'])->name('logout');
        Route::get ('/me',              [AuthController::class, 'me'])->name('me');
        Route::put ('/profile',         [AuthController::class, 'updateProfile'])->name('profile.update');
        Route::post('/profile/photo',   [AuthController::class, 'uploadPhoto'])->name('profile.photo');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
    });
});

// ─── Préférences utilisateur (Onboarding) ────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/user/preferences', [AuthController::class, 'saveOnboardingData'])->name('user.preferences');
});

// ─── Étals (Shops) ───────────────────────────────────────────────────────────
Route::apiResource('shops', ShopController::class)->only(['index', 'show']);

// Avis d'un étal — public
Route::get('shops/{shop}/reviews', [ReviewController::class, 'index'])->name('shops.reviews');

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('shops', ShopController::class)->except(['index', 'show']);
    Route::get('my/shop/dashboard',    [ShopController::class, 'dashboard'])->name('shops.dashboard');
    Route::get('my/shop/articles',     [ShopController::class, 'myArticles'])->name('shops.articles');
    Route::post('shops/{shop}/avatar', [ShopController::class, 'uploadAvatar'])->name('shops.avatar');
    Route::post('shops/{shop}/cover',  [ShopController::class, 'uploadCover'])->name('shops.cover');
    Route::post('my/shop/story-boost', [ShopController::class, 'storyBoost'])->name('shops.story-boost');
    Route::get ('my/shop/trust-center', [ShopController::class, 'trustCenter'])->name('shops.trust-center');
});

// ─── Articles ────────────────────────────────────────────────────────────────
Route::apiResource('articles', ArticleController::class)->only(['index', 'show']);

// Compteurs publics
Route::post('articles/{article}/view',          [ArticleController::class, 'incrementView']);
Route::post('articles/{article}/whatsapp-click',[ArticleController::class, 'incrementWhatsappClick']);
Route::post('articles/{article}/share',         [ArticleController::class, 'incrementShare']);

// V2 — Recommandations Similaires VIP (public, pas besoin de token)
Route::get('articles/{article}/similar', [ArticleController::class, 'similarVip'])->name('articles.similar');

Route::middleware('auth:sanctum')->group(function () {
    Route::patch('articles/{article}/sold',  [ArticleController::class, 'markAsSold'])->name('articles.sold');
    Route::post ('articles/{article}/story', [ArticleController::class, 'addToStory'])->name('articles.story');

    // Images articles
    Route::post  ('articles/{article}/images',          [ImageController::class, 'store'])->name('articles.images.store');
    Route::delete('articles/{article}/images/{image}',  [ImageController::class, 'destroy'])->name('articles.images.destroy');

    Route::apiResource('articles', ArticleController::class)->except(['index', 'show']);

    // V2 — Portefeuille de Jetons Colways
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get ('/',            [ArticleController::class, 'myWallet'])->name('index');
        Route::post('/claim-bonus', [ArticleController::class, 'claimSeedBonus'])->name('claim-bonus');
    });
});

// ─── Avis (Reviews) ───────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('reviews', [ReviewController::class, 'store'])->name('reviews.store');
});

// ─── Mises en avant (Boosts) ──────────────────────────────────────────────────
// (l'utilisateur peut voir le statut sans être connecté, par exemple après un retour PayDunya)
Route::get('boosts/{boost}/status',[BoostController::class, 'checkStatus'])->name('boosts.status');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('boosts/offres', [BoostController::class, 'offres'])->name('boosts.offres');
    Route::post('boosts',      [BoostController::class, 'store'])->name('boosts.store');
    Route::get ('my/boosts',   [BoostController::class, 'myBoosts'])->name('my.boosts');
});

// Webhook PayDunya (Sans Auth)
Route::post('webhooks/paydunya', [BoostController::class, 'webhook'])->name('webhooks.paydunya');

// ─── Signalements (Reports) ──────────────────────────────────────────────────
Route::post('reports', [ReportController::class, 'store'])->name('reports.store');

// ─── Négociations (Price Offers) ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get ('price-offers',           [PriceOfferController::class, 'index'])->name('price-offers.index');
    Route::post('articles/{article}/offer',[PriceOfferController::class, 'store'])->name('price-offers.store');
    Route::patch('price-offers/{offer}',   [PriceOfferController::class, 'update'])->name('price-offers.update');
});

// ─── Admin — File d'Attente Review (Gardien Friperie) ────────────────────────
Route::middleware(['auth:sanctum', 'is_admin'])
    ->prefix('admin/review')
    ->name('admin.review.')
    ->group(function () {
        Route::get ('pending',                       [AdminReviewController::class, 'pending'])->name('pending');
        Route::get ('stats',                         [AdminReviewController::class, 'stats'])->name('stats');
        Route::post('{article}/approve',             [AdminReviewController::class, 'approve'])->name('approve');
        Route::post('{article}/request-correction',  [AdminReviewController::class, 'requestCorrection'])->name('request-correction');
        Route::post('{article}/reject',              [AdminReviewController::class, 'reject'])->name('reject');
    });

// ─── Carte et Localisation ───────────────────────────────────────────────────
Route::prefix('map')->name('map.')->group(function () {
    Route::get('/quartiers', [MapController::class, 'quartiers'])->name('quartiers');
    Route::get('/locations', [MapController::class, 'locations'])->name('locations');
});
