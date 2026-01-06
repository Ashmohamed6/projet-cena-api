<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckElectionAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // ✅ Header injecté automatiquement par ton api.ts
        $electionId = $request->header('X-Election-Id')
            ?? $request->input('election_id')
            ?? $request->input('electionId');

        if (!$electionId || !is_numeric($electionId)) {
            return response()->json([
                "success" => false,
                "message" => "Élection non spécifiée"
            ], 400);
        }

        $electionId = (int) $electionId;

        // ✅ Vérifier que l'utilisateur a accès à cette élection
        $hasAccess = $user->affectations()
            ->where('election_id', $electionId)
            ->where('actif', true)
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                "success" => false,
                "message" => "Accès refusé à cette élection"
            ], 403);
        }

        // ✅ CORRECTION: On stocke avec une clé cohérente que les contrôleurs vont lire
        $request->attributes->set('active_election_id', $electionId);

        return $next($request);
    }
}