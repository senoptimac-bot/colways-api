<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    /**
     * Liste tous les signalements en attente de traitement.
     */
    public function pending(): JsonResponse
    {
        $reports = Report::where('status', 'pending')
            ->with([
                'article:id,title,status,user_id',
                'reporter:id,name,phone',
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'count'   => $reports->count(),
            'reports' => $reports,
        ]);
    }

    /**
     * Traite un signalement : supprimer l'article ou le rejeter.
     *
     * Actions disponibles :
     *   - "dismiss"        → rejeter le signalement (article reste en ligne)
     *   - "delete_article" → supprimer l'article signalé
     */
    public function action(Request $request, Report $report): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'in:dismiss,delete_article'],
        ]);

        if ($request->action === 'delete_article') {
            // Supprimer l'article — l'ArticleObserver nettoie Cloudinary
            $report->article?->delete();

            // Marquer tous les signalements liés comme traités
            Report::where('article_id', $report->article_id)
                ->update([
                    'status'      => 'reviewed',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);

            return response()->json([
                'message' => 'Article supprimé et signalements marqués comme traités.',
            ]);
        }

        // Action "dismiss" — rejeter le signalement
        $report->update([
            'status'      => 'dismissed',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Signalement rejeté.',
        ]);
    }
}
