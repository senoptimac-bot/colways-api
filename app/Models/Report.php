<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    /**
     * Pas de updated_at — les signalements ne sont pas modifiés après soumission.
     */
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    /**
     * Champs autorisés à l'assignation en masse.
     */
    protected $fillable = [
        'article_id',
        'reporter_id',
        'reason',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * Conversions automatiques de types.
     */
    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    /**
     * Un signalement concerne un article.
     */
    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Un signalement est soumis par un utilisateur (nullable — public autorisé).
     */
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Un signalement est traité par un admin.
     */
    public function reviewedByAdmin()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
