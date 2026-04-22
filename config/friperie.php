<?php

/**
 * ─────────────────────────────────────────────────────────────────────────────
 *  Colways — Configuration du Gardien Friperie
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  Ce fichier centralise TOUS les paramètres qui définissent l'identité de
 *  Colways : qu'est-ce qui est "de la friperie" et qu'est-ce qui ne l'est pas.
 *
 *  Toute modification ici impacte immédiatement le comportement du
 *  FriperieGuardianService, sans toucher au code métier.
 *
 *  Accessible via config('friperie.xxx').
 */

return [

    // ═══════════════════════════════════════════════════════════════════════
    //  1. MOTS INTERDITS — Par catégorie thématique
    //  Si un de ces mots apparaît dans le titre OU la description,
    //  l'article est flagué et passe en pending_review.
    // ═══════════════════════════════════════════════════════════════════════

    'banned_keywords' => [

        // ── Électronique / Téléphonie ────────────────────────────────────────
        'electronique' => [
            'iphone', 'samsung galaxy', 'xiaomi', 'huawei', 'oppo', 'tecno',
            'infinix', 'itel', 'smartphone', 'telephone portable', 'téléphone portable',
            'ordinateur portable', 'laptop', 'macbook', 'pc gamer', 'tablette',
            'ipad', 'écouteurs bluetooth', 'airpods', 'playstation', 'xbox',
            'télévision', 'smart tv', 'frigo', 'réfrigérateur', 'climatiseur',
            'machine à laver', 'ventilateur',
        ],

        // ── Alimentation ────────────────────────────────────────────────────
        'alimentation' => [
            'poulet', 'riz au poisson', 'thiéboudienne', 'thiébou', 'dibiterie',
            'restaurant', 'livraison repas', 'traiteur', 'épicerie', 'boisson',
            'jus naturel', 'fruits frais', 'légumes frais',
        ],

        // ── Services (non-produit) ──────────────────────────────────────────
        'services' => [
            'coiffure à domicile', 'couture sur commande', 'prestation',
            'livraison courses', 'cours particulier', 'formation payante',
            'lavage voiture', 'nettoyage maison', 'déménagement', 'taxi',
            'réparation', 'dépannage',
        ],

        // ── Immobilier ──────────────────────────────────────────────────────
        'immobilier' => [
            'location maison', 'location appartement', 'terrain à vendre',
            'villa à louer', 'chambre à louer', 'bureau à louer',
        ],

        // ── Véhicules ───────────────────────────────────────────────────────
        'vehicules' => [
            'voiture', 'moto à vendre', 'scooter', 'vélo électrique',
            'pièce auto', 'pneu voiture',
        ],

        // ── Signaux de "boutique neuve" (pas de la friperie) ────────────────
        'boutique_neuve' => [
            'direct usine', 'prix usine', 'livraison depuis dubai',
            'livraison depuis turquie', 'importation directe',
            'garantie constructeur', 'produit officiel', 'grossiste en ligne',
            'revendeur officiel',
        ],

        // ── Arnaques classiques ─────────────────────────────────────────────
        'arnaques' => [
            'gagner argent facile', 'investissement rapide', 'crypto monnaie',
            'bitcoin', 'forex trading', 'mlm', 'chaîne pyramidale',
            'marabout', 'voyance',
        ],

    ],

    // ═══════════════════════════════════════════════════════════════════════
    //  2. FOURCHETTES DE PRIX — Cohérentes avec la friperie sénégalaise
    // ═══════════════════════════════════════════════════════════════════════

    'price_ranges' => [

        // Prix minimum acceptable (en dessous = spam probable)
        'minimum'      => 200,

        // Zone normale — aucun flag
        'normal_min'   => 500,
        'normal_max'   => 75_000,

        // Zone grise — avertissement mais article reste visible
        'suspect_max'  => 150_000,

        // Plafond absolu B2C — au-delà, pending_review obligatoire
        // (les grossistes/B2B ont leur propre flow avec type='grossiste')
        'maximum_b2c'  => 150_000,

        // Plafond absolu B2B (palette) — limite haute
        'maximum_b2b'  => 2_000_000,

    ],

    // ═══════════════════════════════════════════════════════════════════════
    //  3. CRITÈRES DE QUALITÉ — Composition du friperie_score (0 à 100)
    // ═══════════════════════════════════════════════════════════════════════

    'quality_weights' => [

        // Photos — au moins 2 photos = 20 pts, 3+ = 25 pts
        'photos_min_2'       => 15,
        'photos_3_plus'      => 25,

        // Description — un vrai effort vaut 20 points
        'description_30_chars' => 10,
        'description_80_chars' => 20,

        // Titre descriptif (pas juste "article" ou "vêtement")
        'title_10_chars'     => 10,
        'title_non_generic'  => 10,  // bonus si pas un mot générique

        // Informations structurées remplies
        'condition_filled'   => 10,  // déjà contraint mais mesuré
        'price_in_range'     => 10,  // prix dans la zone normale

    ],

    // Score minimum pour apparaître dans le feed (en dessous = invisible)
    'visibility_threshold' => 40,

    // Titres trop génériques à pénaliser
    'generic_titles' => [
        'article', 'vêtement', 'vetement', 'chaussure', 'sac', 'montre',
        'accessoire', 'truc', 'chose', 'vente', 'à vendre', 'a vendre',
    ],

    // ═══════════════════════════════════════════════════════════════════════
    //  4. RÈGLES COMMUNAUTAIRES — Signalements
    // ═══════════════════════════════════════════════════════════════════════

    'reports' => [

        // Nombre de signalements "pas_de_la_friperie" pour auto-suspend
        'suspend_threshold_identity' => 3,

        // Nombre de signalements "arnaque" pour auto-suspend
        'suspend_threshold_scam'     => 2,

        // Rate limit par IP (déjà appliqué via throttle dans les routes)
        'rate_limit_per_hour'        => 3,

    ],

    // ═══════════════════════════════════════════════════════════════════════
    //  5. CONFIANCE VENDEUR — Promotions/rétrogradations automatiques
    // ═══════════════════════════════════════════════════════════════════════

    'trust' => [

        // Nombre d'articles approuvés pour passer en 'trusted'
        'promote_to_trusted_after' => 30,

        // Nombre d'articles rejetés pour passer en 'flagged'
        'flag_after_rejections'    => 2,

        // Impact du trust_level sur le flow de publication :
        //   new     → analyse automatique, publication directe si score >= 40
        //   trusted → publication directe sans review (score calculé mais pas bloquant)
        //   flagged → TOUS les articles passent en pending_review manuelle
    ],

    // ═══════════════════════════════════════════════════════════════════════
    //  6. ALGORITHME DE VISIBILITÉ — Pondération des 5 piliers
    //  Ces valeurs sont utilisées dans Article::scopeWeightedSort()
    // ═══════════════════════════════════════════════════════════════════════

    'algorithm' => [

        // ── Pilier TIER (tier vendeur payant) ───────────────────────────────
        'tier_elite_pts'      => 60,   // réduit de 100 à 60 pour équité
        'tier_discovery_pts'  => 30,   // réduit de 50 à 30

        // ── Pilier CONFIANCE (mérité, non achetable) ────────────────────────
        'colobane_verified_pts'  => 25,
        'verified_seller_pts'    => 15,

        // ── Pilier MÉRITE (qualité + engagement) ────────────────────────────
        'quality_multiplier'     => 0.3,  // friperie_score * 0.3 → max 30 pts
        'engagement_clicks_pts'  => 15,   // si whatsapp_clicks / views > 5%
        'engagement_shares_pts'  => 10,   // si share_count > 0

        // ── Pilier DÉCOUVERTE (démocratisation) ─────────────────────────────
        'new_seller_boost_days'  => 7,    // bonus 7 premiers jours
        'new_seller_boost_pts'   => 20,
        'freshness_max_pts'      => 30,   // décroît linéairement sur 48h

        // ── Pilier AMPLIFICATION (boost payant) ─────────────────────────────
        'boost_base_pts'         => 20,
        'boost_quality_threshold'=> 70,   // score > 70 = boost à 100%
        'boost_low_quality_mult' => 0.5,  // score < 40 = boost divisé par 2

        // Cap sur les boosts visibles simultanément (anti-saturation)
        'max_boosted_per_page'   => 4,
    ],

];
