<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleImage;
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
            'images'   => ['required', 'array', 'min:1', 'max:5'],
            // 'image' seul échoue avec React Native — on utilise mimes explicitement
            'images.*' => ['file', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:5120'],
        ]);

        // Vérifier qu'on ne dépasse pas 5 photos au total
        $nbExistantes = $article->images()->count();
        $nbNouvelles  = count($request->file('images'));

        if ($nbExistantes + $nbNouvelles > 5) {
            return response()->json([
                'message' => "Limite atteinte. Un article peut avoir au maximum 5 photos. Tu en as déjà {$nbExistantes}.",
            ], 422);
        }

        $imagesCreees = [];

        foreach ($request->file('images') as $fichier) {
            // La position détermine l'ordre d'affichage (0 = photo principale)
            $position = $article->images()->count();

            // Upload vers Cloudinary — compressé automatiquement
            $resultat = $cloudinary->upload($fichier, 'colways/articles');

            $image = ArticleImage::create([
                'article_id'    => $article->id,
                'image_url'     => $resultat['url'],
                'cloudinary_id' => $resultat['cloudinary_id'],
                'position'      => $position,
            ]);

            $imagesCreees[] = $image;
        }

        return response()->json([
            'message' => count($imagesCreees) . ' photo(s) ajoutée(s).',
            'images'  => $imagesCreees,
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
