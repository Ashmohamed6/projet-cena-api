<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Dashboard\CommunaleDashboardService;

class CommunaleDashboardController extends Controller
{
    public function __construct(private CommunaleDashboardService $dashboardService) {}

    /**
     * ✅ Récupère l'ID d'élection active depuis request->attributes
     */
    private function electionId(Request $request): int
    {
        return (int) ($request->attributes->get('active_election_id') ?? 0);
    }

    public function stats(Request $request)
    {
        $electionId = $this->electionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getStats($electionId),
        ]);
    }

    public function resultats(Request $request)
    {
        $electionId = $this->electionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getResultats($electionId),
        ]);
    }

    public function resultatCommune(Request $request, int $communeId)
    {
        $electionId = $this->electionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getResultatCommune($electionId, $communeId),
        ]);
    }

    /**
     * GET /dashboard/communale/participation
     * ✅ ALIAS pour tauxParticipation
     */
    public function participation(Request $request)
    {
        return $this->tauxParticipation($request);
    }

    public function tauxParticipation(Request $request)
    {
        $electionId = $this->electionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getTauxParticipation($electionId),
        ]);
    }

    public function progression(Request $request)
    {
        $electionId = $this->electionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getProgression($electionId),
        ]);
    }

    public function historique(Request $request)
    {
        $electionId = $this->electionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getHistorique($electionId),
        ]);
    }

    public function cartographie(Request $request)
    {
        $electionId = $this->electionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getCartographie($electionId),
        ]);
    }

    public function incidents(Request $request)
    {
        $electionId = $this->electionId($request);

        $historique = $this->dashboardService->getHistorique($electionId);

        return response()->json([
            'success' => true,
            'data' => $historique['incidents'] ?? [],
        ]);
    }

    public function audit(Request $request)
    {
        $electionId = $this->electionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getHistorique($electionId),
        ]);
    }
}