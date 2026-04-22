<?php

/**
 * Configuration CORS — Colways
 *
 * Autorise les requêtes cross-origin venant :
 * - des environnements de développement locaux (Expo Web, Vite, artisan serve)
 * - du domaine de production colways.sn (Web)
 * - de l'app mobile (pas de CORS — les requêtes natives ne sont pas cross-origin)
 *
 * supports_credentials = true est requis pour Sanctum (cookies de session SPA)
 * combiné avec la liste explicite des origines autorisées (pas de wildcard '*').
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Routes CORS
    |--------------------------------------------------------------------------
    | api/*              — Toutes les routes API
    | sanctum/csrf-cookie — Endpoint CSRF pour les SPA (Sanctum stateful)
    */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
    |--------------------------------------------------------------------------
    | Méthodes HTTP autorisées
    |--------------------------------------------------------------------------
    */
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
    |--------------------------------------------------------------------------
    | Origines autorisées
    |--------------------------------------------------------------------------
    | On liste explicitement chaque origine — pas de wildcard '*' car
    | supports_credentials = true l'interdit (spec CORS).
    */
    'allowed_origins' => [
        // ─── Développement local ──────────────────────────────────────────
        'http://localhost',
        'http://localhost:3000',    // React / Next dev classique
        'http://localhost:8081',    // Expo Web (port par défaut)
        'http://localhost:19006',   // Expo Web (ancien port)
        'http://127.0.0.1',
        'http://127.0.0.1:8000',

        // ─── Production ───────────────────────────────────────────────────
        'https://colways.sn',
        'https://www.colways.sn',
    ],

    /*
    |--------------------------------------------------------------------------
    | Patterns d'origines autorisées (regex)
    |--------------------------------------------------------------------------
    | Autorise tous les ports localhost pour la flexibilité du dev
    | (Expo Web peut démarrer sur un port dynamique)
    */
    'allowed_origins_patterns' => [
        '/^http:\/\/localhost(:\d+)?$/',
        '/^http:\/\/127\.0\.0\.1(:\d+)?$/',
    ],

    /*
    |--------------------------------------------------------------------------
    | En-têtes autorisés dans les requêtes
    |--------------------------------------------------------------------------
    */
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],

    /*
    |--------------------------------------------------------------------------
    | En-têtes exposés dans les réponses
    |--------------------------------------------------------------------------
    */
    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Durée de mise en cache du preflight (secondes)
    |--------------------------------------------------------------------------
    */
    'max_age' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Credentials (cookies, Authorization header)
    |--------------------------------------------------------------------------
    | Requis pour Sanctum SPA (stateful) — l'app mobile utilise Bearer token
    | (stateless) mais ce paramètre ne la dérange pas.
    */
    'supports_credentials' => true,

];
