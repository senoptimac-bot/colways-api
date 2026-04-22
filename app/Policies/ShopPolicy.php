<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

/**
 * ShopPolicy — Contrôle d'accès aux étals
 *
 * Règle absolue Colways : seul le propriétaire peut modifier son étal.
 * Laravel auto-découvre cette Policy via App\Models\Shop → App\Policies\ShopPolicy.
 */
class ShopPolicy
{
    /**
     * Modifier les infos de l'étal (nom, description, quartier).
     */
    public function update(User $user, Shop $shop): bool
    {
        return $user->id === $shop->user_id;
    }

    /**
     * Uploader l'avatar de l'étal.
     */
    public function uploadAvatar(User $user, Shop $shop): bool
    {
        return $user->id === $shop->user_id;
    }

    /**
     * Uploader la photo de couverture de l'étal.
     */
    public function uploadCover(User $user, Shop $shop): bool
    {
        return $user->id === $shop->user_id;
    }
}
