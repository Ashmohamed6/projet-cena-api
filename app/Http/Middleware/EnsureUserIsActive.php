<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Middleware EnsureUserIsActive
 * 
 * Vérifie que le compte utilisateur est actif
 */
class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'error' => 'Non authentifié',
            ], 401);
        }

        $user = auth()->user();

        if ($user->statut !== 'actif') {
            // Logger la tentative
            \App\Models\ActivityLog::log(
                action: 'inactive_user_attempt',
                module: 'security',
                description: 'Tentative d\'accès avec compte inactif',
                metadata: ['user_status' => $user->statut]
            );

            // Déconnecter l'utilisateur
            auth()->logout();

            return response()->json([
                'error' => 'Compte inactif',
                'message' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.',
                'status' => $user->statut,
            ], 403);
        }

        return $next($request);
    }
}
