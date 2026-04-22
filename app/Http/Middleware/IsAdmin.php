<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Vérifie que l'utilisateur connecté est un admin Colways.
     * Utilisé sur toutes les routes /api/admin/*.
     *
     * Si l'utilisateur n'est pas admin → 403 Forbidden.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json([
                'message' => 'Accès réservé à l\'équipe Colways.',
            ], 403);
        }

        return $next($request);
    }
}
