<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur Colways.
     *
     * Valide le numéro WhatsApp au format sénégalais (+221XXXXXXXXX).
     * Crée un token Sanctum et retourne les données utilisateur.
     * Message de bienvenue : "Bienvenue sur Colways 🔥"
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // Création de l'utilisateur — mot de passe hashé automatiquement via cast
        $user = User::create([
            'name'             => $request->name,
            'phone'            => $request->phone,
            'whatsapp_number'  => $request->whatsapp_number,
            'whatsapp_verified' => false, // Vérification manuelle en V1
            'password'         => $request->password,
            'role'             => 'buyer', // Rôle par défaut — devient 'seller' à la création d'étal
        ]);

        // Génération du token Sanctum
        $token = $user->createToken('colways_token')->plainTextToken;

        return response()->json([
            'message' => 'Bienvenue sur Colways 🔥',
            'user'    => $user->load(['shop' => function($q) {
                $q->withCount('articles');
            }]),
            'token'   => $token,
        ], 201);
    }

    /**
     * Connexion d'un utilisateur existant.
     *
     * Identifiant : numéro de téléphone (pas email).
     * Retourne un nouveau token Sanctum à chaque connexion.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'phone'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Recherche de l'utilisateur par numéro de téléphone
        $user = User::where('phone', $request->phone)->first();

        // Vérification du mot de passe
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Numéro ou mot de passe incorrect.'],
            ]);
        }

        // Génération d'un nouveau token Sanctum
        $token = $user->createToken('colways_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie.',
            'user'    => $user->load(['shop' => function($q) {
                $q->withCount('articles');
            }]),
            'token'   => $token,
        ]);
    }

    /**
     * Connexion via Google One Tap.
     *
     * Reçoit le token Google ID depuis le frontend.
     * Vérifie le token auprès de Google tokeninfo endpoint.
     * Crée ou retrouve l'utilisateur par email Google.
     * Retourne un token Sanctum.
     *
     * Note : Pour la production, utiliser la bibliothèque officielle
     * google/apiclient ou firebase/php-jwt pour valider la signature.
     */
    public function googleLogin(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        try {
            // Vérification du token Google via l'endpoint public
            $response = Http::timeout(10)
                ->get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $request->token,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Token Google invalide.',
                ], 401);
            }

            $googleData = $response->json();

            // Vérifier que le token est destiné à notre app (Web, iOS ou Android)
            $allowedClients = [
                config('services.google.client_id_web')     ?? env('GOOGLE_CLIENT_ID_WEB'),
                config('services.google.client_id_ios')     ?? env('GOOGLE_CLIENT_ID_IOS'),
                config('services.google.client_id_android') ?? env('GOOGLE_CLIENT_ID_ANDROID'),
            ];

            if (!in_array($googleData['aud'], array_filter($allowedClients))) {
                return response()->json([
                    'message' => 'Token Google non autorisé.',
                ], 401);
            }

            $googleId = $googleData['sub'];
            $email    = $googleData['email'] ?? null;
            $name     = $googleData['name']  ?? ($googleData['given_name'] ?? 'Utilisateur Colways');

            // Retrouver ou créer l'utilisateur par google_id ou email
            $user = User::where('google_id', $googleId)->first()
                 ?? ($email ? User::where('email', $email)->first() : null);

            if (! $user) {
                // Nouvel utilisateur Google — création
                $user = User::create([
                    'name'      => $name,
                    'email'     => $email,
                    'google_id' => $googleId,
                    'password'  => Hash::make(str()->random(32)), // Mot de passe aléatoire inutilisable
                    'role'      => 'buyer',
                ]);
            } else {
                // Mise à jour du google_id si l'utilisateur existait par email
                if (! $user->google_id) {
                    $user->update(['google_id' => $googleId]);
                }
            }

            $token = $user->createToken('colways_google_token')->plainTextToken;

            return response()->json([
                'message' => 'Connexion Google réussie. Bienvenue ' . $user->name . ' 🎉',
                'user'    => $user->load('shop'),
                'token'   => $token,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la vérification Google.',
            ], 500);
        }
    }

    /**
     * Sauvegarde les préférences recueillies pendant l'Onboarding.
     *
     * Stocke les réponses du questionnaire dans le profil utilisateur.
     * Ces données permettront de personnaliser le feed ultérieurement.
     */
    public function saveOnboardingData(Request $request): JsonResponse
    {
        $request->validate([
            'search_type'  => ['nullable', 'string', 'in:unique,wholesale,deals'],
            'categories'   => ['nullable', 'array'],
            'categories.*' => ['string'],
            'want_to_sell' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        // Stockage dans la colonne JSON preferences du modèle User
        // (à ajouter en migration si elle n'existe pas encore)
        $current = $user->preferences ?? [];

        $user->update([
            'preferences' => array_merge($current, [
                'search_type'  => $request->search_type,
                'categories'   => $request->categories ?? [],
                'want_to_sell' => $request->want_to_sell,
                'onboarded_at' => now()->toIso8601String(),
            ]),
        ]);

        return response()->json([
            'message' => 'Préférences sauvegardées.',
            'preferences' => $user->fresh()->preferences,
        ]);
    }

    /**
     * Déconnexion de l'utilisateur.
     *
     * Révoque uniquement le token actif (pas tous les tokens).
     * Cela permet à l'utilisateur de rester connecté sur d'autres appareils.
     */
    public function logout(Request $request): JsonResponse
    {
        // Suppression du token courant uniquement
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    /**
     * Retourne le profil de l'utilisateur connecté.
     *
     * Charge l'étal du vendeur si il existe — évite un second appel API côté app.
     */
    public function me(Request $request): JsonResponse
    {
        // Chargement eager de l'étal avec le nombre d'articles pour le Dashboard KPI
        $user = $request->user()->load(['shop' => function($q) {
            $q->withCount('articles');
        }]);

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Met à jour le profil de l'utilisateur connecté.
     *
     * Gère le nom, numéro WhatsApp et le mot de passe optionnel.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        
        $data = $request->validated();
        
        // Si un mot de passe est fourni, il sera hashé par le cast du modèle User
        if (isset($data['password']) && empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user'    => $user->fresh('shop'),
        ]);
    }

    /**
     * Changement de mot de passe sécurisé.
     * Vérifie l'ancien mot de passe avant d'appliquer le nouveau.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Le mot de passe actuel est incorrect.'], 422);
        }

        $user->update(['password' => \Illuminate\Support\Facades\Hash::make($request->password)]);

        return response()->json(['message' => 'Mot de passe modifié avec succès.']);
    }

    /**
     * Upload de la photo de profil utilisateur (Avatar).
     */
    public function uploadPhoto(Request $request, \App\Services\CloudinaryService $cloudinary): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:5120', // 5MB max
        ]);

        $user = $request->user();

        try {
            // Upload vers Cloudinary
            $result = $cloudinary->upload($request->file('image'), 'colways/avatars');
            
            $user->update([
                'profile_photo_url' => $result['url']
            ]);

            return response()->json([
                'message' => 'Photo mise à jour.',
                'user'    => $user->fresh('shop'),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Upload Profile Error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 500);
        }
    }
}
