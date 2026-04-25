<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudinary — Stockage images, audio, vidéo
    |--------------------------------------------------------------------------
    | Les clés sont dans .env — jamais directement ici.
    | Compte à créer sur cloudinary.com (plan gratuit suffisant pour démarrer).
    */
    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key'    => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PayDunya — Passerelle de paiement sénégalaise
    |--------------------------------------------------------------------------
    | Supporte : Wave, Orange Money, Free Money, carte bancaire.
    | Compte à créer sur paydunya.com
    |
    | Tant que ces clés sont vides, le BoostController utilise le paiement
    | manuel (instructions Wave/OM affichées dans l'app).
    | Quand tu reçois tes clés PayDunya, remplis .env et le mode automatique
    | s'active instantanément — sans changer une ligne de code.
    */
    'paydunya' => [
        'master_key'  => env('PAYDUNYA_MASTER_KEY', ''),
        'private_key' => env('PAYDUNYA_PRIVATE_KEY', ''),
        'token'       => env('PAYDUNYA_TOKEN', ''),
        'mode'        => env('PAYDUNYA_MODE', 'test'), // 'test' ou 'live'
    ],

    /*
    |--------------------------------------------------------------------------
    | Remove.bg — Détourage IA instantané (fond blanc sur photos d'articles)
    |--------------------------------------------------------------------------
    | API gratuite : 50 images/mois. Clé à récupérer sur remove.bg/api
    | Ajouter dans .env Hostinger : REMOVEBG_API_KEY=votre_clé
    */
    'removebg' => [
        'api_key' => env('REMOVEBG_API_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Gemini — IA analyse photo (désactivé, remplacé par Claude)
    |--------------------------------------------------------------------------
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic Claude — IA analyse photo pour auto-remplissage articles
    |--------------------------------------------------------------------------
    | Modèle : claude-3-haiku-20240307 (vision multimodale, rapide, ~$0.001/analyse)
    | Clé à créer sur : console.anthropic.com
    | Ajouter dans .env : ANTHROPIC_API_KEY=sk-ant-votre_clé
    */
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth — Vérification des tokens ID côté backend
    |--------------------------------------------------------------------------
    | Ces IDs doivent correspondre exactement aux OAuth Client IDs configurés
    | dans Google Cloud Console > APIs & Services > Credentials.
    */
    'google' => [
        'client_id_web'     => env('GOOGLE_CLIENT_ID_WEB'),
        'client_id_ios'     => env('GOOGLE_CLIENT_ID_IOS'),
        'client_id_android' => env('GOOGLE_CLIENT_ID_ANDROID'),
    ],


];
