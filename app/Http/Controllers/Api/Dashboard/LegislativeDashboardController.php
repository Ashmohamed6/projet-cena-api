<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Dashboard\LegislativeDashboardService;
use Illuminate\Support\Facades\Log;

class LegislativeDashboardController extends Controller
{
    public function __construct(private LegislativeDashboardService $dashboardService) {}

    /**
     * ✅ Récupère l'ID d'élection depuis PLUSIEURS sources (defensive)
     */
    private function electionId(Request $request): int
    {
        // Essayer dans cet ordre :
        // 1. Attributs (si middleware fonctionne)
        $id = $request->attributes->get('active_election_id')
            ?? $request->attributes->get('election_id')
            // 2. Header X-Election-Id
            ?? $request->header('X-Election-Id')
            // 3. Query params
            ?? $request->input('election_id')
            ?? $request->input('electionId')
            ?? 0;

        $electionId = (int) $id;

        // Log pour debug
        Log::info("Dashboard Legislative - Election ID: {$electionId}", [
            'from_attributes_active' => $request->attributes->get('active_election_id'),
            'from_attributes' => $request->attributes->get('election_id'),
            'from_header' => $request->header('X-Election-Id'),
            'from_query' => $request->input('election_id'),
        ]);

        return $electionId;
    }

    /**
     * GET /dashboard/legislative
     * Statistiques générales
     */
    public function stats(Request $request)
    {
        try {
            $electionId = $this->electionId($request);

            if ($electionId === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID d\'élection non fourni',
                    'debug' => [
                        'header' => $request->header('X-Election-Id'),
                        'query' => $request->input('election_id'),
                        'attributes' => $request->attributes->all(),
                    ]
                ], 400);
            }

            $data = $this->dashboardService->getStats($electionId);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Legislative Stats Error", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * GET /dashboard/legislative/resultats
     */
    public function resultats(Request $request)
    {
        try {
            $electionId = $this->electionId($request);

            if ($electionId === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID d\'élection non fourni'
                ], 400);
            }

            $data = $this->dashboardService->getResultats($electionId);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Legislative Resultats Error", [
                'message' => $e->getMessage(),
                'election_id' => $this->electionId($request),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des résultats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /dashboard/legislative/participation
     */
    public function participation(Request $request)
    {
        return $this->tauxParticipation($request);
    }

    /**
     * GET /dashboard/legislative/taux-participation
     */
    public function tauxParticipation(Request $request)
    {
        try {
            $electionId = $this->electionId($request);

            if ($electionId === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID d\'élection non fourni'
                ], 400);
            }

            $data = $this->dashboardService->getTauxParticipation($electionId);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Legislative Participation Error", [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la participation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /dashboard/legislative/partis
     */
    public function partis(Request $request)
    {
        try {
            $electionId = $this->electionId($request);

            if ($electionId === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID d\'élection non fourni'
                ], 400);
            }

            $data = $this->dashboardService->getStatistiquesPartis($electionId);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Legislative Partis Error", [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des partis',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /dashboard/legislative/progression
     */
    public function progression(Request $request)
    {
        try {
            $electionId = $this->electionId($request);

            if ($electionId === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID d\'élection non fourni'
                ], 400);
            }

            $data = $this->dashboardService->getProgression($electionId);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Legislative Progression Error", [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la progression',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /dashboard/legislative/incidents
     */
    public function incidents(Request $request)
    {
        try {
            $electionId = $this->electionId($request);

            if ($electionId === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID d\'élection non fourni'
                ], 400);
            }

            $historique = $this->dashboardService->getHistorique($electionId);

            return response()->json([
                'success' => true,
                'data' => $historique['incidents'] ?? [],
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Legislative Incidents Error", [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des incidents',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /dashboard/legislative/audit
     */
    public function audit(Request $request)
    {
        try {
            $electionId = $this->electionId($request);

            if ($electionId === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID d\'élection non fourni'
                ], 400);
            }

            $data = $this->dashboardService->getHistorique($electionId);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error("Dashboard Legislative Audit Error", [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'audit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Méthodes supplémentaires (non critiques pour le moment)
    public function circonscriptions(Request $request)
    {
        return $this->resultats($request);
    }

    public function circonscription(Request $request, int $circonscriptionId)
    {
        try {
            $electionId = $this->electionId($request);
            $data = $this->dashboardService->getResultatCirconscription($electionId, $circonscriptionId);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function parti(Request $request, int $partiId)
    {
        return $this->partis($request);
    }

    public function historique(Request $request)
    {
        return $this->audit($request);
    }

    public function cartographie(Request $request)
    {
        try {
            $electionId = $this->electionId($request);
            $data = $this->dashboardService->getCartographie($electionId);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function repartitionSieges(Request $request)
    {
        try {
            $electionId = $this->electionId($request);
            $data = $this->dashboardService->getRepartitionSieges($electionId);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}