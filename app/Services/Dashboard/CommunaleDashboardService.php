<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;

class CommunaleDashboardService
{
    public function getStats(int $electionId): array
    {
        // Même logique que législative
        $pvAgg = DB::table('proces_verbaux')
            ->where('election_id', $electionId)
            ->selectRaw('
                COALESCE(SUM(nombre_inscrits),0) as total_inscrits,
                COALESCE(SUM(nombre_votants),0) as total_votants,
                COUNT(*)::int as pv_total,
                COALESCE(SUM(CASE WHEN statut IS NULL OR statut = \'brouillon\' THEN 0 ELSE 1 END),0)::int as pv_traites
            ')
            ->first();

        $totalInscrits = (int)($pvAgg->total_inscrits ?? 0);
        $totalVotants  = (int)($pvAgg->total_votants ?? 0);
        $taux          = $totalInscrits > 0 ? round(($totalVotants / $totalInscrits) * 100, 2) : 0;

        return [
            'election_id' => $electionId,
            'inscrits' => $totalInscrits,
            'votants' => $totalVotants,
            'participation_pct' => $taux,
            'pv_total' => (int)($pvAgg->pv_total ?? 0),
            'pv_traites' => (int)($pvAgg->pv_traites ?? 0),
            'incidents_total' => DB::table('incidents')->where('election_id', $electionId)->count(),
        ];
    }

    public function getResultats(int $electionId): array
    {
        // Pour communales, on peut agréger aussi par niveau/niveau_id
        $items = DB::table('proces_verbaux')
            ->where('election_id', $electionId)
            ->selectRaw('niveau, niveau_id, COUNT(*)::int as pv_total, COALESCE(SUM(nombre_votants),0)::int as votants')
            ->groupBy('niveau', 'niveau_id')
            ->orderBy('niveau')
            ->orderBy('niveau_id')
            ->get();

        return ['election_id' => $electionId, 'items' => $items];
    }

    public function getResultatCommune(int $electionId, int $communeId): array
    {
        // On suppose que pour communales : niveau='commune' et niveau_id = communeId
        $items = DB::table('proces_verbaux')
            ->where('election_id', $electionId)
            ->where('niveau', 'commune')
            ->where('niveau_id', $communeId)
            ->orderByDesc('updated_at')
            ->get();

        return [
            'election_id' => $electionId,
            'commune_id' => $communeId,
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
