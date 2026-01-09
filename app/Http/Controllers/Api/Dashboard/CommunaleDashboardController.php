<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use App\Services\Dashboard\CommunaleDashboardService;

class CommunaleDashboardController extends Controller
{
    public function __construct(private CommunaleDashboardService $dashboardService) {}

    private function electionId(Request $request): int
    {
        return (int) ($request->attributes->get('active_election_id') 
            ?? $request->header('X-Election-Id') 
            ?? $request->input('election_id') 
            ?? 0);
    }

    public function stats(Request $request): JsonResponse
    {
        $electionId = $this->electionId($request);
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getStats($electionId),
        ]);
    }

    public function participation(Request $request): JsonResponse
    {
        $electionId = $this->electionId($request);
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getParticipation($electionId),
        ]);
    }

    public function resultats(Request $request): JsonResponse
    {
        $electionId = $this->electionId($request);
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getResultats($electionId),
        ]);
    }

    public function progression(Request $request): JsonResponse
    {
        $electionId = $this->electionId($request);
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->getProgression($electionId),
        ]);
    }
}