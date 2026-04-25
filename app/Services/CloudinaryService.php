<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    private Cloudinary $cloudinary;

    /**
     * Initialise le client Cloudinary avec les clés depuis .env
     * Les clés ne sont JAMAIS dans le code — toujours dans .env
     */
    public function __construct()
    {
        if (config('services.cloudinary.api_secret')) {
            $this->cloudinary = new Cloudinary(
                new Configuration([
                    'cloud' => [
                        'cloud_name' => config('services.cloudinary.cloud_name'),
                        'api_key'    => config('services.cloudinary.api_key'),
                        'api_secret' => config('services.cloudinary.api_secret'),
                    ],
                    'url' => [
                        'secure' => true, // Toujours HTTPS
                    ],
                ])
            );
        }
    }

    /**
     * Upload un fichier (image, audio, vidéo) sur Cloudinary.
     *
     * Dossiers utilisés :
     *   colways/articles/  → photos d'articles
     *   colways/shops/     → avatars et covers d'étals
     *   colways/audio/     → descriptions vocales (V1.1)
     *   colways/video/     → vidéos de démonstration (V1.1)
     *
     * @param UploadedFile $file    Le fichier à uploader
     * @param string       $folder  Le dossier Cloudinary (ex: 'colways/articles')
     * @return array{ url: string, cloudinary_id: string }
     */
    public function upload(UploadedFile $file, string $folder): array
    {
        // Bypass si les clés Cloudinary ne sont pas configurées (Stockage local à la place)
        if (!config('services.cloudinary.api_secret')) {
            $path = $file->store($folder, 'public');
            return [
                'url'           => url('storage/' . $path),
                'cloudinary_id' => 'local_' . $path,
            ];
        }

        $resultat = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder'         => $folder,
                'resource_type'  => 'auto', // Détecte automatiquement image/audio/vidéo
                'transformation' => $this->getTransformation($folder),
            ]
        );

        return [
            'url'           => $resultat['secure_url'],   // URL HTTPS publique
            'cloudinary_id' => $resultat['public_id'],    // ID pour la suppression future
        ];
    }

    /**
     * Upload une image avec Détourage IA via Remove.bg (fond blanc pur).
     *
     * Flow :
     *   1. Envoie l'image à l'API Remove.bg → JPG avec fond blanc en 2-3s
     *   2. Sauvegarde temporaire sur le serveur
     *   3. Upload le fichier traité vers Cloudinary
     *   4. Supprime le fichier temporaire
     *
     * Fallback : si Remove.bg échoue (quota, erreur réseau, clé manquante),
     *            on fait un upload Cloudinary classique sans détourage.
     *
     * @param UploadedFile $file    Le fichier image original
     * @param string       $folder  Dossier Cloudinary (ex: 'colways/articles')
     * @return array{ url: string, cloudinary_id: string }
     */
    public function uploadWithDetourage(UploadedFile $file, string $folder): array
    {
        // Bypass local (développement sans clés Cloudinary)
        if (!config('services.cloudinary.api_secret')) {
            $path = $file->store($folder, 'public');
            return [
                'url'           => url('storage/' . $path),
                'cloudinary_id' => 'local_' . $path,
            ];
        }

        $apiKey = config('services.removebg.api_key');

        // ── Étape 1 : Détourage via Remove.bg ───────────────────────────────
        if ($apiKey) {
            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-Api-Key' => $apiKey,
                ])
                ->attach('image_file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post('https://api.remove.bg/v1.0/removebg', [
                    'size'     => 'auto',
                    'bg_color' => 'ffffff',  // Fond blanc pur
                    'format'   => 'jpg',     // JPG (pas PNG transparent)
                ]);

                if ($response->successful()) {
                    // ── Étape 2 : Fichier temporaire ────────────────────────
                    $tempPath = tempnam(sys_get_temp_dir(), 'colways_bg_') . '.jpg';
                    file_put_contents($tempPath, $response->body());

                    // ── Étape 3 : Upload vers Cloudinary ────────────────────
                    $resultat = $this->cloudinary->uploadApi()->upload($tempPath, [
                        'folder'         => $folder,
                        'resource_type'  => 'image',
                        'transformation' => $this->getTransformation($folder),
                    ]);

                    // ── Étape 4 : Nettoyage du fichier temporaire ───────────
                    @unlink($tempPath);

                    return [
                        'url'           => $resultat['secure_url'],
                        'cloudinary_id' => $resultat['public_id'],
                    ];
                }

                \Illuminate\Support\Facades\Log::warning(
                    '[Détourage] Remove.bg erreur HTTP ' . $response->status()
                );

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[Détourage] Remove.bg exception: ' . $e->getMessage());
            }
        } else {
            \Illuminate\Support\Facades\Log::warning('[Détourage] REMOVEBG_API_KEY absent du .env');
        }

        // ── Fallback : upload Cloudinary classique si Remove.bg indisponible ─
        return $this->upload($file, $folder);
    }

    /**
     * Supprime un fichier de Cloudinary via son identifiant.
     * Appelé quand un article est supprimé ou qu'une image est retirée.
     *
     * @param string $cloudinaryId  L'identifiant stocké en base (ex: colways/articles/abc123)
     * @param string $resourceType  'image', 'video' ou 'raw' (audio)
     */
    public function delete(string $cloudinaryId, string $resourceType = 'image'): void
    {
        if (str_starts_with($cloudinaryId, 'local_')) {
            $path = str_replace('local_', '', $cloudinaryId);
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            return;
        }

        if (config('services.cloudinary.api_secret') && !str_starts_with($cloudinaryId, 'mock_')) {
            $this->cloudinary->uploadApi()->destroy($cloudinaryId, [
                'resource_type' => $resourceType,
            ]);
        }
    }

    /**
     * Applique des transformations automatiques selon le type de contenu.
     * Optimise les images pour les réseaux 3G du Sénégal (< 1 Mo).
     */
    private function getTransformation(string $folder): array
    {
        // Photos d'articles — format carré, qualité réduite pour 3G
        if (str_contains($folder, 'articles')) {
            return [
                'width'   => 800,
                'height'  => 800,
                'crop'    => 'limit',    // Ne pas agrandir, seulement réduire
                'quality' => 'auto:good', // Compression automatique optimisée
                'format'  => 'auto',     // WebP si supporté, JPEG sinon
            ];
        }

        // Avatars d'étals — format carré petit
        if (str_contains($folder, 'shops') && str_contains($folder, 'avatar')) {
            return [
                'width'   => 400,
                'height'  => 400,
                'crop'    => 'fill',
                'gravity' => 'face',     // Centré sur le visage si portrait
                'quality' => 'auto:good',
                'format'  => 'auto',
            ];
        }

        // Cover d'étals — format 16:9
        if (str_contains($folder, 'shops') && str_contains($folder, 'cover')) {
            return [
                'width'   => 1200,
                'height'  => 675,
                'crop'    => 'fill',
                'quality' => 'auto:good',
                'format'  => 'auto',
            ];
        }

        // Pas de transformation pour audio/vidéo
        return [];
    }
}
