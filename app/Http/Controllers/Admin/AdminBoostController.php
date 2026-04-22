<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Boost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBoostController extends Controller
{
    /**
     * Liste tous les boosts en attente de confirmation.
     * L'admin vérifie le paiement Wave/OM et confirme manuellement.
     */
    public function pending(): JsonResponse
    {
        $boosts = Boost::where('payment_status', 'pending')
            ->with([
                'article:id,title,status',
                'user:id,name,phone,whatsapp_number',
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'count'  => $boosts->count(),
            'boosts' => $boosts,
        ]);
    }

    /**
     * Confirme un boost après vérification du paiement.
     *
     * Actions :
     *   1. Marquer le boost comme "confirmed"
     *   2. Définir la date de début et de fin (now + duration_hours)
     *   3. Marquer l'article comme "is_boosted = true"
     *   4. Enregistrer quel admin a confirmé
     */
    public function confirm(Request $request, Boost $boost): JsonResponse
    {
        if ($boost->payment_status !== 'pending') {
            return response()->json([
                'message' => 'Ce boost n\'est pas en attente de confirmation.',
            ], 422);
        }

        $maintenant = now();
        $expiration = $maintenant->copy()->addHours($boost->duration_hours);

        // Activer le boost
        $boost->update([
            'payment_status' => 'confirmed',
            'starts_at'      => $maintenant,
            'expires_at'     => $expiration,
            'confirmed_by'   => $request->user()->id,
            'confirmed_at'   => $maintenant,
        ]);

        // Activer le boost sur l'article
        $boost->article->update([
            'is_boosted'       => true,
            'boost_expires_at' => $expiration,
        ]);

        return response()->json([
            'message'    => "Boost activé. L'article sera mis en avant jusqu'au " . $expiration->format('d/m/Y à H:i') . '.',
            'boost'      => $boost->fresh(),
        ]);
    }
}
