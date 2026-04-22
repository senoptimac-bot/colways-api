<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleImage extends Model
{
    use HasFactory;

    /**
     * Pas de updated_at — les images ne sont pas modifiées, seulement créées/supprimées.
     */
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    /**
     * Champs autorisés à l'assignation en masse.
     */
    protected $fillable = [
        'article_id',
        'image_url',
        'cloudinary_id',
        'position',
    ];

    /**
     * Conversions automatiques de types.
     */
    protected $casts = [
        'position'   => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Accessor : URL absolue pour l'image de l'article.
     */
    public function getImageUrlAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return \Illuminate\Support\Facades\Storage::url($value);
    }

    // ─── Relations ────────────────────────────────────────────────────────────


    /**
     * Une image appartient à un article.
     */
    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
