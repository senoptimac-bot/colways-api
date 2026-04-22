<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Boost;
use App\Services\PaydunyaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoostController extends Controller
{
    /**
     * Tarifs des boosts Colways EN JETONS.
     * B2C (particulier) : 5 / 10 Jetons (équivalent 500 / 1000 FCFA)
     * B2B (grossiste)   : 15 / 25 Jetons
     */
    private const TARIFS = [
        24 => 5,
        48 => 10,
    ];

    private const TARIFS_PALETTE = [
        24 => 15,
        48 => 25,
    ];

    /**
     * Demande de mise en avant d'un article.
     * Le paiement se fait avec les Jetons Colways.
     * Si le solde est suffisant, le boost est activé instantanément.
     */
    public function store(Request $request, PaydunyaService $paydunya): JsonResponse
    {
        $request->validate([
            'article_id'     => ['required', 'integer', 'exists:articles,id'],
            'duration_hours' => ['required', 'in:24,48'],
        ]);

        $article = Article::with('shop:id,type')->findOrFail($request->article_id);

        // Vérification ownership — seul le propriétaire peut booster son article
        if (! $article->ownedBy($request->user()->id)) {
            return response()->json([
                'message' => 'Cet article ne t\'appartient pas.',
            ], 403);
        }

        // Un seul boost actif ou en attente par article
        $boostExistant = Boost::where('article_id', $article->id)
            ->whereIn('payment_status', ['pending', 'confirmed'])
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($boostExistant) {
            return response()->json([
                'message' => 'Cet article a déjà une mise en avant active ou en attente.',
            ], 422);
        }

        $duree      = (int) $request->duration_hours;
        $isPalette  = $article->shop?->type === 'grossiste';
        $tarifs     = $isPalette ? self::TARIFS_PALETTE : self::TARIFS;
        $montantJetons = $tarifs[$duree];

        $user = $request->user();
        $wallet = $user->getOrCreateWallet();

        if ($wallet->credits < $montantJetons) {
            return response()->json([
                'message' => "Solde insuffisant. Il vous manque " . ($montantJetons - $wallet->credits) . " jetons.",
                'error_code' => 'INSUFFICIENT_FUNDS',
            ], 402);
        }

        // Déduire les jetons
        $spent = $wallet->spendCredits($montantJetons, "Boost de {$duree}h pour l'article #{$article->id}", $article->id);
        
        if (!$spent) {
            return response()->json(['message' => 'Erreur lors du paiement par jetons.'], 500);
        }

        // Créer et activer le boost immédiatement
        $now = now();
        $expires = $now->copy()->addHours($duree);

        $boost = Boost::create([
            'article_id'     => $article->id,
            'user_id'        => $user->id,
            'duration_hours' => $duree,
            'amount_fcfa'    => $montantJetons, // On utilise cette colonne pour stocker les Jetons
            'payment_method' => 'manual', // Workaround pour la contrainte ENUM SQLite
            'payment_status' => 'confirmed',
            'starts_at'      => $now,
            'expires_at'     => $expires,
            'confirmed_at'   => $now,
        ]);

        $article->update([
            'is_boosted'      => true,
            'boost_expires_at'=> $expires,
        ]);

        return response()->json([
            'message'      => "Boost activé avec succès !",
            'boost_id'     => $boost->id,
            'amount_jetons'=> $montantJetons,
            'duration'     => "{$duree}h",
        ], 201);
    }

    /**
     * Retourne les tarifs de boost pour un article donné en jetons.
     */
    public function offres(Request $request): JsonResponse
    {
        $request->validate(['article_id' => ['required', 'integer', 'exists:articles,id']]);

        $article   = Article::with('shop:id,type')->findOrFail($request->article_id);
        $isPalette = $article->shop?->type === 'grossiste';
        $tarifs    = $isPalette ? self::TARIFS_PALETTE : self::TARIFS;

        return response()->json([
            'is_palette' => $isPalette,
            'wallet_balance' => $request->user()->getOrCreateWallet()->credits,
            'offres'     => [
                [
                    'id'    => '24h',
                    'duree' => 24,
                    'prix'  => $tarifs[24],
                    'label' => '24 heures',
                    'sous'  => $isPalette
                        ? 'Ta palette en tête du feed grossiste pendant 24h'
                        : 'Ton article en tête du feed pendant 24h',
                    'badge' => null,
                ],
                [
                    'id'    => '48h',
                    'duree' => 48,
                    'prix'  => $tarifs[48],
                    'label' => '48 heures',
                    'sous'  => $isPalette
                        ? 'Visibilité maximale pour les acheteurs B2B — recommandé'
                        : 'Double visibilité — recommandé pour les articles > 5 000 FCFA',
                    'badge' => 'Populaire',
                ],
            ],
        ]);
    }

    /**
     * Flux PayDunya — crée une facture et retourne l'URL de paiement.
     */
    private function storePaydunya(Request $request, Article $article, PaydunyaService $paydunya, int $duree, int $montant, bool $isPalette = false): JsonResponse
    {
        // Créer le boost en base AVANT d'appeler PayDunya
        $boost = Boost::create([
            'article_id'     => $article->id,
            'user_id'        => $request->user()->id,
            'duration_hours' => $duree,
            'amount_fcfa'    => $montant,
            'payment_method' => 'wave', // PayDunya agrège Wave/OM/etc.
            'payment_status' => 'pending',
        ]);

        try {
            $invoice = $paydunya->createInvoice([
                'amount'      => $montant,
                'description' => "Boost article {$duree}h — {$article->title}",
                'reference'   => "BOOST-{$boost->id}",
                'callback_url'=> url("/api/webhooks/paydunya"),
                'return_url'  => url("/api/boosts/{$boost->id}/paid"),
                'cancel_url'  => url("/api/boosts/{$boost->id}/cancelled"),
            ]);

            // Sauvegarder le token PayDunya et l'URL de paiement
            $boost->update([
                'paydunya_token' => $invoice['token'],
                'payment_url'    => $invoice['checkout_url'],
            ]);

            return response()->json([
                'mode'         => 'paydunya',
                'message'      => "Complète ton paiement de {$montant} FCFA sur Wave ou Orange Money.",
                'boost_id'     => $boost->id,
                'amount_fcfa'  => $montant,
                'duration'     => "{$duree}h",
                'payment_url'  => $invoice['checkout_url'], // L'app ouvre cette URL
            ], 201);

        } catch (\RuntimeException $e) {
            // Si PayDunya est en panne → supprimer le boost créé et retourner une erreur
            $boost->delete();
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    /**
     * Flux manuel — retourne les instructions de paiement Wave/OM.
     */
    private function storeManuel(Request $request, Article $article, int $duree, int $montant, bool $isPalette = false): JsonResponse
    {
        $boost = Boost::create([
            'article_id'     => $article->id,
            'user_id'        => $request->user()->id,
            'duration_hours' => $duree,
            'amount_fcfa'    => $montant,
            'payment_method' => 'manual',
            'payment_status' => 'pending',
        ]);

        return response()->json([
            'mode'         => 'manual',
            'message'      => "Demande envoyée ! Envoie {$montant} FCFA pour activer ta mise en avant.",
            'boost_id'     => $boost->id,
            'amount_fcfa'  => $montant,
            'duration'     => "{$duree}h",
            'instructions' => $this->getInstructionsPaiement($montant, $boost->id),
        ], 201);
    }

    /**
     * Vérifie le statut d'un boost (polling depuis l'app après paiement PayDunya).
     * L'app appelle cette route toutes les 5s après avoir ouvert le lien de paiement.
     */
    public function checkStatus(Boost $boost, PaydunyaService $paydunya): JsonResponse
    {
        // Vérification ownership
        if ($boost->user_id !== auth()->id()) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        // Mode manuel — statut déterminé par l'admin
        if (! $boost->paydunya_token) {
            return response()->json([
                'status'  => $boost->payment_status,
                'is_paid' => $boost->payment_status === 'confirmed',
            ]);
        }

        // Mode PayDunya — vérifier en temps réel
        $result = $paydunya->checkStatus($boost->paydunya_token);

        // Si PayDunya confirme → activer le boost automatiquement
        if ($result['paid'] && $boost->payment_status === 'pending') {
            $this->activerBoost($boost);
        }

        return response()->json([
            'status'  => $boost->fresh()->payment_status,
            'is_paid' => $result['paid'],
        ]);
    }

    /**
     * Webhook IPN PayDunya — appelé automatiquement après chaque paiement.
     *
     * PayDunya envoie un POST avec le hash SHA512 du master key dans le header
     * X-PAYDUNYA-MASTER-KEY pour vérifier l'authenticité.
     *
     * NB : Cette route est SANS auth Sanctum (appelée par PayDunya, pas par l'app).
     *      Elle est protégée par la vérification du hash IPN.
     */
    public function webhook(Request $request, PaydunyaService $paydunya): JsonResponse
    {
        // 1. Vérifier la signature IPN
        $hash = $request->header('X-PAYDUNYA-MASTER-KEY', '');
        if (! $paydunya->verifyIPN($hash)) {
            return response()->json(['message' => 'Signature invalide.'], 401);
        }

        // 2. Extraire la référence Colways depuis les données custom
        $token     = $request->input('token');
        $status    = $request->input('status');  // "completed" si payé
        $reference = $request->input('custom_data.colways_reference', '');

        // Format attendu : "BOOST-{id}"
        if (! str_starts_with($reference, 'BOOST-')) {
            return response()->json(['message' => 'Référence inconnue.'], 200);
        }

        $boostId = (int) str_replace('BOOST-', '', $reference);
        $boost   = Boost::find($boostId);

        if (! $boost || $boost->payment_status !== 'pending') {
            return response()->json(['message' => 'Boost introuvable ou déjà traité.'], 200);
        }

        // 3. Sauvegarder le statut PayDunya
        $boost->update(['paydunya_status' => $status]);

        // 4. Activer si paiement confirmé
        if ($status === 'completed') {
            $this->activerBoost($boost);
        }

        return response()->json(['message' => 'IPN reçu.'], 200);
    }

    /**
     * Active le boost : marque l'article comme boosté et définit la date d'expiration.
     */
    private function activerBoost(Boost $boost): void
    {
        $now     = now();
        $expires = $now->copy()->addHours($boost->duration_hours);

        $boost->update([
            'payment_status' => 'confirmed',
            'starts_at'      => $now,
            'expires_at'     => $expires,
            'confirmed_at'   => $now,
        ]);

        // Marquer l'article comme boosté
        $boost->article->update([
            'is_boosted'      => true,
            'boost_expires_at'=> $expires,
        ]);
    }

    /**
     * Historique des boosts du vendeur connecté.
     */
    public function myBoosts(Request $request): JsonResponse
    {
        $boosts = Boost::where('user_id', $request->user()->id)
            ->with('article:id,title,status')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($boosts);
    }

    /**
     * Génère les instructions de paiement Wave / Orange Money.
     * Le numéro de référence contient l'ID du boost pour faciliter la vérification admin.
     */
    private function getInstructionsPaiement(int $montant, int $boostId): array
    {
        return [
            'wave' => [
                'numero'    => '+221 XX XXX XX XX', // À remplacer par ton numéro Wave
                'montant'   => "{$montant} FCFA",
                'reference' => "COLWAYS-BOOST-{$boostId}",
                'message'   => "Envoie {$montant} FCFA sur Wave avec la référence COLWAYS-BOOST-{$boostId}",
            ],
            'orange_money' => [
                'numero'    => '+221 XX XXX XX XX', // À remplacer par ton numéro OM
                'montant'   => "{$montant} FCFA",
                'reference' => "COLWAYS-BOOST-{$boostId}",
                'message'   => "Envoie {$montant} FCFA sur Orange Money avec la référence COLWAYS-BOOST-{$boostId}",
            ],
            'note' => "Après paiement, ton article sera mis en avant dans les 30 minutes. ⚡",
        ];
    }
}
