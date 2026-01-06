<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware LogActivity
 * 
 * Logger automatiquement toutes les actions
 */
class LogActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Logger uniquement si authentifié
        if (auth()->check()) {
            // Déterminer l'action
            $action = $this->determineAction($request);
            
            // Déterminer le module
            $module = $this->determineModule($request);

            // Logger l'activité
            \App\Models\ActivityLog::log(
                action: $action,
                module: $module,
                description: $this->buildDescription($request),
                metadata: [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'params' => $request->except(['password', 'password_confirmation', 'token']),
                    'status_code' => $response->getStatusCode(),
                ]
            );
        }

        return $response;
    }

    private function determineAction(Request $request): string
    {
        $method = $request->method();
        
        return match($method) {
            'GET' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'unknown',
        };
    }

    private function determineModule(Request $request): string
    {
        $path = $request->path();
        
        // Extraire le module depuis le path
        if (preg_match('#api/v1/([^/]+)#', $path, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }

    private function buildDescription(Request $request): string
    {
        $action = $this->determineAction($request);
        $module = $this->determineModule($request);

        return ucfirst($action) . ' ' . $module;
    }
}
