<?php

namespace App\Services;

/**
 * ─────────────────────────────────────────────────────────────────────────────
 *  Colways — Le Gardien Friperie
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  Ce service est le "cerveau" qui protège l'identité de Colways.
 *  Il analyse chaque article au moment de sa création et détermine :
 *
 *    1. Le friperie_score (0-100) — qualité et cohérence avec la friperie
 *    2. Le status initial     — 'available' ou 'pending_review'
 *    3. Les guardian_flags    — liste des raisons de blocage éventuelles
 *
 *  Philosophie :
 *    - On ne punit pas, on éduque. Les messages d'erreur sont bienveillants.
 *    - Le score récompense l'effort (photos, description, prix cohérent).
 *    - Un vendeur de confiance (trust_level = 'trusted') est publié directement.
 *    - Un vendeur suspect (trust_level = 'flagged') passe toujours en review.
 *
 *  Tous les paramètres viennent de config/friperie.php — aucune valeur magique
 *  dans le code.
 */
class FriperieGuardianService
{
    // ── Résultat de l'analyse ─────────────────────────────────────────────────

    private int    $score    = 0;
    private array  $flags    = [];
    private string $status   = 'available';

    // ─────────────────────────────────────────────────────────────────────────
    //  Point d'entrée principal — appelé par ArticleObserver::creating()
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Analyse un article et retourne le résultat de la vérification.
     *
     * @param  array  $data        Données de l'article (title, description, price, images_count, ...)
     * @param  string $trustLevel  Niveau de confiance du vendeur : new / trusted / flagged
     * @return array{
     *   friperie_score: int,
     *   status: string,
     *   guardian_flags: array,
     *   published_at: \Carbon\Carbon|null
     * }
     */
    public function analyze(array $data, string $trustLevel = 'new'): array
    {
        // Réinitialisation pour réutilisation du service
        $this->score  = 0;
        $this->flags  = [];
        $this->status = 'available';

        // ── Les vendeurs de confiance passent directement ─────────────────────
        if ($trustLevel === 'trusted') {
            $this->score = $this->calculateQualityScore($data);
            return $this->buildResult(publish: true);
        }

        // ── Les vendeurs suspects → review manuelle systématique ──────────────
        if ($trustLevel === 'flagged') {
            $this->score = $this->calculateQualityScore($data);
            $this->flags[] = 'vendeur_suspect';
            $this->status  = 'pending_review';
            return $this->buildResult(publish: false);
        }

        // ── Analyse complète pour les vendeurs 'new' (cas standard) ──────────
        $this->runIdentityChecks($data);
        $this->score = $this->calculateQualityScore($data);

        // Si des flags bloquants ont été levés → pending_review
        if ($this->hasBlockingFlags()) {
            $this->status = 'pending_review';
            return $this->buildResult(publish: false);
        }

        // Score insuffisant → article invisible mais pas bloqué
        // (il reste en 'available' mais friperie_score < threshold = hors feed)
        return $this->buildResult(publish: true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Vérifications d'Identité (Pilier 0 — Gardien)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Vérifie que l'article correspond à l'identité friperie de Colways.
     * Lève des flags si des incohérences sont détectées.
     */
    private function runIdentityChecks(array $data): void
    {
        $texte = strtolower(($data['title'] ?? '') . ' ' . ($data['description'] ?? ''));

        // ── 1. Mots interdits ─────────────────────────────────────────────────
        $banned = config('friperie.banned_keywords', []);
        foreach ($banned as $categorie => $mots) {
            foreach ($mots as $mot) {
                if (str_contains($texte, $mot)) {
                    $this->flags[] = "mot_interdit:{$categorie}:{$mot}";
                }
            }
        }

        // ── 2. Fourchette de prix ─────────────────────────────────────────────
        $price  = (int) ($data['price'] ?? 0);
        $ranges = config('friperie.price_ranges');

        if ($price < $ranges['minimum']) {
            $this->flags[] = 'prix_trop_bas';
        }

        $shopType = $data['shop_type'] ?? 'particulier';
        $plafond  = $shopType === 'grossiste'
            ? $ranges['maximum_b2b']
            : $ranges['maximum_b2c'];

        if ($price > $plafond) {
            $this->flags[] = 'prix_trop_eleve';
        }

        // ── 3. Volume anormal de publications ─────────────────────────────────
        // Détecté en amont dans ArticleObserver — flag transmis ici si présent
        if (! empty($data['volume_suspect'])) {
            $this->flags[] = 'volume_publication_anormal';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Score de Qualité (Pilier 1 — Mérite)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcule le friperie_score selon les critères de qualité Colways.
     *
     * Score maximum : 100 pts
     *   Photos       : 0-25 pts
     *   Description  : 0-20 pts
     *   Titre        : 0-20 pts
     *   Prix cohérent: 0-10 pts
     *   Condition    : 0-10 pts
     *   Catégorie    : 0-15 pts (défini par le formulaire — valeur fixe si OK)
     */
    private function calculateQualityScore(array $data): int
    {
        $score   = 0;
        $weights = config('friperie.quality_weights');
        $ranges  = config('friperie.price_ranges');

        // ── Photos ────────────────────────────────────────────────────────────
        $nbPhotos = (int) ($data['images_count'] ?? 0);
        if ($nbPhotos >= 3) {
            $score += $weights['photos_3_plus'];        // 25 pts
        } elseif ($nbPhotos >= 2) {
            $score += $weights['photos_min_2'];         // 15 pts
        } elseif ($nbPhotos === 1) {
            $score += 5;                                // 5 pts minimum
        }

        // ── Description ───────────────────────────────────────────────────────
        $descLen = mb_strlen(trim($data['description'] ?? ''));
        if ($descLen >= 80) {
            $score += $weights['description_80_chars']; // 20 pts
        } elseif ($descLen >= 30) {
            $score += $weights['description_30_chars']; // 10 pts
        }

        // ── Titre ─────────────────────────────────────────────────────────────
        $titre    = trim($data['title'] ?? '');
        $titreLen = mb_strlen($titre);

        if ($titreLen >= 10) {
            $score += $weights['title_10_chars'];       // 10 pts
        }

        // Bonus si le titre n'est pas un mot générique
        if ($titreLen >= 10 && ! $this->isTitleGeneric($titre)) {
            $score += $weights['title_non_generic'];    // 10 pts
        }

        // ── Prix dans la fourchette normale ───────────────────────────────────
        $price = (int) ($data['price'] ?? 0);
        if ($price >= $ranges['normal_min'] && $price <= $ranges['normal_max']) {
            $score += $weights['price_in_range'];       // 10 pts
        }

        // ── Condition renseignée ──────────────────────────────────────────────
        if (! empty($data['condition'])) {
            $score += $weights['condition_filled'];     // 10 pts
        }

        // Catégorie : toujours valide (contraint côté API) — on accorde 15 pts fixe
        // si la catégorie est dans la liste autorisée de la friperie
        $catsFriperie = ['vetements','chaussures','sacs','montres','casquettes','accessoires',
                         'homme','femme','enfant','montres_bijoux','sacs_accessoires','traditionnel','sport'];
        if (in_array($data['category'] ?? '', $catsFriperie, true)) {
            $score += 15;
        }

        return min(100, $score);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers internes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Vérifie si le titre est trop générique pour être pertinent.
     */
    private function isTitleGeneric(string $titre): bool
    {
        $titreLower  = strtolower(trim($titre));
        $generics    = config('friperie.generic_titles', []);

        return in_array($titreLower, $generics, true);
    }

    /**
     * Détermine si les flags levés sont bloquants (→ pending_review).
     *
     * Flags bloquants :
     *   - mot interdit détecté
     *   - prix hors plafond
     *   - volume de publication anormal
     *
     * Flags non bloquants (avertissement dans guardian_flags, article visible) :
     *   - (aucun pour l'instant — à étendre en V1.1)
     */
    private function hasBlockingFlags(): bool
    {
        foreach ($this->flags as $flag) {
            if (
                str_starts_with($flag, 'mot_interdit:')  ||
                $flag === 'prix_trop_eleve'               ||
                $flag === 'volume_publication_anormal'    ||
                $flag === 'vendeur_suspect'
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Construit le tableau de résultat retourné à l'Observer.
     */
    private function buildResult(bool $publish): array
    {
        return [
            'friperie_score' => $this->score,
            'status'         => $this->status,
            'guardian_flags' => $this->flags,
            'published_at'   => $publish ? now() : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  API publique — utilitaires pour les autres couches
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Recalcule uniquement le friperie_score d'un article existant.
     * Utilisé quand le vendeur modifie son article (ajout de photos, description...).
     */
    public function recalculateScore(array $data): int
    {
        return $this->calculateQualityScore($data);
    }

    /**
     * Message bienveillant expliquant pourquoi l'article est en review.
     * Affiché dans la réponse API au vendeur.
     */
    public function getVendorMessage(array $flags): string
    {
        if (empty($flags)) {
            return "Ton article est en ligne. Les acheteurs vont adorer ! 🔥";
        }

        // Chercher le flag le plus pertinent à expliquer
        foreach ($flags as $flag) {
            if (str_starts_with($flag, 'mot_interdit:electronique')) {
                return "Cet article semble être un produit électronique. Colways est une marketplace 100% friperie — vêtements et accessoires de seconde main uniquement. Modifie ton annonce ou contacte-nous si c'est une erreur.";
            }
            if (str_starts_with($flag, 'mot_interdit:alimentation')) {
                return "Les produits alimentaires ne sont pas acceptés sur Colways. Notre plateforme est dédiée aux vêtements et accessoires de friperie sénégalaise.";
            }
            if (str_starts_with($flag, 'mot_interdit:services')) {
                return "Les offres de services ne peuvent pas être publiées sur Colways. On est spécialisé dans la vente de vêtements et accessoires de seconde main.";
            }
            if (str_starts_with($flag, 'mot_interdit:boutique_neuve')) {
                return "Colways est réservé à la friperie — articles de seconde main uniquement. Les importations directes d'usine ne correspondent pas à notre identité.";
            }
            if ($flag === 'prix_trop_eleve') {
                return "Le prix de ton article dépasse notre limite. Pour la friperie sénégalaise, nos articles vont jusqu'à 150 000 FCFA. Tu es vendeur professionnel/grossiste ? Crée un compte Étal Pro.";
            }
            if ($flag === 'prix_trop_bas') {
                return "Le prix indiqué est trop bas pour être publié (minimum 200 FCFA). Vérifie le montant saisi.";
            }
            if ($flag === 'volume_publication_anormal') {
                return "Tu as publié beaucoup d'articles en peu de temps ! Pour garantir la qualité du feed, tes nouveaux articles seront vérifiés avant publication. Ça prend moins de 24h.";
            }
            if ($flag === 'vendeur_suspect') {
                return "Ton compte fait l'objet d'une vérification. Tes articles seront publiés après validation de notre équipe.";
            }
        }

        // Message générique bienveillant
        return "Ton article est en cours de vérification par notre équipe. Il sera publié sous 24h. Merci de ta patience ! 🙏";
    }
}
