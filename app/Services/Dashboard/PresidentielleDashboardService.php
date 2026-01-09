<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\{DB, Cache};

class PresidentielleDashboardService
{
    const CACHE_MINUTES = 5;

    public function getStats(int $electionId): array
    {
        return Cache::remember("dashboard.presidentielle.stats.{$electionId}", self::CACHE_MINUTES, function() use ($electionId) {
            // Réutiliser le service législatives
            $legislativeService = new LegislativeDashboardService();
            return $legislativeService->getStats($electionId);
        });
    }

    public function getParticipation(int $electionId): array
    {
        $stats = $this->getStats($electionId);
        
        return [
            'election_id' => $electionId,
            'inscrits' => $stats['inscrits_total'] ?? 0,
            'votants' => $stats['total_votants'] ?? 0,
            'participation_pct' => $stats['taux_participation'] ?? 0,
        ];
    }

    public function getResultats(int $electionId): array
    {
        $candidats = DB::table('resultats as r')
            ->join('proces_verbaux as pv', 'pv.id', '=', 'r.proces_verbal_id')
            ->join('candidatures as c', 'c.id', '=', 'r.candidature_id')
            ->join('entites_politiques as ep', 'ep.id', '=', 'c.entite_politique_id')
            ->where('pv.election_id', $electionId)
            ->whereNotIn('pv.statut', ['brouillon', 'annule'])
            ->selectRaw('
                c.id as candidature_id,
                ep.nom as candidat_nom,
                ep.sigle as candidat_sigle,
                COALESCE(SUM(r.nombre_voix), 0)::int as total_voix
            ')
            ->groupBy('c.id', 'ep.nom', 'ep.sigle')
            ->orderByDesc('total_voix')
            ->get();

        return [
            'election_id' => $electionId,
            'candidats' => $candidats->toArray(),
            'top_3' => array_slice($candidats->toArray(), 0, 3),
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