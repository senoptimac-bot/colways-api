<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\CreditTransaction;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    // CloudinaryService injecté par méthode pour éviter l'erreur de config au démarrage

    /**
     * Upload 1 à 5 photos pour un article.
     *
     * Règles :
     * - Maximum 5 photos par article au total
     * - La première photo uploadée devient la photo principale (position = 0)
     * - Les photos suivantes prennent les positions 1, 2, 3, 4
     * - Taille max 5 Mo par photo AVANT compression Cloudinary
     * - Cloudinary compresse automatiquement à < 1 Mo (optimisé 3G)
     *
     * Vérification ownership obligatoire.
     */
    public function store(Request $request, Article $article, CloudinaryService $cloudinary): JsonResponse
    {
        // Vérification ownership
        if (! $article->ownedBy($request->user()->id)) {
            return response()->json(['message' => 'Cet article ne t\'appartient pas.'], 403);
        }

        $request->validate([
            'images'             => ['required', 'array', 'min:1', 'max:5'],
            // 'image' seul échoue avec React Native — on utilise mimes explicitement
            'images.*'           => ['file', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:5120'],
            // Flag optionnel envoyé par le frontend quand le Détourage Premium est activé
            'background_removal' => ['sometimes', 'boolean'],
        ]);

        // Vérifier qu'on ne dépasse pas 5 photos au total
        $nbExistantes = $article->images()->count();
        $nbNouvelles  = count($request->file('images'));

        if ($nbExistantes + $nbNouvelles > 5) {
            return response()->json([
                'message' => "Limite atteinte. Un article peut avoir au maximum 5 photos. Tu en as déjà {$nbExistantes}.",
            ], 422);
        }

        // ── Détourage Premium — vérification & débit wallet ───────────────────
        $detourage = filter_var($request->input('background_removal', false), FILTER_VALIDATE_BOOLEAN);
        $user      = $request->user();

        if ($detourage) {
            // ── 1. Quota mensuel global (limite Remove.bg : 50 images/mois) ──
            // On bloque à 45 pour garder 5 de marge en cas d'erreurs réessayées.
            $QUOTA_MENSUEL = 45;
            $utiliseCeMois = CreditTransaction::where('reason', 'detourage_premium')
                ->whereYear('created_at',  now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();

            if ($utiliseCeMois >= $QUOTA_MENSUEL) {
                return response()->json([
                    'message' => 'Le Détourage Premium est temporairement indisponible ce mois-ci. Réessaie le mois prochain ou contacte le support.',
                    'error'   => 'quota_mensuel_atteint',
                    'quota'   => $QUOTA_MENSUEL,
                    'utilise' => $utiliseCeMois,
                ], 422);
            }

            // ── 2. Vérification solde ────────────────────────────────────────
            $wallet = $user->getOrCreateWallet();

            if (! $wallet->hasEnough(2)) {
                return response()->json([
                    'message' => 'Solde insuffisant. Il te faut 2 Jetons pour le Détourage Premium.',
                    'error'   => 'insufficient_jetons',
                    'balance' => $wallet->credits,
                ], 422);
            }

            // ── 3. Débit forfaitaire : 2 Jetons pour l'article entier ────────
            $wallet->spendCredits(2, 'detourage_premium', 'article_' . $article->id);
        }

        // ── Upload des photos ─────────────────────────────────────────────────
        $imagesCreees = [];

        foreach ($request->file('images') as $fichier) {
            // La position détermine l'ordre d'affichage (0 = photo principale)
            $position = $article->images()->count();

            // Choix de la méthode d'upload selon le mode
            $resultat = $detourage
                ? $cloudinary->uploadWithDetourage($fichier, 'colways/articles')
                : $cloudinary->upload($fichier, 'colways/articles');

            $image = ArticleImage::create([
                'article_id'    => $article->id,
                'image_url'     => $resultat['url'],
                'cloudinary_id' => $resultat['cloudinary_id'],
                'position'      => $position,
            ]);

            $imagesCreees[] = $image;
        }

        $nb = count($imagesCreees);

        return response()->json([
            'message'                    => "{$nb} photo(s) ajoutée(s)." . ($detourage ? ' ✨ Détourage Premium appliqué.' : ''),
            'images'                     => $imagesCreees,
            'background_removal_applied' => $detourage,
        ], 201);
    }

    /**
     * Supprime une photo d'un article.
     *
     * Suppression en deux étapes :
     * 1. Suppression sur Cloudinary (libère l'espace de stockage)
     * 2. Suppression en base de données
     *
     * Si la photo principale (position 0) est supprimée,
     * la photo suivante prend automatiquement sa place.
     *
     * Vérification ownership obligatoire.
     */
    public function destroy(Request $request, Article $article, ArticleImage $image, CloudinaryService $cloudinary): JsonResponse
    {
        // Vérification ownership de l'article (pas juste de l'image)
        if (! $article->ownedBy($request->user()->id)) {
            return response()->json(['message' => 'Cet article ne t\'appartient pas.'], 403);
        }

        // Vérifier que l'image appartient bien à cet article
        if ($image->article_id !== $article->id) {
            return response()->json(['message' => 'Cette image n\'appartient pas à cet article.'], 422);
        }

        $etaitPrincipale = ($image->position === 0);

        // 1. Supprimer de Cloudinary
        $cloudinary->delete($image->cloudinary_id);

        // 2. Supprimer de la base de données
        $image->delete();

        // Si on a supprimé la photo principale, la suivante prend sa place
        if ($etaitPrincipale) {
            $premiereSuivante = $article->images()->orderBy('position')->first();
            if ($premiereSuivante) {
                $premiereSuivante->update(['position' => 0]);
            }
        }

        return response()->json([
            'message' => 'Photo supprimée.',
        ]);
    }
}
