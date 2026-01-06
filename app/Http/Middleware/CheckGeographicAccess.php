<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware CheckGeographicAccess
 * 
 * Vérifie que l'utilisateur a accès au niveau géographique demandé
 */
class CheckGeographicAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $niveauParam  Nom du paramètre de route contenant l'ID
     * @param  string  $niveau  Type de niveau (departement, circonscription, etc.)
     */
    public function handle(Request $request, Closure $next, string $niveauParam, string $niveau): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'error' => 'Non authentifié',
            ], 401);
        }

        $user = auth()->user();

        // Super admin bypass
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Récupérer l'ID depuis les paramètres de route
        $niveauId = $request->route($niveauParam);

        if (!$niveauId) {
            return response()->json([
                'error' => 'Paramètre manquant',
                'message' => "Le paramètre '{$niveauParam}' est requis",
            ], 400);
        }

        // Vérifier l'accès
        if (!$user->hasAccessToNiveau($niveau, $niveauId)) {
            \App\Models\ActivityLog::log(
                action: 'geographic_access_denied',
                module: 'security',
                description: 'Accès refusé - Zone géographique non autorisée',
                metadata: [
                    'niveau' => $niveau,
                    'niveau_id' => $niveauId,
                ]
            );

            return response()->json([
                'error' => 'Accès refusé',
                'message' => 'Vous n\'avez pas accès à cette zone géographique',
            ], 403);
        }

        return $next($request);
    }
}
