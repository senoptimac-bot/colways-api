<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Article;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ReportController extends Controller
{
    /**
     * ══════════════════════════════════════════════════════════════════════
     *  Signalement d'un article suspect — Sprint 13
     * ══════════════════════════════════════════════════════════════════════
     *
     *  Nouvelles raisons disponibles :
     *   - arnaque             → 2 signalements distincts → pending_review auto
     *   - article_inexistant
     *   - contenu_inapproprie
     *   - pas_de_la_friperie  → 3 signalements distincts → pending_review auto
     *   - prix_abusif
     *   - copie_article
     *   - autre
     *
     *  Rate limit : 3 signalements/heure/IP (routes).
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $article = Article::findOrFail($request->article_id);

        // Éviter le double-signalement par le même utilisateur connecté
        if (auth()->check()) {
            $dejaSignale = Report::where('article_id', $request->article_id)
                ->where('reporter_id', auth()->id())
                ->exists();

            if ($dejaSignale) {
                return response()->json([
                    'message' => 'Tu as déjà signalé cet article. Notre équipe l\'examine. Merci ! 🙏',
                ], 422);
            }
        }

        $report = Report::create([
            'article_id'  => $request->article_id,
            'reporter_id' => auth()->id(),
            'reason'      => $request->reason,
            'description' => $request->description,
            'status'      => 'pending',
        ]);

        // Vérifier si le seuil communautaire est atteint → suspension auto
        $this->verifierSeuilCommunautaire($article, $request->reason);

        // Notification email admin
        $this->notifierAdmin($report, $article);

        return response()->json([
            'message' => 'Signalement reçu. Notre équipe va examiner cet article. Merci de protéger Colways ! 🙏',
        ], 201);
    }

    /**
     * Applique la règle de suspension communautaire automatique.
     *
     * Si le seuil de signalements distincts est atteint pour une raison critique,
     * l'article passe en pending_review et disparaît du feed immédiatement.
     */
    private function verifierSeuilCommunautaire(Article $article, string $reason): void
    {
        $cfg = config('friperie.reports');

        $regles = [
            'pas_de_la_friperie' => $cfg['suspend_threshold_identity'] ?? 3,
            'arnaque'            => $cfg['suspend_threshold_scam']     ?? 2,
        ];

        if (! isset($regles[$reason])) {
            return;
        }

        $seuil = $regles[$reason];
        $count = Report::where('article_id', $article->id)
            ->where('reason', $reason)
            ->where('status', 'pending')
            ->distinct('reporter_id')
            ->count('reporter_id');

        if ($count >= $seuil && $article->status === 'available') {
            $article->update([
                'status'         => 'pending_review',
                'guardian_flags' => array_merge(
                    $article->guardian_flags ?? [],
                    ["auto_suspend_communaute:{$reason}"]
                ),
            ]);
        }
    }

    /**
     * Notifie l'admin — inclut le friperie_score et le statut actuel de l'article.
     */
    private function notifierAdmin(Report $report, Article $article): void
    {
        try {
            $adminEmail = config('app.admin_email', 'admin@colways.sn');

            $labels = [
                'arnaque'            => '🚨 Arnaque',
                'article_inexistant' => '❓ Article inexistant',
                'contenu_inapproprie'=> '🔞 Contenu inapproprié',
                'pas_de_la_friperie' => '👗 Pas de la friperie',
                'prix_abusif'        => '💸 Prix abusif',
                'copie_article'      => '📋 Article dupliqué',
                'autre'              => 'ℹ️ Autre',
            ];

            $label = $labels[$report->reason] ?? $report->reason;

            Mail::raw(
                "Nouveau signalement Colways\n\n" .
                "Raison     : {$label}\n" .
                "Article    : #{$article->id} — {$article->title}\n" .
                "Score      : {$article->friperie_score}/100\n" .
                "Statut     : {$article->status}\n" .
                "Détail     : {$report->description}\n\n" .
                "Voir : " . config('app.url') . "/admin/reports/{$report->id}",
                fn ($m) => $m
                    ->to($adminEmail)
                    ->subject("[Colways] {$label} — Article #{$article->id}")
            );
        } catch (\Exception $e) {
            // Silencieux en local (MAIL_MAILER=log)
        }
    }
}
