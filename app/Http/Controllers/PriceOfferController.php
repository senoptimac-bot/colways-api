<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\PriceOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PriceOfferController extends Controller
{
    /**
     * Liste des offres reçues par le vendeur (mon étal) 
     * ou envoyées par l'acheteur.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Par défaut, on retourne les offres REÇUES (pour le Dashboard vendeur)
        $query = PriceOffer::with(['article', 'buyer:id,name,phone'])
            ->whereHas('article', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        // Optionnel : filtrer par les offres ENVOYÉES (pour le suivi acheteur)
        if ($request->query('type') === 'sent') {
            $query = PriceOffer::with(['article.shop', 'article.images'])
                ->where('buyer_id', $user->id);
        }

        $offers = $query->orderByDesc('created_at')->get();

        return response()->json([
            'offers' => $offers,
        ]);
    }

    /**
     * Envoyer une offre sur un article (Acheteur).
     */
    public function store(Request $request, Article $article): JsonResponse
    {
        $request->validate([
            'offered_price' => ['required', 'integer', 'min:100'],
            'message'       => ['nullable', 'string', 'max:500'],
        ]);

        if ($article->user_id === Auth::id()) {
            return response()->json(['message' => 'Tu ne peux pas négocier ton propre article.'], 422);
        }

        $offer = PriceOffer::create([
            'article_id'    => $article->id,
            'buyer_id'      => Auth::id(),
            'offered_price' => $request->offered_price,
            'message'       => $request->message,
            'status'        => 'pending',
        ]);

        return response()->json([
            'message' => 'Offre envoyée ! Le vendeur va l\'étudier.',
            'offer'   => $offer,
        ], 201);
    }

    /**
     * Accepter ou refuser une offre (Vendeur).
     */
    public function update(Request $request, PriceOffer $offer): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:accepted,refused'],
        ]);

        // Vérifier que l'article appartient à l'utilisateur connecté
        if ($offer->article->user_id !== Auth::id()) {
            return response()->json(['message' => 'Action non autorisée.'], 403);
        }

        $offer->update([
            'status' => $request->status,
        ]);

        $msg = $request->status === 'accepted' 
            ? 'Offre acceptée ✓ Contacte l\'acheteur sur WhatsApp !' 
            : 'Offre refusée.';

        return response()->json([
            'message' => $msg,
            'offer'   => $offer->fresh(['buyer']),
        ]);
    }
}
