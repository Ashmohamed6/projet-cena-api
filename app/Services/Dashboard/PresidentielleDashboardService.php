<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;

class PresidentielleDashboardService
{
    public function getStats(int $electionId): array
    {
        return (new LegislativeDashboardService())->getStats($electionId);
    }

    public function getResultats(int $electionId): array
    {
        return (new LegislativeDashboardService())->getResultats($electionId);
    }

    public function getResultatDepartement(int $electionId, int $departementId): array
    {
        // On suppose: niveau='departement' et niveau_id = departementId
        $items = DB::table('proces_verbaux')
            ->where('election_id', $electionId)
            ->where('niveau', 'departement')
            ->where('niveau_id', $departementId)
            ->orderByDesc('updated_at')
            ->get();

        return [
            'election_id' => $electionId,
            'departement_id' => $departementId,
            'items' => $items,
        ];
    }

    public function getTauxParticipation(int $electionId): array
    {
        return (new LegislativeDashboardService())->getTauxParticipation($electionId);
    }

    public function getProgression(int $electionId): array
    {
        return (new LegislativeDashboardService())->getProgression($electionId);
    }

    public function getHistorique(int $electionId): array
    {
        return (new LegislativeDashboardService())->getHistorique($electionId);
    }

    public function getCartographie(int $electionId): array
    {
        return (new LegislativeDashboardService())->getCartographie($electionId);
    }
}
