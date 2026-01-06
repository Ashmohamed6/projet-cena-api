<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class LegislativeDashboardService
{
    /**
     * Détecte automatiquement le nom de la table entités politiques
     */
    private function entitePolitiqueTable(): ?string
    {
        try {
            if (Schema::hasTable('entite_politiques')) return 'entite_politiques';
            if (Schema::hasTable('entites_politiques')) return 'entites_politiques';
            if (Schema::hasTable('entitepolitiques')) return 'entitepolitiques';
            
            Log::warning("Aucune table entité politique trouvée");
            return null;
        } catch (\Exception $e) {
            Log::error("Erreur détection table entités", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Statistiques générales
     */
    public function getStats(int $electionId): array
    {
        try {
            // Vérifier que l'élection existe
            $electionExists = DB::table('elections')->where('id', $electionId)->exists();
            if (!$electionExists) {
                Log::warning("Election {$electionId} n'existe pas");
                return $this->emptyStats($electionId);
            }

            // Agrégation PV
            $pvAgg = DB::table('proces_verbaux')
                ->where('election_id', $electionId)
                ->selectRaw('
                    COALESCE(SUM(nombre_inscrits),0) as total_inscrits,
                    COALESCE(SUM(nombre_votants),0) as total_votants,
                    COUNT(*)::int as pv_total,
                    COALESCE(SUM(CASE WHEN statut IS NULL OR statut = \'brouillon\' THEN 0 ELSE 1 END),0)::int as pv_traites,
                    COALESCE(SUM(CASE WHEN statut IS NULL OR statut = \'brouillon\' THEN 1 ELSE 0 END),0)::int as pv_brouillon
                ')
                ->first();

            // Incidents
            $incidentsTotal = DB::table('incidents')
                ->where('election_id', $electionId)
                ->count();

            // Résultats
            $resultAgg = DB::table('resultats as r')
                ->join('proces_verbaux as pv', 'pv.id', '=', 'r.proces_verbal_id')
                ->where('pv.election_id', $electionId)
                ->selectRaw('COALESCE(SUM(r.nombre_voix),0) as total_voix, COUNT(*)::int as lignes_resultats')
                ->first();

            // Candidatures
            $candidaturesTotal = DB::table('candidatures')
                ->where('election_id', $electionId)
                ->count();

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
                'pv_brouillon' => (int)($pvAgg->pv_brouillon ?? 0),
                'incidents_total' => (int)$incidentsTotal,
                'resultats_total_voix' => (int)($resultAgg->total_voix ?? 0),
                'resultats_lignes' => (int)($resultAgg->lignes_resultats ?? 0),
                'candidatures_total' => (int)$candidaturesTotal,
            ];

        } catch (\Exception $e) {
            Log::error("LegislativeDashboardService::getStats ERROR", [
                'election_id' => $electionId,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return $this->emptyStats($electionId);
        }
    }

    /**
     * Retourne des stats vides en cas d'erreur
     */
    private function emptyStats(int $electionId): array
    {
        return [
            'election_id' => $electionId,
            'inscrits' => 0,
            'votants' => 0,
            'participation_pct' => 0,
            'pv_total' => 0,
            'pv_traites' => 0,
            'pv_brouillon' => 0,
            'incidents_total' => 0,
            'resultats_total_voix' => 0,
            'resultats_lignes' => 0,
            'candidatures_total' => 0,
        ];
    }

    /**
     * Résultats globaux
     */
    public function getResultats(int $electionId): array
    {
        try {
            $entiteTable = $this->entitePolitiqueTable();

            $q = DB::table('resultats as r')
                ->join('proces_verbaux as pv', 'pv.id', '=', 'r.proces_verbal_id')
                ->join('candidatures as c', 'c.id', '=', 'r.candidature_id')
                ->where('pv.election_id', $electionId)
                ->selectRaw('
                    c.id as candidature_id,
                    c.code as candidature_code,
                    c.entite_politique_id,
                    c.circonscription_id,
                    COALESCE(SUM(r.nombre_voix),0)::int as voix
                ')
                ->groupBy('c.id', 'c.code', 'c.entite_politique_id', 'c.circonscription_id')
                ->orderByDesc('voix');

            if ($entiteTable) {
                $q->leftJoin($entiteTable.' as ep', 'ep.id', '=', 'c.entite_politique_id')
                  ->addSelect('ep.nom as entite_nom', 'ep.sigle as entite_sigle');
            }

            $items = $q->get();

            return [
                'election_id' => $electionId,
                'items' => $items,
            ];

        } catch (\Exception $e) {
            Log::error("LegislativeDashboardService::getResultats ERROR", [
                'election_id' => $electionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'election_id' => $electionId,
                'items' => [],
            ];
        }
    }

    /**
     * Taux de participation
     */
    public function getTauxParticipation(int $electionId): array
    {
        try {
            $stats = $this->getStats($electionId);
            
            return [
                'election_id' => $electionId,
                'inscrits' => $stats['inscrits'],
                'votants' => $stats['votants'],
                'participation_pct' => $stats['participation_pct'],
            ];

        } catch (\Exception $e) {
            Log::error("LegislativeDashboardService::getTauxParticipation ERROR", [
                'election_id' => $electionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'election_id' => $electionId,
                'inscrits' => 0,
                'votants' => 0,
                'participation_pct' => 0,
            ];
        }
    }

    /**
     * Statistiques par parti
     */
    public function getStatistiquesPartis(int $electionId): array
    {
        try {
            $entiteTable = $this->entitePolitiqueTable();

            if (!$entiteTable) {
                Log::warning("Table entités politiques introuvable");
                return [
                    'election_id' => $electionId,
                    'items' => [],
                ];
            }

            $q = DB::table('resultats as r')
                ->join('proces_verbaux as pv', 'pv.id', '=', 'r.proces_verbal_id')
                ->join('candidatures as c', 'c.id', '=', 'r.candidature_id')
                ->leftJoin($entiteTable.' as ep', 'ep.id', '=', 'c.entite_politique_id')
                ->where('pv.election_id', $electionId)
                ->selectRaw('
                    c.entite_politique_id,
                    ep.nom as entite_nom,
                    ep.sigle as entite_sigle,
                    COALESCE(SUM(r.nombre_voix),0)::int as voix
                ')
                ->groupBy('c.entite_politique_id', 'ep.nom', 'ep.sigle')
                ->orderByDesc('voix');

            $items = $q->get();

            return [
                'election_id' => $electionId,
                'items' => $items,
            ];

        } catch (\Exception $e) {
            Log::error("LegislativeDashboardService::getStatistiquesPartis ERROR", [
                'election_id' => $electionId,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return [
                'election_id' => $electionId,
                'items' => [],
            ];
        }
    }

    /**
     * Progression du dépouillement
     */
    public function getProgression(int $electionId): array
    {
        try {
            $agg = DB::table('proces_verbaux')
                ->where('election_id', $electionId)
                ->selectRaw('
                    COUNT(*)::int as pv_total,
                    COALESCE(SUM(CASE WHEN statut IS NULL OR statut = \'brouillon\' THEN 0 ELSE 1 END),0)::int as pv_traites
                ')
                ->first();

            $pvTotal = (int)($agg->pv_total ?? 0);
            $pvTraites = (int)($agg->pv_traites ?? 0);

            $pct = $pvTotal > 0 ? round(($pvTraites / $pvTotal) * 100, 2) : 0;

            return [
                'election_id' => $electionId,
                'pv_total' => $pvTotal,
                'pv_traites' => $pvTraites,
                'progression_pct' => $pct,
            ];

        } catch (\Exception $e) {
            Log::error("LegislativeDashboardService::getProgression ERROR", [
                'election_id' => $electionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'election_id' => $electionId,
                'pv_total' => 0,
                'pv_traites' => 0,
                'progression_pct' => 0,
            ];
        }
    }

    /**
     * Historique
     */
    public function getHistorique(int $electionId): array
    {
        try {
            $pv = DB::table('proces_verbaux')
                ->where('election_id', $electionId)
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get(['id', 'code', 'statut', 'updated_at', 'created_at']);

            $incidents = DB::table('incidents')
                ->where('election_id', $electionId)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'code', 'type', 'gravite', 'niveau', 'niveau_id', 'created_at']);

            return [
                'election_id' => $electionId,
                'proces_verbaux' => $pv,
                'incidents' => $incidents,
            ];

        } catch (\Exception $e) {
            Log::error("LegislativeDashboardService::getHistorique ERROR", [
                'election_id' => $electionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'election_id' => $electionId,
                'proces_verbaux' => [],
                'incidents' => [],
            ];
        }
    }

    /**
     * Cartographie
     */
    public function getCartographie(int $electionId): array
    {
        try {
            $items = DB::table('proces_verbaux')
                ->where('election_id', $electionId)
                ->selectRaw('
                    niveau,
                    niveau_id,
                    COUNT(*)::int as pv_total,
                    COALESCE(SUM(nombre_inscrits),0)::int as inscrits,
                    COALESCE(SUM(nombre_votants),0)::int as votants
                ')
                ->groupBy('niveau', 'niveau_id')
                ->orderBy('niveau')
                ->orderBy('niveau_id')
                ->get();

            return [
                'election_id' => $electionId,
                'items' => $items,
            ];

        } catch (\Exception $e) {
            Log::error("LegislativeDashboardService::getCartographie ERROR", [
                'election_id' => $electionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'election_id' => $electionId,
                'items' => [],
            ];
        }
    }

    /**
     * Répartition des sièges (non implémenté)
     */
    public function getRepartitionSieges(int $electionId): array
    {
        return [
            'election_id' => $electionId,
            'message' => 'Répartition des sièges: non implémentée pour le moment.',
            'items' => [],
        ];
    }

    /**
     * Résultat d'une circonscription
     */
    public function getResultatCirconscription(int $electionId, int $circonscriptionId): array
    {
        try {
            $entiteTable = $this->entitePolitiqueTable();

            $q = DB::table('resultats as r')
                ->join('proces_verbaux as pv', 'pv.id', '=', 'r.proces_verbal_id')
                ->join('candidatures as c', 'c.id', '=', 'r.candidature_id')
                ->where('pv.election_id', $electionId)
                ->where('c.circonscription_id', $circonscriptionId)
                ->selectRaw('
                    c.id as candidature_id,
                    c.code as candidature_code,
                    c.entite_politique_id,
                    c.numero_liste,
                    c.tete_liste,
                    COALESCE(SUM(r.nombre_voix),0)::int as voix
                ')
                ->groupBy('c.id', 'c.code', 'c.entite_politique_id', 'c.numero_liste', 'c.tete_liste')
                ->orderByDesc('voix');

            if ($entiteTable) {
                $q->leftJoin($entiteTable.' as ep', 'ep.id', '=', 'c.entite_politique_id')
                  ->addSelect('ep.nom as entite_nom', 'ep.sigle as entite_sigle');
            }

            return [
                'election_id' => $electionId,
                'circonscription_id' => $circonscriptionId,
                'items' => $q->get(),
            ];

        } catch (\Exception $e) {
            Log::error("LegislativeDashboardService::getResultatCirconscription ERROR", [
                'election_id' => $electionId,
                'circonscription_id' => $circonscriptionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'election_id' => $electionId,
                'circonscription_id' => $circonscriptionId,
                'items' => [],
            ];
        }
    }
}