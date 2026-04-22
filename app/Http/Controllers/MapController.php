<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MapController extends Controller
{
    /**
     * Coordonnées géographiques fixes des quartiers pour la vue radar.
     */
    private const COORDONNEES = [
        'colobane'   => ['lat' => 14.6892, 'lng' => -17.4467, 'label' => 'Colobane',   'couleur' => '#D97706'],
        'hlm'        => ['lat' => 14.7123, 'lng' => -17.4612, 'label' => 'HLM',         'couleur' => '#2563EB'],
        'medina'     => ['lat' => 14.6950, 'lng' => -17.4580, 'label' => 'Médina',      'couleur' => '#10B981'],
        'plateau'    => ['lat' => 14.6737, 'lng' => -17.4441, 'label' => 'Plateau',     'couleur' => '#8B5CF6'],
        'grand_yoff' => ['lat' => 14.7340, 'lng' => -17.4580, 'label' => 'Grand-Yoff',  'couleur' => '#EF4444'],
        'parcelles'  => ['lat' => 14.7680, 'lng' => -17.4420, 'label' => 'Parcelles',   'couleur' => '#F59E0B'],
        'pikine'     => ['lat' => 14.7456, 'lng' => -17.3958, 'label' => 'Pikine',      'couleur' => '#EC4899'],
        'guediawaye' => ['lat' => 14.7784, 'lng' => -17.3947, 'label' => 'Guédiawaye',  'couleur' => '#14B8A6'],
        'autre'      => ['lat' => 14.7167, 'lng' => -17.4677, 'label' => 'Autre',       'couleur' => '#6B7280'],
    ];

    /**
     * Statistiques globales par quartier pour les bulles de la carte.
     */
    public function quartiers(): JsonResponse
    {
        $resultat = Cache::remember('map_quartiers_final', 900, function () {
            $stats = Shop::select('quartier')
                ->selectRaw('COUNT(id) as count')
                ->groupBy('quartier')
                ->get()
                ->keyBy('quartier');

            $data = [];
            foreach (self::COORDONNEES as $slug => $infos) {
                $stat = $stats->get($slug);
                $data[] = [
                    'quartier'       => $slug,
                    'label'          => $infos['label'],
                    'sellers_count'  => $stat ? (int) $stat->count : 0,
                    'latitude'       => $infos['lat'],
                    'longitude'      => $infos['lng'],
                    'couleur'        => $infos['couleur'],
                ];
            }
            return (array) $data;
        });

        return response()->json((array) $resultat);
    }

    /**
     * Récupère les positions exactes des étals pour les marqueurs (Pins).
     */
    public function locations(): JsonResponse
    {
        // On sélectionne les colonnes nécessaires (y compris avatar_url pour l'accessor)
        $shops = Shop::select(['id', 'shop_name', 'type', 'description', 'specialties', 'latitude', 'longitude', 'avatar_url'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->withCount('articles') // Utilise le moteur Laravel pour compter les articles dynamiquement
            ->get();

        return response()->json($shops);
    }
}
