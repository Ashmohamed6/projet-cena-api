<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware CheckRole
 * 
 * Vérifie que l'utilisateur a au moins un des rôles requis
 */
class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'error' => 'Non authentifié',
                'message' => 'Vous devez être connecté pour accéder à cette ressource',
            ], 401);
        }

        $user = auth()->user();

        if (!$user->hasRole($roles)) {
            \App\Models\ActivityLog::log(
                action: 'access_denied',
                module: 'security',
                description: 'Accès refusé - Rôle insuffisant',
                metadata: ['required_roles' => $roles]
            );

            return response()->json([
                'error' => 'Accès refusé',
                'message' => 'Vous n\'avez pas les rôles nécessaires pour cette action',
                'required_roles' => $roles,
            ], 403);
        }

        return $next($request);
    }
}
