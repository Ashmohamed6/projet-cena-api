<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware CheckPermission
 * 
 * Vérifie que l'utilisateur a au moins une des permissions requises
 */
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'error' => 'Non authentifié',
                'message' => 'Vous devez être connecté pour accéder à cette ressource',
            ], 401);
        }

        $user = auth()->user();

        // Super admin bypass
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (!$user->hasAnyPermission($permissions)) {
            \App\Models\ActivityLog::log(
                action: 'access_denied',
                module: 'security',
                description: 'Accès refusé - Permission insuffisante',
                metadata: ['required_permissions' => $permissions]
            );

            return response()->json([
                'error' => 'Accès refusé',
                'message' => 'Vous n\'avez pas les permissions nécessaires pour cette action',
                'required_permissions' => $permissions,
            ], 403);
        }

        return $next($request);
    }
}
