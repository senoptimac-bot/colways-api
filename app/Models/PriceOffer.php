<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceOffer extends Model
{
    use HasFactory;

    /**
     * Table price_offers — Négociation de prix (V1.1).
     * En V1 : la négociation passe par WhatsApp (message pré-rempli).
     * En V1.1 : les offres sont gérées in-app via cette table.
     */
    protected $fillable = [
        'article_id',
        'buyer_id',
        'offered_price',
        'status',
        'counter_price',
        'message',
    ];

    /**
     * Conversions automatiques de types.
     */
    protected $casts = [
        'offered_price' => 'integer',
        'counter_price' => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    /**
     * Une offre concerne un article.
     */
    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Une offre est faite par un acheteur.
     */
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Génère le message WhatsApp pré-rempli pour la négociation V1.
     * Ex : "Salam ! Je suis intéressé par Jordan Retro 1. Je propose 25 000 FCFA. C'est possible ?"
     */
    public function generateWhatsAppMessage(): string
    {
        $titre = $this->article->title ?? 'cet article';
        $prix  = number_format($this->offered_price, 0, ',', ' ');

        return "Salam ! Je suis intéressé par {$titre}. Je propose {$prix} FCFA. C'est possible ?";
    }
}
