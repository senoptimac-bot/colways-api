<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Champs autorisés à l'assignation en masse.
     * Le numéro WhatsApp est renseigné à l'inscription et validé (+221XXXXXXXXX).
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'google_id',
        'whatsapp_number',
        'whatsapp_verified',
        'password',
        'role',
        'preferences',
    ];

    /**
     * Champs masqués dans les réponses JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversions automatiques de types.
     */
    protected $casts = [
        'whatsapp_verified' => 'boolean',
        'password'          => 'hashed',
        'preferences'       => 'array',   // JSON auto-cast — stockage des préférences onboarding
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    /**
     * Un vendeur possède UN SEUL étal ("Mon étal").
     */
    public function shop()
    {
        return $this->hasOne(Shop::class);
    }

    /**
     * Un utilisateur peut avoir demandé plusieurs boosts (mises en avant).
     */
    public function boosts()
    {
        return $this->hasMany(Boost::class);
    }

    /**
     * Un utilisateur peut avoir signalé plusieurs articles.
     */
    public function reports()
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    /**
     * Un acheteur peut avoir envoyé plusieurs offres de prix.
     */
    public function priceOffers()
    {
        return $this->hasMany(PriceOffer::class, 'buyer_id');
    }

    /**
     * Le portefeuille de jetons Colways de l'utilisateur.
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Récupère ou crée le portefeuille de l'utilisateur.
     * Utilisé partout pour garantir qu'un wallet existe toujours.
     */
    public function getOrCreateWallet(): Wallet
    {
        return $this->wallet ?? $this->wallet()->create(['credits' => 0]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Accessor : URL absolue pour la photo de profil.
     */
    public function getProfilePhotoUrlAttribute($value)
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return \Illuminate\Support\Facades\Storage::url($value);
    }

    /**
     * Vérifie si l'utilisateur est un admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Vérifie si l'utilisateur est un vendeur.
     */
    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }
}
