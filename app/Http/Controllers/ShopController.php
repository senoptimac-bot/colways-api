<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShopRequest;
use App\Http\Requests\UpdateShopRequest;
use App\Models\ArticleImage;
use App\Models\Shop;
use App\Models\PriceOffer;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShopController extends Controller
{
    // CloudinaryService injecté uniquement dans les méthodes d'upload
    // pour éviter une erreur de config si les clés Cloudinary ne sont pas encore renseignées

    /**
     * Liste les étals publics pour la StoryBar du feed.
     *
     * Algorithme qualité (même philosophie que le feed articles) :
     *   1. Profil minimum requis : avatar + description + au moins 1 article dispo
     *      → Un étal vide ou sans photo n'apparaît pas dans la vitrine publique.
     *
     *   2. Score de qualité calculé côté SQL :
     *      +40  si Story VIP active (a payé 20 jetons)
     *      +30  si articles_approved_count >= 5 (contenu validé par le Gardien)
     *      +15  si trust_level = 'trusted' (vendeur certifié)
     *      +10  si cover_url non null (profil soigné)
     *      +5   si articles_approved_count >= 1 (au moins 1 article validé)
     *
     *   3. Limite : 20 étals. VIP remontent en tête automatiquement.
     */
    public function index(Request $request): JsonResponse
    {
        $now = now();

        $query = Shop::select(
                'id', 'shop_name', 'avatar_url', 'cover_url', 'type',
                'created_at', 'story_expires_at', 'trust_level',
                'articles_approved_count', 'is_colobane_verified'
            )
            ->withCount(['articles as available_articles_count' => function ($q) {
                $q->where('status', 'available');
            }])
            // ── Critères minimums de qualité ──────────────────────────────────
            ->whereNotNull('avatar_url')           // Doit avoir une photo de profil
            ->where(function ($q) {                // Doit avoir une description non vide
                $q->whereNotNull('description')
                  ->where('description', '!=', '');
            })
            ->whereHas('articles', function ($q) { // Doit avoir au moins 1 article dispo
                $q->where('status', 'available');
            });

        // ── Filtre catégorie (optionnel) ───────────────────────────────────────
        if ($request->filled('category')) {
            $category = $request->category;
            $query->whereHas('articles', function ($q) use ($category) {
                $q->where('category', $category)->where('status', 'available');
            });
        }

        // ── Filtre type d'étal ─────────────────────────────────────────────────
        if ($request->filled('type') && in_array($request->type, ['grossiste', 'particulier'])) {
            $query->where('type', $request->type);
        }

        // ── Score qualité — même levier que l'algorithme articles ──────────────
        $shops = $query
            ->orderByRaw("
                (
                    CASE WHEN story_expires_at > ? THEN 40 ELSE 0 END +
                    CASE WHEN articles_approved_count >= 15 THEN 30
                         WHEN articles_approved_count >= 5  THEN 15
                         WHEN articles_approved_count >= 1  THEN 5
                         ELSE 0 END +
                    CASE WHEN trust_level = 'trusted' THEN 15 ELSE 0 END +
                    CASE WHEN cover_url IS NOT NULL THEN 10 ELSE 0 END
                ) DESC
            ", [$now])
            ->orderByDesc('articles_approved_count')
            ->limit(20)
            ->get();

        return response()->json([
            'shops' => $shops,
        ]);
    }

    /**
     * Crée l'étal du vendeur connecté ("Mon étal").
     * Un utilisateur ne peut avoir qu'un seul étal — vérifié ici.
     */
    public function store(StoreShopRequest $request): JsonResponse
    {
        // Vérifier qu'il n'a pas déjà un étal
        if ($request->user()->shop) {
            return response()->json([
                'message' => 'Tu as déjà un étal sur Colways.',
            ], 422);
        }

        $shop = Shop::create([
            'user_id'         => $request->user()->id,
            'shop_name'       => $request->shop_name,
            'description'     => $request->description,
            'quartier'        => $request->quartier ?? 'autre',
            'address'         => $request->address ?? $request->quartier ?? 'Adresse non renseignée',
            'type'            => $request->type ?? 'particulier',
            'type_updated_at' => now(), // Initialiser lors de la création
            'latitude'        => $request->latitude,
            'longitude'       => $request->longitude,
        ]);

        // Passer le rôle à "seller" maintenant qu'il a un étal
        $request->user()->update(['role' => 'seller']);

        return response()->json([
            'message' => 'Cet étal est en préparation 🪝 Ajoute tes premiers articles !',
            'shop'    => $shop,
        ], 201);
    }

    /**
     * Affiche un étal public avec ses articles disponibles et ses stats.
     * Accessible sans token — visible par tous les acheteurs.
     */
    public function show(Shop $shop): JsonResponse
    {
        // Charger l'étal avec son propriétaire et ses articles actifs
        $shop->load([
            'user:id,name,whatsapp_number',
            'articles' => function ($query) {
                $query->select('articles.*')
                      ->addSelect([
                          'first_image_url' => ArticleImage::select('image_url')
                              ->whereColumn('article_id', 'articles.id')
                              ->orderBy('position')
                              ->limit(1),
                      ])
                      ->where('status', 'available')
                      ->orderByDesc('is_boosted')
                      ->orderByDesc('created_at')
                      ->limit(50);
            },
        ]);

        return response()->json([
            'shop' => $shop,
        ]);
    }

    /**
     * Modifie les informations de l'étal.
     * Authorization via ShopPolicy@update.
     */
    public function update(UpdateShopRequest $request, Shop $shop): JsonResponse
    {
        $this->authorize('update', $shop);

        $data = $request->validated();

        // Sécurité Colways : Verrouillage du type d'étal (Sprint 12)
        // Empêche le changement de statut Particulier/Grossiste plus d'une fois tous les 30 jours.
        if (isset($data['type']) && $data['type'] !== $shop->type) {
            $lastUpdate = $shop->type_updated_at;
            if ($lastUpdate && $lastUpdate->gt(now()->subDays(30))) {
                $daysRemaining = $lastUpdate->addDays(30)->diffInDays(now());
                return response()->json([
                    'message' => "🔒 Statut verrouillé. Tu pourras le modifier dans $daysRemaining jours."
                ], 422);
            }
            $data['type_updated_at'] = now();
        }

        $shop->update($data);

        return response()->json([
            'message' => 'Étal mis à jour.',
            'shop'    => $shop->fresh(),
        ]);
    }

    /**
     * Upload l'avatar de l'étal (photo de profil du vendeur).
     * Format carré — compressé automatiquement par Cloudinary.
     * Vérification ownership avant tout upload.
     */
    public function uploadAvatar(Request $request, Shop $shop, CloudinaryService $cloudinary): JsonResponse
    {
        $this->authorize('uploadAvatar', $shop);

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:5120'],
        ]);

        // Supprimer l'ancien avatar de Cloudinary si il existe
        if ($shop->avatar_cloudinary_id ?? null) {
            $cloudinary->delete($shop->avatar_cloudinary_id);
        }

        // Upload du nouvel avatar
        $resultat = $cloudinary->upload(
            $request->file('avatar'),
            'colways/shops/avatars'
        );

        $shop->update([
            'avatar_url'           => $resultat['url'],
            'avatar_cloudinary_id' => $resultat['cloudinary_id'],
        ]);

        return response()->json([
            'message'    => 'Photo de profil mise à jour.',
            'avatar_url' => $resultat['url'],
        ]);
    }

    /**
     * Upload la photo de couverture de l'étal (format 16:9).
     * Compressée et recadrée automatiquement par Cloudinary.
     * Vérification ownership avant tout upload.
     */
    public function uploadCover(Request $request, Shop $shop, CloudinaryService $cloudinary): JsonResponse
    {
        $this->authorize('uploadCover', $shop);

        $request->validate([
            'cover' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:5120'],
        ]);

        // Supprimer l'ancienne cover de Cloudinary si elle existe
        if ($shop->cover_cloudinary_id ?? null) {
            $cloudinary->delete($shop->cover_cloudinary_id);
        }

        // Upload de la nouvelle cover
        $resultat = $cloudinary->upload(
            $request->file('cover'),
            'colways/shops/covers'
        );

        $shop->update([
            'cover_url'           => $resultat['url'],
            'cover_cloudinary_id' => $resultat['cloudinary_id'],
        ]);

        return response()->json([
            'message'   => 'Photo de couverture mise à jour.',
            'cover_url' => $resultat['url'],
        ]);
    }

    /**
     * Dashboard Vendeur (Merchant Elite)
     * Statistiques de performance et offres en attente.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        $shop = $user->shop;

        if (!$shop) {
            return response()->json(['message' => 'Étal non trouvé.'], 404);
        }

        $articles = $shop->articles;

        $stats = [
            'total_views'     => (int) $articles->sum('views_count'),
            'total_contacts'  => (int) $articles->sum('whatsapp_clicks'),
            'total_shares'    => (int) $articles->sum('share_count'),
            'articles_count'  => $articles->count(),
            'active_boosts'   => $articles->where('is_boosted', true)->count(),
        ];

        // Dernières offres reçues pour l'étal
        $latestOffers = PriceOffer::with(['article:id,title,price', 'buyer:id,name'])
            ->whereHas('article', function($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $wallet = $user->getOrCreateWallet();

        return response()->json([
            'shop'           => $shop->only(['id', 'shop_name', 'avatar_url', 'cover_url', 'is_colobane_verified', 'type']),
            'stats'          => $stats,
            'latest_offers'  => $latestOffers,
            'wallet_balance' => (int) $wallet->credits,
        ]);
    }

    /**
     * Liste des articles de "Mon étal" (Inventaire complet)
     * Utilisé par le Centre de Commande pour un chargement scalable.
     */
    public function myArticles(Request $request): JsonResponse
    {
        $user = Auth::user();
        $shop = $user->shop;

        if (!$shop) {
            return response()->json(['message' => 'Étal non trouvé.'], 404);
        }

        $articles = $shop->articles()
            ->select('articles.*')
            ->addSelect([
                'first_image_url' => \App\Models\ArticleImage::select('image_url')
                    ->whereColumn('article_id', 'articles.id')
                    ->orderBy('position')
                    ->limit(1),
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($articles);
    }

    /**
     * Propulse l'étal en Story VIP (en haut du Feed).
     * Coûte 20 jetons pour 24 heures.
     */
    public function storyBoost(Request $request): JsonResponse
    {
        $user = $request->user();
        $shop = $user->shop;

        if (!$shop) {
            return response()->json(['message' => 'Étal introuvable.'], 404);
        }

        $wallet = $user->getOrCreateWallet();
        $cost = 20;

        if ($wallet->credits < $cost) {
            return response()->json([
                'message' => "Solde insuffisant. Il vous manque " . ($cost - $wallet->credits) . " jetons.",
                'error_code' => 'INSUFFICIENT_FUNDS',
            ], 402);
        }

        // Débiter le compte
        $wallet->spendCredits($cost, 'shop_story_vip', $shop->id);

        // Mettre à jour la date d'expiration de la Story
        $shop->update([
            'story_expires_at' => now()->addHours(24)
        ]);

        return response()->json([
            'message' => 'Ton étal est propulsé en Story VIP pour les 24 prochaines heures ! 🚀⭕',
            'wallet_balance' => $wallet->credits,
            'story_expires_at' => $shop->story_expires_at
        ]);
    }
    /**
     * Centre de Confiance — Tableau de bord de progression du vendeur.
     * Retourne toutes les métriques réelles pour calculer et afficher la progression.
     */
    public function trustCenter(Request $request): JsonResponse
    {
        $user = $request->user();
        $shop = $user->shop;

        if (!$shop) {
            return response()->json(['message' => 'Étal introuvable.'], 404);
        }

        // ── Articles ────────────────────────────────────────────────────────
        $articlesTotal    = $shop->articles()->count();
        $articlesSold     = $shop->articles()->where('status', 'sold')->count();
        $articlesActive   = $shop->articles()->where('status', 'available')->count();
        $articlesApproved = (int) $shop->articles_approved_count; // compteur tenu à jour par FriperieGuardianService
        $APPROVED_TARGET  = 30;

        // ── Profil complété ──────────────────────────────────────────────────
        $hasAvatar      = !empty($shop->avatar_url);
        $hasDescription = !empty($shop->description);
        $hasArticle     = $articlesTotal >= 1;

        // ── Performance ─────────────────────────────────────────────────────
        $totalViews    = $shop->articles()->sum('views_count');
        $totalContacts = $shop->articles()->sum('whatsapp_clicks');
        $totalShares   = $shop->articles()->sum('shares_count');

        // ── Story VIP ───────────────────────────────────────────────────────
        $wallet           = $user->getOrCreateWallet();
        $storyVipCount    = $wallet->transactions()->where('type', 'shop_story_vip')->count();
        $storyActiveUntil = $shop->story_expires_at && now()->lt($shop->story_expires_at)
            ? $shop->story_expires_at
            : null;

        // ── Avis clients ─────────────────────────────────────────────────────
        $reviews     = \App\Models\Review::where('shop_id', $shop->id)->get();
        $reviewCount = $reviews->count();
        $avgRating   = $reviewCount > 0 ? round($reviews->avg('rating'), 1) : 0;
        $REVIEWS_TARGET = 5;
        $RATING_TARGET  = 4.5;

        // ── Niveau de confiance — basé sur trust_level (auto, Gardien Friperie)
        // trust_level: 'new' → 'trusted' après $APPROVED_TARGET articles approuvés
        $trustLevel  = $shop->trust_level ?? 'new';
        $isTrusted   = $trustLevel === 'trusted';
        $isFlagged   = $trustLevel === 'flagged';
        $isCertified = $isTrusted; // "Vendeur Certifié" = trust_level trusted
        $isTop       = $isCertified && $reviewCount >= $REVIEWS_TARGET && $avgRating >= $RATING_TARGET;

        $currentLevel = 'new';
        if ($isTop)        $currentLevel = 'top';
        elseif ($isCertified) $currentLevel = 'certified';

        // ── Message d'encouragement (tutoiement, motivant) ──────────────────
        $remaining = max(0, $APPROVED_TARGET - $articlesApproved);
        $encouragement = match(true) {
            $isTop       => '🏆 Tu es au sommet de Colways. Continue de briller !',
            $isCertified => $reviewCount >= 3
                ? '💬 Plus que ' . ($REVIEWS_TARGET - $reviewCount) . ' avis pour devenir Top Vendeur !'
                : '⭐ Demande des avis à tes acheteurs pour progresser.',
            $articlesApproved >= 8
                => "🔥 Plus que {$remaining} article(s) approuvé(s) — tu y es presque !",
            $articlesApproved >= 5
                => "💪 {$articlesApproved}/{$APPROVED_TARGET} articles approuvés. Continue sur ta lancée !",
            $hasArticle
                => "🛡️ Publie des articles de qualité pour que le Gardien les approuve.",
            default
                => '🚀 Publie tes premiers articles pour lancer ton étal !',
        };

        // ── Checklist pour le niveau en cours ───────────────────────────────
        $certChecklist = [
            [
                'done'    => $hasArticle,
                'label'   => 'Publier au moins 1 article',
                'value'   => null,
                'total'   => null,
            ],
            [
                'done'    => $hasDescription,
                'label'   => 'Renseigner une description de boutique',
                'value'   => null,
                'total'   => null,
            ],
            [
                'done'    => $hasAvatar,
                'label'   => 'Ajouter une photo de profil',
                'value'   => null,
                'total'   => null,
            ],
            [
                'done'    => $articlesApproved >= $APPROVED_TARGET,
                'label'   => 'Articles approuvés par le Gardien Friperie',
                'value'   => $articlesApproved,
                'total'   => $APPROVED_TARGET,
            ],
        ];

        $topChecklist = [
            [
                'done'  => $isCertified,
                'label' => 'Être Vendeur Certifié',
                'value' => null,
                'total' => null,
            ],
            [
                'done'  => $reviewCount >= $REVIEWS_TARGET,
                'label' => 'Recevoir au moins 5 avis clients',
                'value' => $reviewCount,
                'total' => $REVIEWS_TARGET,
            ],
            [
                'done'  => $avgRating >= $RATING_TARGET,
                'label' => "Maintenir une note ≥ {$RATING_TARGET}/5",
                'value' => (float) $avgRating,
                'total' => $RATING_TARGET,
            ],
        ];

        $nextStep = null;
        if (!$isCertified) {
            $nextStep = ['level' => 'certified', 'checklist' => $certChecklist];
        } elseif (!$isTop) {
            $nextStep = ['level' => 'top', 'checklist' => $topChecklist];
        }

        return response()->json([
            'level'         => $currentLevel,
            'trust_level'   => $trustLevel,
            'is_certified'  => $isCertified,
            'is_top'        => $isTop,
            'is_flagged'    => $isFlagged,
            'encouragement' => $encouragement,
            'next_step'     => $nextStep,
            'checklists'    => [
                'certified' => $certChecklist,
                'top'       => $topChecklist,
            ],

            'metrics' => [
                'articles_total'       => $articlesTotal,
                'articles_sold'        => $articlesSold,
                'articles_active'      => $articlesActive,
                'articles_approved'    => $articlesApproved,
                'articles_approved_target' => $APPROVED_TARGET,
                'has_avatar'           => $hasAvatar,
                'has_description'      => $hasDescription,
                'total_views'          => $totalViews,
                'total_contacts'       => $totalContacts,
                'total_shares'         => $totalShares,
                'story_vip_count'      => $storyVipCount,
                'story_active_until'   => $storyActiveUntil,
                'review_count'         => $reviewCount,
                'avg_rating'           => $avgRating,
                'wallet_balance'       => $wallet->credits,
            ],
        ]);
    }
}

