<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiController extends Controller
{
    /**
     * Analyse une photo d'article via Claude 3 Haiku (Anthropic).
     *
     * Reçoit une image, la convertit en base64, l'envoie à l'API Anthropic
     * avec un prompt copywriter sénégalais, et renvoie un JSON propre
     * avec : titre, categorie, description.
     *
     * POST /api/articles/analyze-image
     */
    public function analyzeImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,heic', 'max:5120'],
        ]);

        $apiKey = trim(config('services.anthropic.api_key', ''));

        if (empty($apiKey)) {
            Log::warning('[Claude] ANTHROPIC_API_KEY absent du .env');
            return response()->json(['message' => 'Service IA non configuré.'], 503);
        }

        // ── Conversion image → base64 ─────────────────────────────────────────
        $file     = $request->file('image');
        $mimeType = $file->getMimeType() ?? 'image/jpeg';
        // Anthropic accepte uniquement : image/jpeg, image/png, image/gif, image/webp
        $supportedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $supportedMimes)) {
            $mimeType = 'image/jpeg';
        }
        $base64 = base64_encode(file_get_contents($file->getRealPath()));

        // ── Prompt copywriter Galsen ──────────────────────────────────────────
        $prompt = <<<PROMPT
Tu es un copywriter e-commerce d'élite et un expert en psychologie du consommateur au Sénégal. Analyse cette photo d'article. Ton objectif est de déclencher un achat coup de cœur.
Règles de rédaction :
1. Projection (Le plus important) : Ne te contente pas de décrire le produit. Fais imaginer à l'acheteur comment il se sentira en le portant. Projette-le dans un contexte local (ex: une soirée chic aux Almadies, la chaleur de Dakar, une fête de Korité ou de Tabaski, une journée de travail relax).
2. Taille SEO : La description doit faire entre 300 et 450 caractères maximum (c'est ce que les algorithmes préfèrent).
3. Ton : Chaleureux, convaincant, "Galsen", très professionnel, avec 2 ou 3 emojis bien choisis.
4. Catégorie : choisis UNIQUEMENT parmi ces valeurs exactes : vetements, chaussures, sacs, montres, casquettes, accessoires.
Renvoie UNIQUEMENT un objet JSON valide (sans balises markdown, sans texte autour) avec les clés : titre (accrocheur, max 50 caractères), categorie (une des valeurs listées), et description (le texte persuasif).
PROMPT;

        // ── Appel API Anthropic (Claude 3 Haiku) ─────────────────────────────
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => 'claude-3-haiku-20240307',
                    'max_tokens' => 512,
                    'messages'   => [
                        [
                            'role'    => 'user',
                            'content' => [
                                [
                                    'type'   => 'image',
                                    'source' => [
                                        'type'       => 'base64',
                                        'media_type' => $mimeType,
                                        'data'       => $base64,
                                    ],
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                ]);

            Log::info('[Claude] Status : ' . $response->status());

            if (!$response->successful()) {
                Log::warning('[Claude] Erreur HTTP ' . $response->status() . ' — ' . substr($response->body(), 0, 300));
                return response()->json(['message' => 'Erreur de l\'IA. Réessaie.'], 502);
            }

            // ── Extraction du texte généré ────────────────────────────────────
            $body = $response->json();
            $text = $body['content'][0]['text'] ?? null;

            if (!$text) {
                Log::warning('[Claude] Réponse vide ou structure inattendue : ' . json_encode($body));
                return response()->json(['message' => 'Réponse IA invalide. Réessaie.'], 502);
            }

            // ── Nettoyage — retire les balises markdown si présentes ──────────
            $text = preg_replace('/^```json\s*/i', '', trim($text));
            $text = preg_replace('/\s*```$/i',     '', $text);
            $text = trim($text);

            $parsed = json_decode($text, true);

            if (!$parsed || !isset($parsed['titre'], $parsed['categorie'], $parsed['description'])) {
                Log::warning('[Claude] JSON invalide reçu : ' . $text);
                return response()->json(['message' => 'Format IA invalide. Réessaie.'], 502);
            }

            // ── Validation catégorie ──────────────────────────────────────────
            $categoriesValides = ['vetements', 'chaussures', 'sacs', 'montres', 'casquettes', 'accessoires'];
            if (!in_array($parsed['categorie'], $categoriesValides)) {
                $parsed['categorie'] = 'vetements'; // fallback sûr
            }

            Log::info('[Claude] ✅ Succès — titre: ' . $parsed['titre']);

            return response()->json([
                'titre'       => $parsed['titre'],
                'categorie'   => $parsed['categorie'],
                'description' => $parsed['description'],
            ]);

        } catch (\Exception $e) {
            Log::warning('[Claude] Exception : ' . $e->getMessage());
            return response()->json(['message' => 'Erreur réseau IA. Réessaie.'], 503);
        }
    }
}
