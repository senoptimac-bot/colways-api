<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Review;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * GET /api/shops/{shop}/reviews
     * Retourne les avis d'un étal (paginés, 10 par page).
     * Route publique — aucune auth requise.
     */
    public function index(Shop $shop): JsonResponse
    {
        $reviews = Review::where('shop_id', $shop->id)
            ->with('reviewer:id,name')
            ->latest()
            ->paginate(10);

        return response()->json($reviews);
    }

    /**
     * POST /api/reviews
     * Laisser un avis sur un étal.
     * Auth requise — 1 avis par utilisateur par étal (unique).
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId    = $request->user()->id;

        // Empêcher de s'auto-noter
        $shop = Shop::find($validated['shop_id']);
        if ($shop->user_id === $userId) {
            return response()->json([
                'message' => 'Vous ne pouvez pas noter votre propre étal.',
            ], 422);
        }

        // Upsert — met à jour si l'avis existe déjà
        $review = Review::updateOrCreate(
            [
                'shop_id'     => $validated['shop_id'],
                'reviewer_id' => $userId,
            ],
            [
                'article_id' => $validated['article_id'] ?? null,
                'rating'     => $validated['rating'],
                'comment'    => isset($validated['comment'])
                    ? strip_tags(trim($validated['comment']))
                    : null,
            ]
        );

        return response()->json([
            'message' => 'Avis publié avec succès.',
            'review'  => $review->load('reviewer:id,name'),
        ], 201);
    }
}
