<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $article->title }} - Colways</title>

    <!-- Open Graph Meta Tags (Facebook, WhatsApp, LinkedIn, iMessage) -->
    <meta property="og:type" content="product" />
    <meta property="og:title" content="👗 {{ $article->title }} — {{ number_format($article->price, 0, ',', ' ') }} FCFA" />
    <meta property="og:description" content="{{ mb_strimwidth($article->description ?? 'Trouvé sur Colways, la marketplace de friperie numéro 1 au Sénégal.', 0, 100, '...') }} 🛍️" />
    
    @if($article->mainImage)
    <meta property="og:image" content="{{ $article->mainImage->image_url }}" />
    <meta property="og:image:width" content="800" />
    <meta property="og:image:height" content="800" />
    @endif

    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:site_name" content="Colways" />

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="👗 {{ $article->title }} — {{ number_format($article->price, 0, ',', ' ') }} FCFA">
    <meta name="twitter:description" content="{{ mb_strimwidth($article->description ?? 'Trouvé sur Colways, l\'app des friperies.', 0, 100, '...') }}">
    @if($article->mainImage)
    <meta name="twitter:image" content="{{ $article->mainImage->image_url }}">
    @endif

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #059669; /* Theme Emerald Colways */
            color: white;
            text-align: center;
            padding: 50px 20px;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .container h1 { color: #111827; font-size: 20px; margin-bottom: 8px;}
        .container p { color: #6B7280; font-size: 14px; line-height: 1.5; margin-bottom: 24px;}
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #059669;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .btn {
            background-color: #059669;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="loader"></div>
        <h1>Ouverture de l'article...</h1>
        <p>Tu vas être redirigé vers l'application Colways dans un instant pour voir cet article.</p>
        <!-- Fallback si le deep link automatique échoue -->
        <a href="https://colways.sn" class="btn">Télécharger Colways</a>
    </div>

    <!-- Script de redirection automatique (Deep Linking) -->
    <script>
        // Ce code ne s'exécute que pour les vrais utilisateurs physiques (navigateur),
        // WhatsApp et Facebook se contentent de lire les balises <meta> en haut.
        
        window.onload = function() {
            // Le deep link vers l'app Expo/React Native
            var deepLink = "colways://article/{{ $article->id }}";
            var fallback = "https://colways.sn"; // Redirection si l'app n'est pas installée

            // Tente d'ouvrir le deep link (l'application Colways)
            window.location.href = deepLink;

            // Si au bout de 2.5 secondes on est toujours sur cette page web,
            // c'est que l'app n'est pas installée. On redirige vers le site/store.
            setTimeout(function() {
                window.location.href = fallback;
            }, 2500);
        };
    </script>
</body>
</html>
