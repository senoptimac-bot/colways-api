<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;

    /**
     * Champs autorisés à l'assignation en masse.
     */
    protected $fillable = [
        'user_id',
        'shop_name',
        'type',
        'description',
        'avatar_url',
        'avatar_cloudinary_id',
        'cover_url',
        'cover_cloudinary_id',
        'quartier',
        'address',
        'latitude',
        'longitude',
        'specialties',
        'is_colobane_verified',
        'colobane_verified_at',
        'type_updated_at',
        'articles_count',
        // V2 — Système de niveaux (Pass Colways)
        'account_tier',
        'tier_expires_at',
        'is_verified_seller',
        'daily_impressions',
    ];

    /**
     * Conversions automatiques de types.
     */
    protected $casts = [
        'is_colobane_verified' => 'boolean',
        'colobane_verified_at' => 'datetime',
        'type_updated_at'      => 'datetime',
        'articles_count'       => 'integer',
        'latitude'             => 'decimal:8',
        'longitude'            => 'decimal:8',
        'specialties'          => 'array',
        // V2
        'tier_expires_at'      => 'datetime',
        'is_verified_seller'   => 'boolean',
        'daily_impressions'    => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    /**
     * L'étal appartient à un vendeur.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un étal possède plusieurs articles.
     */
    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Articles disponibles (non vendus) — utilisé pour articles_count.
     */
    public function activeArticles()
    {
        return $this->hasMany(Article::class)->where('status', 'available');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Accessor : URL absolue pour l'avatar.
     */
    public function getAvatarUrlAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return \Illuminate\Support\Facades\Storage::url($value);
    }

    /**
     * Accessor : URL absolue pour la couverture.
     */
    public function getCoverUrlAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return \Illuminate\Support\Facades\Storage::url($value);
    }

    /**
     * Vérifie si cet étal appartient à l'utilisateur donné.
     */
    public function ownedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    // ─── V2 : Système de Niveaux ─────────────────────────────────────────────

    /**
     * Vérifie si le vendeur est au niveau Standard (gratuit).
     */
    public function isStandard(): bool
    {
        return $this->account_tier === 'standard';
    }

    /**
     * Vérifie si le vendeur est au niveau Découverte ou supérieur.
     */
    public function isDiscoveryOrAbove(): bool
    {
        return in_array($this->account_tier, ['discovery', 'elite']);
    }

    /**
     * Vérifie si le vendeur est au niveau Élite.
     */
    public function isElite(): bool
    {
        return $this->account_tier === 'elite';
    }

    /**
     * Vérifie si l'abonnement est encore actif (non expiré).
     */
    public function isTierActive(): bool
    {
        if ($this->account_tier === 'standard') return true;
        if (!$this->tier_expires_at) return true; // pas de date = permanent (fondateur)
        return $this->tier_expires_at->isFuture();
    }

    /**
     * Rétrograde le vendeur au niveau Standard si son abonnement est expiré.
     */
    public function downgradeIfExpired(): bool
    {
        if (!$this->isTierActive()) {
            $this->update([
                'account_tier'    => 'standard',
                'tier_expires_at' => null,
            ]);
            return true;
        }
        return false;
    }
}
