<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminShopController extends Controller
{
    /**
     * Attribue le badge "Vendeur de Colobane ✓" à un étal.
     *
     * C'est la distinction officielle Colways — attribuée manuellement en V1
     * après vérification que le vendeur est bien basé à Colobane.
     * Affiché en Or Colways (#D97706) dans l'app.
     */
    public function verify(Request $request, Shop $shop): JsonResponse
    {
        if ($shop->is_colobane_verified) {
            return response()->json([
                'message' => 'Cet étal est déjà un Vendeur de Colobane ✓ vérifié.',
            ], 422);
        }

        $shop->update([
            'is_colobane_verified'  => true,
            'colobane_verified_at'  => now(),
        ]);

        // Notification email au vendeur
        $this->notifierVendeur($shop);

        // Invalider le cache de la carte des quartiers
        \Illuminate\Support\Facades\Cache::forget('map_quartiers');

        return response()->json([
            'message' => "Badge \"Vendeur de Colobane ✓\" attribué à {$shop->shop_name}.",
            'shop'    => $shop->fresh(),
        ]);
    }

    /**
     * Statistiques globales Colways pour le dashboard admin.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'users'            => User::count(),
            'sellers'          => User::where('role', 'seller')->count(),
            'articles_actifs'  => \App\Models\Article::where('status', 'available')->count(),
            'articles_vendus'  => \App\Models\Article::where('status', 'sold')->count(),
            'boosts_pending'   => \App\Models\Boost::where('payment_status', 'pending')->count(),
            'boosts_confirmes' => \App\Models\Boost::where('payment_status', 'confirmed')->count(),
            'revenus_fcfa'     => \App\Models\Boost::where('payment_status', 'confirmed')->sum('amount_fcfa'),
            'reports_pending'  => \App\Models\Report::where('status', 'pending')->count(),
            'badge_colobane'   => Shop::where('is_colobane_verified', true)->count(),
        ]);
    }

    /**
     * Envoie un email de félicitations au vendeur qui reçoit le badge.
     */
    private function notifierVendeur(Shop $shop): void
    {
        try {
            $email = $shop->user->email ?? null;
            if (! $email) return;

            Mail::raw(
                "Félicitations {$shop->user->name} !\n\n" .
                "Ton étal \"{$shop->shop_name}\" est maintenant un Vendeur de Colobane ✓ vérifié sur Colways.\n\n" .
                "Le badge doré apparaît désormais sur toutes tes annonces.\n\n" .
                "— L'équipe Colways",
                fn ($m) => $m->to($email)->subject('🏆 Tu es maintenant Vendeur de Colobane ✓ sur Colways !')
            );
        } catch (\Exception $e) {
            // Silencieux si email non configuré
        }
    }
}
