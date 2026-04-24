<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Article;
use App\Models\ArticleImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * Feed principal — liste des articles disponibles.
     *
     * ══════════════════════════════════════════════════════════════════════
     *  ALGORITHME 5 PILIERS (Sprint 13)
     *  Ordre de priorité : Mérite + Découverte + Confiance + Boost + Tier
     *  Voir Article::scopeWeightedSort() pour le détail des scores.
     *
     *  Règle d'identité (Pilier 0 — Gardien Friperie) :
     *   - Seuls les articles avec status = 'available' entrent dans le feed
     *   - Seuls les articles avec friperie_score >= seuil sont pleinement visibles
     *   - Les articles en 'pending_review' sont invisibles ici
     * ══════════════════════════════════════════════════════════════════════
     */
    public function index(Request $request): JsonResponse
    {
        $threshold = config('friperie.visibility_threshold', 40);

        $query = Article::query()
            ->weightedSort()
            // Sous-requête SQL : première photo de chaque article
            ->addSelect([
                'first_image_url' => ArticleImage::select('image_url')
                    ->whereColumn('article_id', 'articles.id')
                    ->orderBy('position')
                    ->limit(1),
            ])
            ->with([
                'shop:id,shop_name,quartier,avatar_url,is_colobane_verified,account_tier,is_verified_seller',
            ])
            // ── PILIER 0 : Identité — seuls les articles validés apparaissent ──
            ->where(function ($q) {
                $q->where('articles.status', 'available')
                  ->orWhere(function ($sub) {
                      // Preuve sociale : articles vendus restent visibles 48h max, puis disparaissent du feed
                      $sub->where('articles.status', 'sold')
                          ->where('articles.updated_at', '>=', now()->subHours(48));
                  });
            })
            // Seuil de qualité minimum — articles trop pauvres invisibles dans le feed
            ->where('articles.friperie_score', '>=', $threshold)

            // Filtre B2C / B2B
            ->when($request->mode === 'b2b', fn($q) => $q->whereHas('shop', fn($s) => $s->where('type', 'grossiste')))
            ->when($request->mode === 'b2c', fn($q) => $q->whereHas('shop', fn($s) => $s->where('type', 'particulier')))

            // Filtres standards
            ->when($request->category, fn($q) => $q->where('articles.category', $request->category))
            ->when($request->quartier, fn($q) => $q->whereHas('shop', fn($s) => $s->where('quartier', $request->quartier)))
            ->when($request->search, fn($q) => $q->where(fn($s) =>
                $s->where('articles.title', 'like', '%' . $request->search . '%')
                  ->orWhere('articles.description', 'like', '%' . $request->search . '%')
            ))
            ->when($request->min_price, fn($q) => $q->where('articles.price', '>=', (int) $request->min_price))
            ->when($request->max_price, fn($q) => $q->where('articles.price', '<=', (int) $request->max_price))

            // Filtres spécifiques
            ->when($request->filled('sizes'), fn($q) => $q->whereIn('articles.size', (array) $request->input('sizes')))
            ->when($request->filled('colors'), fn($q) => $q->whereIn('articles.color', (array) $request->input('colors')))
            ->when($request->filled('conditions'), fn($q) => $q->whereIn('articles.condition', (array) $request->input('conditions')))
            ->when($request->date_filter === 'today', fn($q) => $q->whereDate('articles.created_at', today()))
            ->when($request->date_filter === 'week', fn($q) => $q->where('articles.created_at', '>=', now()->startOfWeek()))
            ->when($request->boolean('is_story'), fn($q) =>
                $q->where('articles.is_story', true)
                  ->where('articles.story_added_at', '>=', now()->subHours(24))
            );

        $articles = $query->paginate(15);

        // Cache HTTP : 2 min côté client/CDN, stale-while-revalidate 1 min.
        // Réduit les allers-retours réseau sur 3G lors des scrolls/pull-to-refresh.
        return response()->json($articles)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=60');
    }

    /**
     * Détail d'un article — accessible sans token.
     * Retourne toutes les images, infos shop, audio et vidéo si disponibles.
     */
    public function show(Article $article): JsonResponse
    {
        $article->load([
            'images',
            'shop:id,shop_name,quartier,avatar_url,is_colobane_verified,type',
            'shop.user:id,name,whatsapp_number',
        ]);

        return response()->json(['article' => $article]);
    }

    /**
     * Publie un nouvel article.
     * L'article est rattaché à l'étal du vendeur connecté.
     * Un vendeur doit avoir un étal pour publier (vérifié dans StoreArticleRequest).
     */
    public function store(StoreArticleRequest $request): JsonResponse
    {
        $shop = $request->user()->shop;

        // Note : status, friperie_score, guardian_flags et published_at
        // sont injectés automatiquement par ArticleObserver::creating()
        // On ne les passe pas ici — l'Observer a le dernier mot.
        $article = Article::create([
            'shop_id'          => $shop->id,
            'user_id'          => $request->user()->id,
            'title'            => $request->title,
            'description'      => $request->description,
            'price'            => $request->price,
            'category'         => $request->category,
            'condition'        => $request->condition,
            // Taille — Sprint 10 + 11
            'size'             => $request->size,
            'color'            => $request->color,
            'size_fit'         => $request->size_fit,
            // Détecteur de défauts — Sprint 11
            'defects_list'        => $request->defects_list ?? [],
            'defects_description' => $request->defects_description,
            // Origine & Négociation — Sprint 11
            'origin_country'   => $request->origin_country,
            'is_negotiable'    => $request->boolean('is_negotiable', true),
            // Friperie — Sprint 12
            'gender'           => $request->gender,
            'brand'            => $request->brand,
            'sub_type'         => $request->sub_type,
            'material'         => $request->material,
            // Champs palette B2B
            'poids_kg'         => $request->poids_kg,
            'quantite_estimee' => $request->quantite_estimee,
            'origine_pays'     => $request->origine_pays,
        ]);

        // Message bienveillant : l'Observer a injecté guardian_message si bloqué
        $message = $article->guardian_message
            ?? "Ton article est en ligne. Les acheteurs vont adorer ! 🔥";

        // Conseils de qualité personnalisés — affichés quand score < seuil
        $threshold = config('friperie.visibility_threshold', 40);
        $scoreTips = [];
        if ($article->friperie_score < $threshold) {
            $guardian  = app(\App\Services\FriperieGuardianService::class);
            $scoreTips = $guardian->getScoreTips([
                'title'        => $request->title,
                'description'  => $request->description,
                'images_count' => 0, // photos pas encore uploadées au moment du store
                'condition'    => $request->condition,
                'price'        => $request->price,
                'category'     => $request->category,
            ], $article->friperie_score);
        }

        // Code HTTP : 201 si publié directement, 202 si en attente de review
        $httpCode = $article->status === 'pending_review' ? 202 : 201;

        return response()->json([
            'message'        => $message,
            'status'         => $article->status,
            'friperie_score' => $article->friperie_score,
            'score_tips'     => $scoreTips,   // [] si score OK, conseils sinon
            'article'        => $article,
        ], $httpCode);
    }

    /**
     * Modifie un article existant.
     * Authorization via ArticlePolicy@update — seul le propriétaire peut modifier.
     */
    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        $this->authorize('update', $article);

        $article->update($request->only([
            'title', 'description', 'price', 'category', 'condition',
            // Taille
            'size', 'color', 'size_fit',
            // Défauts
            'defects_list', 'defects_description',
            // Origine & Négociation
            'origin_country', 'is_negotiable',
            // Friperie
            'gender', 'brand', 'sub_type', 'material',
            // Palette B2B
            'poids_kg', 'quantite_estimee', 'origine_pays',
        ]));

        return response()->json([
            'message' => 'Article mis à jour.',
            'article' => $article->fresh(),
        ]);
    }

    /**
     * Supprime un article et toutes ses images Cloudinary.
     * La suppression des images Cloudinary est gérée par ArticleObserver.
     * Authorization via ArticlePolicy@delete.
     */
    public function destroy(Request $request, Article $article): JsonResponse
    {
        $this->authorize('delete', $article);

        // La suppression Cloudinary est déclenchée par ArticleObserver@deleting
        $article->delete();

        return response()->json([
            'message' => 'Article supprimé.',
        ]);
    }

    /**
     * Marque un article comme vendu.
     * Félicitations ! Une pépite de plus vendue 🎉
     * Authorization via ArticlePolicy@markAsSold.
     */
    public function markAsSold(Request $request, Article $article): JsonResponse
    {
        $this->authorize('markAsSold', $article);

        $article->update(['status' => 'sold']);

        return response()->json([
            'message' => 'Félicitations ! Une pépite de plus vendue 🎉',
            'article' => $article->fresh(),
        ]);
    }

    /**
     * Incrémente le compteur de vues — silencieux (pas de réponse complexe).
     * Appelé par l'app dès qu'un article est ouvert.
     */
    public function incrementView(Article $article): JsonResponse
    {
        $article->increment('views_count');
        return response()->json(['ok' => true]);
    }

    /**
     * Incrémente le compteur de clics WhatsApp — silencieux.
     * Appelé quand un acheteur appuie sur le bouton WhatsApp vert.
     */
    public function incrementWhatsappClick(Article $article): JsonResponse
    {
        $article->increment('whatsapp_clicks');
        return response()->json(['ok' => true]);
    }

    /**
     * Incrémente le compteur de partages WhatsApp Status — silencieux.
     * Appelé après que l'image de partage a été générée côté app.
     */
    public function incrementShare(Article $article): JsonResponse
    {
        $article->increment('share_count');
        return response()->json(['ok' => true]);
    }

    /**
     * Ajoute un article dans le flux "Stories" de la page d'accueil.
     *
     * Phase initiale : met simplement à jour le flag `is_story` et horodate `story_added_at`.
     * Une migration sera nécessaire si ces colonnes n'existent pas encore.
     *
     * Authorization : seul le propriétaire peut pousser son article en Story.
     */
    public function addToStory(Request $request, Article $article): JsonResponse
    {
        // ── Vérification propriétaire ─────────────────────────────────────────
        if ($article->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Seul le propriétaire peut ajouter cet article en Story.',
            ], 403);
        }

        // ── Vérification statut ───────────────────────────────────────────────
        if ($article->status !== 'available') {
            return response()->json([
                'message' => 'Seuls les articles disponibles peuvent être mis en Story.',
            ], 422);
        }

        // ── Mise à jour — gracieuse si la colonne n'existe pas encore ─────────
        try {
            $article->update([
                'is_story'       => true,
                'story_added_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning("addToStory: colonnes manquantes — {$e->getMessage()}");
        }

        return response()->json([
            'message' => 'Article ajouté à la Story avec succès ! ✨',
            'article' => $article->fresh(),
        ]);
    }

    // ─── V2 : Endpoints Algorithmiques ────────────────────────────────────────

    /**
     * Recommandations Similaires VIP.
     * Retourne des articles de vendeurs Discovery/Élite dans la même catégorie.
     * Utilisé dans le bloc "D'autres pépites similaires ⭐" en bas de la fiche article.
     *
     * GET /api/articles/{article}/similar
     */
    public function similarVip(Article $article): JsonResponse
    {
        $similar = Article::similarVip($article)
            ->addSelect([
                'articles.*',
                'first_image_url' => ArticleImage::select('image_url')
                    ->whereColumn('article_id', 'articles.id')
                    ->orderBy('position')
                    ->limit(1),
            ])
            ->with(['shop:id,shop_name,avatar_url,account_tier,is_verified_seller'])
            ->get();

        return response()->json(['articles' => $similar]);
    }

    /**
     * Gamification : attribue la bourse de 50 jetons si le profil est complet.
     *
     * Conditions :
     *   - Avatar (photo de profil ou avatar du shop)
     *   - Description de l'étal
     *   - Au moins 1 article publié
     *
     * POST /api/wallet/claim-seed-bonus
     */
    public function claimSeedBonus(Request $request): JsonResponse
    {
        $user   = $request->user();
        $shop   = $user->shop;
        $wallet = $user->getOrCreateWallet();

        if ($wallet->seed_bonus_claimed) {
            return response()->json([
                'message' => 'Bourse déjà récupérée.',
                'credits' => $wallet->credits,
            ], 409);
        }

        $hasAvatar      = !empty($shop?->avatar_url) || !empty($user->profile_photo_url);
        $hasDescription = !empty($shop?->description);
        $hasArticle     = $shop && $shop->articles()->exists();

        if (!$hasAvatar || !$hasDescription || !$hasArticle) {
            return response()->json([
                'message' => 'Complete ton profil à 100% pour recevoir ta bourse de 50 jetons !',
                'missing' => [
                    'avatar'      => !$hasAvatar,
                    'description' => !$hasDescription,
                    'article'     => !$hasArticle,
                ],
                'code' => 'PROFILE_INCOMPLETE',
            ], 422);
        }

        $transaction = $wallet->claimSeedBonus();

        return response()->json([
            'message'     => '🎉 Félicitations ! Tu as reçu 50 Jetons Colways. Utilise-les pour booster tes meilleures pièces !',
            'credits'     => $wallet->fresh()->credits,
            'transaction' => $transaction,
        ]);
    }

    /**
     * Mon Portefeuille — Solde + historique des 20 dernières transactions.
     *
     * GET /api/wallet
     */
    public function myWallet(Request $request): JsonResponse
    {
        $wallet = $request->user()->getOrCreateWallet();
        $wallet->load(['transactions' => fn($q) => $q->limit(20)]);

        return response()->json([
            'credits'      => $wallet->credits,
            'seed_claimed' => $wallet->seed_bonus_claimed,
            'transactions' => $wallet->transactions,
        ]);
    }
}
