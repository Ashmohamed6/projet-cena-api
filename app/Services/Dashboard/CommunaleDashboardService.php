<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\{DB, Cache};

class CommunaleDashboardService
{
    const CACHE_MINUTES = 5;

    public function getStats(int $electionId): array
    {
        return Cache::remember("dashboard.communale.stats.{$electionId}", self::CACHE_MINUTES, function() use ($electionId) {
            // Corps électoral
            $corpsElectoral = DB::table('postes_vote')
                ->selectRaw('COALESCE(SUM(electeurs_inscrits), 0)::int as total_inscrits')
                ->first();

            // Participation
            $pvData = DB::table('proces_verbaux')
                ->where('election_id', $electionId)
                ->whereNotIn('statut', ['brouillon', 'annule'])
                ->selectRaw('
                    COALESCE(SUM(nombre_votants), 0)::int as total_votants,
                    COUNT(*)::int as pv_total,
                    COALESCE(SUM(CASE WHEN statut = \'valide\' THEN 1 ELSE 0 END), 0)::int as pv_traites
                ')
                ->first();

            $totalInscrits = $corpsElectoral->total_inscrits ?? 0;
            $totalVotants = $pvData->total_votants ?? 0;

            $tauxParticipation = $totalInscrits > 0 
                ? round(($totalVotants / $totalInscrits) * 100, 2) 
                : 0;

            return [
                'election_id' => $electionId,
                'inscrits' => $totalInscrits,
                'votants' => $totalVotants,
                'participation_pct' => $tauxParticipation,
                'pv_total' => $pvData->pv_total ?? 0,
                'pv_traites' => $pvData->pv_traites ?? 0,
                'incidents_total' => DB::table('incidents')->where('election_id', $electionId)->count(),
            ];
        });
    }

    public function getParticipation(int $electionId): array
    {
        $stats = $this->getStats($electionId);
        
        return [
            'election_id' => $electionId,
            'inscrits' => $stats['inscrits'],
            'votants' => $stats['votants'],
            'participation_pct' => $stats['participation_pct'],
        ];
    }

    public function getResultats(int $electionId): array
    {
        $items = DB::table('proces_verbaux')
            ->where('election_id', $electionId)
            ->selectRaw('
                niveau,
                niveau_id,
                COUNT(*)::int as pv_total,
                COALESCE(SUM(nombre_votants), 0)::int as votants
            ')
            ->groupBy('niveau', 'niveau_id')
            ->orderBy('niveau')
            ->orderBy('niveau_id')
            ->get();

        return [
            'election_id' => $electionId,
            'items' => $items->toArray(),
        ];
    }

    public function getProgression(int $electionId): array
    {
        $pvStats = DB::table('proces_verbaux')
            ->where('election_id', $electionId)
            ->selectRaw('
                COUNT(*)::int as pv_total,
                COALESCE(SUM(CASE WHEN statut = \'valide\' THEN 1 ELSE 0 END), 0)::int as pv_traites
            ')
            ->first();

        $pvTotal = $pvStats->pv_total ?? 0;
        $pvTraites = $pvStats->pv_traites ?? 0;

        $pourcentage = $pvTotal > 0 
            ? round(($pvTraites / $pvTotal) * 100, 2) 
            : 0;

        return [
            'election_id' => $electionId,
            'pv_total' => $pvTotal,
            'pv_traites' => $pvTraites,
            'progression_pct' => $pourcentage,
        ];
    }
}