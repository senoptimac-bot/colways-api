<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    /**
     * Propriétés temporaires (non persistées en BDD).
     * guardian_message est injecté par ArticleObserver::creating()
     * et lu par ArticleController pour la réponse API au vendeur.
     */
    public ?string $guardian_message = null;

    /**
     * Champs autorisés à l'assignation en masse.
     */
    protected $fillable = [
        'shop_id',
        'user_id',
        'title',
        'description',
        'price',
        'category',
        'condition',
        'status',
        'is_boosted',
        'boost_expires_at',
        'is_story',
        'story_added_at',
        'audio_url',
        'audio_duration',
        'video_url',
        'video_duration',
        'views_count',
        'whatsapp_clicks',
        'share_count',
        // Sprint 10 & 11
        'size',
        'color',
        'size_fit',
        'defects_list',
        'defects_description',
        'origin_country',
        'is_negotiable',
        'poids_kg',
        'quantite_estimee',
        'origine_pays',
        // Sprint 12
        'gender',
        'brand',
        'sub_type',
        'material',
        // Sprint 13 — Gardien Friperie
        'friperie_score',
        'guardian_flags',
        'published_at',
    ];

    /**
     * Conversions automatiques de types.
     */
    protected $casts = [
        'is_boosted'       => 'boolean',
        'boost_expires_at' => 'datetime',
        'is_story'         => 'boolean',
        'story_added_at'   => 'datetime',
        'price'            => 'integer',
        'views_count'      => 'integer',
        'whatsapp_clicks'  => 'integer',
        'share_count'      => 'integer',
        'is_negotiable'    => 'boolean',
        'defects_list'     => 'array',
        // Sprint 13 — Gardien Friperie
        'friperie_score'   => 'integer',
        'guardian_flags'   => 'array',
        'published_at'     => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    /**
     * Un article appartient à un étal.
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Un article appartient à un vendeur.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un article possède plusieurs images (max 5).
     */
    public function images()
    {
        return $this->hasMany(ArticleImage::class)->orderBy('position');
    }

    /**
     * Photo principale de l'article (position = 0) — affichée dans le feed.
     */
    public function mainImage()
    {
        return $this->hasOne(ArticleImage::class)->where('position', 0);
    }

    /**
     * Un article peut avoir plusieurs demandes de mise en avant.
     */
    public function boosts()
    {
        return $this->hasMany(Boost::class);
    }

    /**
     * Un article peut avoir plusieurs signalements.
     */
    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Un article peut recevoir plusieurs offres de prix (V1.1).
     */
    public function priceOffers()
    {
        return $this->hasMany(PriceOffer::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Vérifie si le boost est actif au moment de l'appel.
     */
    public function isBoostedActive(): bool
    {
        return $this->is_boosted && $this->boost_expires_at?->isFuture();
    }

    /**
     * Accessor : URL absolue pour le fichier audio.
     */
    public function getAudioUrlAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return \Illuminate\Support\Facades\Storage::url($value);
    }

    /**
     * Accessor : URL absolue pour le fichier vidéo.
     */
    public function getVideoUrlAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return \Illuminate\Support\Facades\Storage::url($value);
    }

    /**
     * Vérifie si cet article appartient à l'utilisateur donné.
     */
    public function ownedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    // ─── Sprint 13 : Algorithme de Visibilité — 5 Piliers ───────────────────

    /**
     * Scope algorithmique principal : trie les articles par score de visibilité.
     *
     * ══════════════════════════════════════════════════════════════════════════
     *  PILIER 0 — IDENTITÉ (Gardien Friperie)
     *    Seuls les articles avec friperie_score >= seuil entrent dans le feed.
     *    Les articles 'pending_review' ou 'sold' sont exclus (géré dans index()).
     *
     *  PILIER 1 — MÉRITE (gratuit, récompense la qualité du contenu)
     *    friperie_score × 0.3 → max 30 pts
     *    Clics WhatsApp / vues > 5% → +15 pts (engagement réel)
     *    Partages WA Status > 0    → +10 pts (signal viral)
     *
     *  PILIER 2 — DÉCOUVERTE (gratuit, démocratique)
     *    Nouveau vendeur (étal < 7 jours) → +20 pts décroissant
     *    Fraîcheur de l'article → 0 à 30 pts sur 48h (décroissance linéaire)
     *    ← calculé en PHP pour compatibilité SQLite + MySQL
     *
     *  PILIER 3 — CONFIANCE (mérité, non achetable)
     *    Badge Colobane vérifié → +25 pts
     *    Vendeur vérifié (is_verified_seller) → +15 pts
     *
     *  PILIER 4 — AMPLIFICATION (payant, multiplie le mérite)
     *    Boost actif × multiplicateur qualité → max 40 pts
     *    Multiplicateur : score >= 70 → ×1.5 | score >= 40 → ×1.0 | < 40 → ×0.5
     *
     *  PILIER 5 — TIER VENDEUR (payant, réduit pour équité)
     *    Élite     → +60 pts (réduit de 100)
     *    Discovery → +30 pts (réduit de 50)
     *
     *  Score maximum théorique : ~210 pts (Élite + tout)
     *  Score Standard sérieux  : ~120 pts (peut concurrencer un Discovery)
     * ══════════════════════════════════════════════════════════════════════════
     *
     * Compatibilité : SQLite (dev Herd) + MySQL (prod api.colways.sn)
     * Les calculs temporels sont passés comme bindings PHP (pas datetime SQL).
     */
    public function scopeWeightedSort($query)
    {
        $cfg = config('friperie.algorithm');

        // ── Calculs temporels en PHP (agnostique SQLite/MySQL) ────────────────
        $now          = now();
        $cutoff48h    = $now->copy()->subHours(48)->toDateTimeString();  // fraîcheur
        $cutoff7days  = $now->copy()->subDays(7)->toDateTimeString();    // nouveau vendeur
        $tierElite    = (int) ($cfg['tier_elite_pts']     ?? 60);
        $tierDisc     = (int) ($cfg['tier_discovery_pts'] ?? 30);
        $colobane     = (int) ($cfg['colobane_verified_pts']  ?? 25);
        $verified     = (int) ($cfg['verified_seller_pts']    ?? 15);
        $boostBase    = (int) ($cfg['boost_base_pts']          ?? 20);
        $newSeller    = (int) ($cfg['new_seller_boost_pts']    ?? 20);
        $freshMax     = (int) ($cfg['freshness_max_pts']       ?? 30);
        $qualMult     = (float)($cfg['quality_multiplier']     ?? 0.3);
        $engClicks    = (int) ($cfg['engagement_clicks_pts']   ?? 15);
        $engShares    = (int) ($cfg['engagement_shares_pts']   ?? 10);

        return $query
            ->leftJoin('shops', 'articles.shop_id', '=', 'shops.id')
            ->select('articles.*')
            ->selectRaw("
                (
                    /* ── PILIER 1 : Mérite — qualité × 0.3 (max 30 pts) ─── */
                    CAST(articles.friperie_score * {$qualMult} AS INTEGER)

                    /* ── Engagement : clics WhatsApp > 5% des vues ────────── */
                    + CASE
                        WHEN articles.views_count > 0
                             AND CAST(articles.whatsapp_clicks AS FLOAT) / articles.views_count >= 0.05
                        THEN {$engClicks}
                        ELSE 0
                      END

                    /* ── Engagement : partages WA Status ───────────────────── */
                    + CASE WHEN articles.share_count > 0 THEN {$engShares} ELSE 0 END

                    /* ── PILIER 2 : Fraîcheur — décroît sur 48h ────────────── */
                    + CASE
                        WHEN articles.published_at >= ?
                        THEN CAST(
                            {$freshMax} * (1.0 - (
                                (JULIANDAY('now') - JULIANDAY(articles.published_at)) * 24.0 / 48.0
                            ))
                        AS INTEGER)
                        ELSE 0
                      END

                    /* ── PILIER 2 : Nouveau vendeur (étal < 7 jours) ──────── */
                    + CASE
                        WHEN shops.created_at >= ?
                        THEN {$newSeller}
                        ELSE 0
                      END

                    /* ── PILIER 3 : Confiance — badge Colobane ──────────────── */
                    + CASE WHEN shops.is_colobane_verified = 1 THEN {$colobane} ELSE 0 END

                    /* ── PILIER 3 : Confiance — vendeur vérifié ─────────────── */
                    + CASE WHEN shops.is_verified_seller = 1 THEN {$verified} ELSE 0 END

                    /* ── PILIER 4 : Boost actif × multiplicateur qualité ────── */
                    + CASE
                        WHEN articles.is_boosted = 1 AND articles.boost_expires_at > datetime('now')
                        THEN CASE
                            WHEN articles.friperie_score >= 70 THEN CAST({$boostBase} * 1.5 AS INTEGER)
                            WHEN articles.friperie_score >= 40 THEN {$boostBase}
                            ELSE CAST({$boostBase} * 0.5 AS INTEGER)
                          END
                        ELSE 0
                      END

                    /* ── PILIER 5 : Tier vendeur (payant, équité réduite) ───── */
                    + CASE
                        WHEN shops.account_tier = 'elite'     THEN {$tierElite}
                        WHEN shops.account_tier = 'discovery' THEN {$tierDisc}
                        ELSE 0
                      END

                ) AS visibility_score
            ", [$cutoff48h, $cutoff7days])
            ->orderByDesc('visibility_score')
            ->orderByDesc('articles.published_at');
    }

    /**
     * Scope : Articles « Recommandations Similaires ».
     *
     * Retourne les articles disponibles dans la même catégorie,
     * en excluant l'article consulté. Les vendeurs Discovery/Elite
     * sont favorisés via le tri (ORDER BY account_tier DESC) mais
     * les vendeurs standard apparaissent aussi pour garantir du contenu.
     */
    public function scopeSimilarVip($query, Article $excludeArticle)
    {
        return $query
            ->where('articles.id', '!=', $excludeArticle->id)
            ->where('articles.category', $excludeArticle->category)
            ->where('articles.status', 'available')
            ->leftJoin('shops', 'articles.shop_id', '=', 'shops.id')
            ->select('articles.*')
            ->orderByRaw("
                CASE
                    WHEN shops.account_tier = 'elite' THEN 3
                    WHEN shops.account_tier = 'discovery' THEN 2
                    ELSE 1
                END DESC
            ")
            ->inRandomOrder()
            ->limit(6);
    }
}

