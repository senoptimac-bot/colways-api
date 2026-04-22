<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * ─────────────────────────────────────────────────────────────────────────────
 *  Colways Admin — File d'Attente Review (Sprint 13)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  Ce contrôleur gère la file d'attente des articles en pending_review.
 *  L'admin peut :
 *    - Lister les articles en attente (avec les flags du Gardien)
 *    - Approuver un article → publié immédiatement
 *    - Demander une correction → vendeur notifié avec explication
 *    - Rejeter un article → masqué + compteur de rejets du vendeur incrémenté
 *
 *  Impact sur le trust_level du vendeur :
 *    - Approbation → articles_approved_count++
 *    - Rejet       → articles_rejected_count++
 *                 → si rejets >= seuil → trust_level passe à 'flagged'
 *
 *  Toutes les routes de ce contrôleur sont protégées par le middleware IsAdmin.
 */
class AdminReviewController extends Controller
{
    /**
     * Liste les articles en pending_review, triés par date de création.
     * Inclut les flags du Gardien pour aider l'admin à décider rapidement.
     *
     * GET /api/admin/review/pending
     */
    public function pending(Request $request): JsonResponse
    {
        $articles = Article::where('status', 'pending_review')
            ->with([
                'shop:id,shop_name,quartier,trust_level,articles_approved_count,articles_rejected_count',
                'shop.user:id,name,phone',
                'images:article_id,image_url,position',
            ])
            ->orderBy('created_at', 'asc') // Plus anciens en premier (FIFO)
            ->paginate(20);

        return response()->json([
            'total'    => Article::where('status', 'pending_review')->count(),
            'articles' => $articles,
        ]);
    }

    /**
     * Approuve un article → le rend visible dans le feed immédiatement.
     *
     * POST /api/admin/review/{article}/approve
     */
    public function approve(Article $article): JsonResponse
    {
        if ($article->status !== 'pending_review') {
            return response()->json(['message' => 'Cet article n\'est pas en attente de review.'], 422);
        }

        $article->update([
            'status'       => 'available',
            'published_at' => now(),
        ]);

        // Mettre à jour le compteur du vendeur + promouvoir si éligible
        $this->incrementerApprobations($article->shop);

        // Notifier le vendeur
        $this->notifierVendeur(
            $article,
            'approuve',
            "Bonne nouvelle ! Ton article \"{$article->title}\" a été validé et est maintenant visible sur Colways. Les acheteurs vont adorer ! 🔥"
        );

        return response()->json([
            'message' => "Article #{$article->id} approuvé et publié.",
            'article' => $article->fresh(),
        ]);
    }

    /**
     * Demande une correction au vendeur — article reste invisible mais pas rejeté.
     *
     * POST /api/admin/review/{article}/request-correction
     * Body: { reason: string }
     */
    public function requestCorrection(Request $request, Article $article): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        if ($article->status !== 'pending_review') {
            return response()->json(['message' => 'Cet article n\'est pas en attente de review.'], 422);
        }

        // On garde le status pending_review mais on ajoute le flag correction
        $article->update([
            'guardian_flags' => array_merge(
                $article->guardian_flags ?? [],
                ['admin_correction_demandee']
            ),
        ]);

        // Notifier le vendeur avec l'explication précise
        $this->notifierVendeur(
            $article,
            'correction',
            "Ton article \"{$article->title}\" nécessite une correction avant d'être publié.\n\nRaison : {$request->reason}\n\nModifie ton article et il sera réexaminé automatiquement."
        );

        return response()->json([
            'message' => "Demande de correction envoyée au vendeur.",
        ]);
    }

    /**
     * Rejette définitivement un article — masqué, compteur de rejets incrémenté.
     *
     * POST /api/admin/review/{article}/reject
     * Body: { reason: string }
     */
    public function reject(Request $request, Article $article): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        if ($article->status !== 'pending_review') {
            return response()->json(['message' => 'Cet article n\'est pas en attente de review.'], 422);
        }

        $article->update(['status' => 'blocked']);

        // Incrémenter les rejets + vérifier le trust_level
        $this->incrementerRejets($article->shop);

        // Notifier le vendeur avec une explication bienveillante
        $this->notifierVendeur(
            $article,
            'rejete',
            "Ton article \"{$article->title}\" n'a pas pu être publié sur Colways.\n\nRaison : {$request->reason}\n\nColways est une marketplace dédiée à la friperie sénégalaise — vêtements et accessoires de seconde main uniquement.\n\nTu as des questions ? Contacte-nous sur WhatsApp."
        );

        return response()->json([
            'message'     => "Article #{$article->id} rejeté.",
            'trust_level' => $article->shop->fresh()->trust_level,
        ]);
    }

    /**
     * Statistiques de la file d'attente — tableau de bord admin.
     *
     * GET /api/admin/review/stats
     */
    public function stats(): JsonResponse
    {
        $seuil = config('friperie.visibility_threshold', 40);

        return response()->json([
            'pending_review'       => Article::where('status', 'pending_review')->count(),
            'blocked'              => Article::where('status', 'blocked')->count(),
            'available'            => Article::where('status', 'available')->count(),
            'low_score'            => Article::where('status', 'available')
                                        ->where('friperie_score', '<', $seuil)->count(),
            'trusted_shops'        => Shop::where('trust_level', 'trusted')->count(),
            'flagged_shops'        => Shop::where('trust_level', 'flagged')->count(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Helpers privés
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Incrémente le compteur d'approbations du vendeur.
     * Si le seuil est atteint → promotion automatique à 'trusted'.
     */
    private function incrementerApprobations(Shop $shop): void
    {
        $shop->increment('articles_approved_count');
        $shop->refresh();

        $seuil = config('friperie.trust.promote_to_trusted_after', 10);

        if (
            $shop->trust_level === 'new' &&
            $shop->articles_approved_count >= $seuil
        ) {
            $shop->update(['trust_level' => 'trusted']);
        }
    }

    /**
     * Incrémente le compteur de rejets du vendeur.
     * Si le seuil est atteint → rétrogradation à 'flagged'.
     */
    private function incrementerRejets(Shop $shop): void
    {
        $shop->increment('articles_rejected_count');
        $shop->refresh();

        $seuil = config('friperie.trust.flag_after_rejections', 2);

        if ($shop->articles_rejected_count >= $seuil) {
            $shop->update(['trust_level' => 'flagged']);
        }
    }

    /**
     * Notifie le vendeur par email du résultat de la review.
     * Silencieux si MAIL non configuré.
     */
    private function notifierVendeur(Article $article, string $type, string $message): void
    {
        try {
            $email = $article->shop?->user?->email;
            if (! $email) {
                return;
            }

            $sujets = [
                'approuve'   => "✅ Ton article est en ligne — Colways",
                'correction' => "✏️ Correction demandée pour ton article — Colways",
                'rejete'     => "❌ Article non publié — Colways",
            ];

            Mail::raw(
                $message,
                fn ($m) => $m
                    ->to($email)
                    ->subject($sujets[$type] ?? "[Colways] Mise à jour de ton article")
            );
        } catch (\Exception $e) {
            // Silencieux
        }
    }
}
