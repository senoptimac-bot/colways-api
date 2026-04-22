<?php

namespace App\Observers;

use App\Models\Article;
use App\Services\CloudinaryService;
use App\Services\FriperieGuardianService;
use Illuminate\Support\Facades\Log;

class ArticleObserver
{
    /**
     * Déclenché AVANT la création — le Gardien analyse l'article
     * et injecte friperie_score, status et guardian_flags avant insertion.
     *
     * On utilise creating() et non created() pour que les valeurs soient
     * présentes dès la première insertion en base (pas de double requête).
     */
    public function creating(Article $article): void
    {
        try {
            $guardian = app(FriperieGuardianService::class);

            // Récupérer le niveau de confiance du vendeur
            $trustLevel = $article->shop?->trust_level ?? 'new';

            // Vérifier si le vendeur publie trop vite (> 10 articles en 1h)
            $publicationsRecentes = Article::where('user_id', $article->user_id)
                ->where('created_at', '>=', now()->subHour())
                ->count();
            $volumeSuspect = $publicationsRecentes >= 10;

            // Construire les données à analyser
            $data = [
                'title'          => $article->title,
                'description'    => $article->description,
                'price'          => $article->price,
                'category'       => $article->category,
                'condition'      => $article->condition,
                'shop_type'      => $article->shop?->type ?? 'particulier',
                'images_count'   => 0, // Pas encore uploadées à ce stade — réévalué après
                'volume_suspect' => $volumeSuspect,
            ];

            // Lancer l'analyse complète
            $result = $guardian->analyze($data, $trustLevel);

            // Injecter les résultats dans le modèle avant insertion
            $article->friperie_score  = $result['friperie_score'];
            $article->status          = $result['status'];
            $article->guardian_flags  = $result['guardian_flags'];
            $article->published_at    = $result['published_at'];

            // Stocker le message vendeur en propriété temporaire (pas en BDD)
            // ArticleController le lira pour la réponse API
            $article->guardian_message = $guardian->getVendorMessage($result['guardian_flags']);

        } catch (\Throwable $e) {
            // Si le Guardian plante pour une raison inattendue,
            // l'article est publié normalement (fail-open) — on ne bloque pas le vendeur.
            Log::error('[Guardian] Erreur analyse article', [
                'user_id' => $article->user_id,
                'error'   => $e->getMessage(),
            ]);
            $article->friperie_score = 50;
            $article->published_at   = now();
        }
    }

    /**
     * Déclenché APRÈS la création.
     * Incrémente le compteur d'articles de l'étal uniquement si
     * l'article est publié directement (pas en pending_review).
     */
    public function created(Article $article): void
    {
        // On compte uniquement les articles disponibles
        if ($article->status === 'available') {
            $article->shop()->increment('articles_count');
        }
    }

    /**
     * Déclenché APRÈS une modification d'article.
     *
     * Cas gérés :
     *   1. Passage à 'sold'               → décrémenter articles_count
     *   2. Retour à 'available'           → incrémenter articles_count
     *   3. Passage à 'available'          → même chose (après approbation admin)
     *   4. Modification titre/description → recalculer friperie_score
     */
    public function updated(Article $article): void
    {
        // ── Gestion du compteur d'articles de l'étal ─────────────────────────
        if ($article->wasChanged('status')) {
            $ancienStatus = $article->getOriginal('status');
            $nouveauStatus = $article->status;

            if ($nouveauStatus === 'sold') {
                $article->shop()->decrement('articles_count');

            } elseif ($ancienStatus === 'sold' && $nouveauStatus === 'available') {
                $article->shop()->increment('articles_count');

            } elseif ($ancienStatus === 'pending_review' && $nouveauStatus === 'available') {
                // Article approuvé par l'admin → apparaît dans le feed
                $article->shop()->increment('articles_count');
                // Mettre à jour published_at si pas encore défini
                if (! $article->published_at) {
                    $article->timestamps = false;
                    $article->update(['published_at' => now()]);
                    $article->timestamps = true;
                }
            }
        }

        // ── Recalcul du friperie_score si contenu modifié ────────────────────
        // Si le vendeur améliore son article (ajout photos, meilleure description),
        // le score est recalculé silencieusement.
        if ($article->wasChanged(['title', 'description', 'price'])) {
            try {
                $guardian    = app(FriperieGuardianService::class);
                $nbImages    = $article->images()->count();
                $newScore    = $guardian->recalculateScore([
                    'title'        => $article->title,
                    'description'  => $article->description,
                    'price'        => $article->price,
                    'category'     => $article->category,
                    'condition'    => $article->condition,
                    'images_count' => $nbImages,
                ]);

                // Mise à jour silencieuse (sans déclencher à nouveau updated)
                $article->timestamps = false;
                Article::withoutEvents(fn () =>
                    $article->update(['friperie_score' => $newScore])
                );
                $article->timestamps = true;

            } catch (\Throwable $e) {
                Log::warning('[Guardian] Recalcul score échoué', ['id' => $article->id]);
            }
        }
    }

    /**
     * Déclenché avant la suppression.
     * Nettoie toutes les ressources Cloudinary liées à l'article.
     */
    public function deleting(Article $article): void
    {
        if (config('services.cloudinary.cloud_name')) {
            $cloudinary = app(CloudinaryService::class);
            foreach ($article->images as $image) {
                $cloudinary->delete($image->cloudinary_id);
            }
        }
    }

    /**
     * Déclenché après la suppression.
     * Décrémente le compteur si l'article était visible.
     */
    public function deleted(Article $article): void
    {
        if ($article->status === 'available') {
            $article->shop()->decrement('articles_count');
        }

        // Mise à jour du trust_level du vendeur si l'article a été rejeté
        // (géré par l'AdminReviewController, pas ici)
    }
}
