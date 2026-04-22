<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

/**
 * ArticlePolicy — Contrôle d'accès aux articles
 *
 * Seul le propriétaire de l'étal peut modifier / supprimer ses articles.
 * Laravel auto-découvre cette Policy via App\Models\Article → App\Policies\ArticlePolicy.
 */
class ArticlePolicy
{
    /**
     * Modifier un article (titre, prix, description, catégorie).
     */
    public function update(User $user, Article $article): bool
    {
        return $user->id == $article->user_id;
    }

    /**
     * Supprimer un article.
     */
    public function delete(User $user, Article $article): bool
    {
        return $user->id == $article->user_id;
    }

    /**
     * Marquer un article comme vendu.
     */
    public function markAsSold(User $user, Article $article): bool
    {
        return $user->id == $article->user_id;
    }

    /**
     * Ajouter ou supprimer des photos d'un article.
     */
    public function manageImages(User $user, Article $article): bool
    {
        return $user->id == $article->user_id;
    }

    /**
     * Booster un article.
     */
    public function boost(User $user, Article $article): bool
    {
        return $user->id == $article->user_id;
    }
}
