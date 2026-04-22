<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Boost extends Model
{
    use HasFactory;

    /**
     * Pas de updated_at — les boosts ne sont pas modifiés après création.
     */
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    /**
     * Champs autorisés à l'assignation en masse.
     */
    protected $fillable = [
        'article_id',
        'user_id',
        'duration_hours',
        'amount_fcfa',
        'payment_method',
        'payment_status',
        'payment_ref',
        'starts_at',
        'expires_at',
        'confirmed_by',
        'confirmed_at',
    ];

    /**
     * Conversions automatiques de types.
     */
    protected $casts = [
        'starts_at'     => 'datetime',
        'expires_at'    => 'datetime',
        'confirmed_at'  => 'datetime',
        'created_at'    => 'datetime',
        'duration_hours' => 'integer',
        'amount_fcfa'   => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    /**
     * Un boost concerne un article.
     */
    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Un boost est demandé par un vendeur.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un boost est confirmé par un admin.
     */
    public function confirmedByAdmin()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Vérifie si ce boost est actuellement actif.
     */
    public function isActive(): bool
    {
        return $this->payment_status === 'confirmed'
            && $this->expires_at?->isFuture();
    }
}
