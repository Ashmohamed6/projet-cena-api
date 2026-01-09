<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProcesVerbal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PVVerificationController extends Controller
{
    /**
     * Vérifier si un PV existe déjà pour une localisation donnée
     * 
     * GET /api/v1/pv/verification/existe
     * 
     * Query params:
     * - election_id: required
     * - niveau: required (commune|arrondissement|village_quartier)
     * - niveau_id: required
     * 
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "existe": true,
     *     "pv": { ... }  // Si existe
     *   }
     * }
     */
    public function verifierExistence(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'election_id' => 'required|integer|exists:elections,id',
                'niveau' => 'required|string|in:commune,arrondissement,village_quartier',
                'niveau_id' => 'required|integer',
            ]);

            $electionId = $validated['election_id'];
            $niveau = $validated['niveau'];
            $niveauId = $validated['niveau_id'];

            // Chercher un PV existant
            $pv = ProcesVerbal::where('election_id', $electionId)
                ->where('niveau', $niveau)
                ->where('niveau_id', $niveauId)
                ->first();

            if ($pv) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'existe' => true,
                        'pv' => [
                            'id' => $pv->id,
                            'code' => $pv->code,
                            'statut' => $pv->statut,
                            'created_at' => $pv->created_at->format('Y-m-d H:i:s'),
                        ],
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'existe' => false,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Erreur vérification PV:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification',
            ], 500);
        }
    }

    /**
     * Obtenir la liste des entités qui ont déjà un PV
     * 
     * GET /api/v1/pv/verification/entites-utilisees
     * 
     * Query params:
     * - election_id: required
     * - niveau: required (commune|arrondissement|village_quartier)
     * - parent_id: optional (commune_id pour arrondissements, arrondissement_id pour villages)
     * 
     * Response:
     * {
     *   "success": true,
     *   "data": [123, 456, 789]  // IDs des entités qui ont déjà un PV
     * }
     */
     public function getEntitesUtilisees(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'election_id' => 'required|integer|exists:elections,id',
                'niveau' => 'required|string|in:commune,arrondissement,village_quartier',
                'parent_id' => 'nullable|integer',
            ]);

            $electionId = $validated['election_id'];
            $niveau = $validated['niveau'];
            $parentId = $validated['parent_id'] ?? null;

            // Récupérer les IDs des entités qui ont déjà un PV
            $query = ProcesVerbal::where('election_id', $electionId)
                ->where('niveau', $niveau);

            // Filtrer par parent si fourni
            if ($parentId && $niveau === 'arrondissement') {
                // Pour arrondissements d'une commune spécifique
                $query->whereHas('arrondissement', function($q) use ($parentId) {
                    $q->where('commune_id', $parentId);
                });
            } elseif ($parentId && $niveau === 'village_quartier') {
                // Pour villages d'un arrondissement spécifique
                $query->whereHas('villageQuartier', function($q) use ($parentId) {
                    $q->where('arrondissement_id', $parentId);
                });
            }

            $entitesUtilisees = $query->pluck('niveau_id')->toArray();

            return response()->json([
                'success' => true,
                'data' => $entitesUtilisees,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Erreur récupération entités utilisées:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération',
            ], 500);
        }
    }
}