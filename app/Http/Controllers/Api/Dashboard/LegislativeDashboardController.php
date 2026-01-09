<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use App\Services\Dashboard\LegislativeDashboardService;
use Illuminate\Support\Facades\Log;

/**
 * ═══════════════════════════════════════════════════════════════
 * LEGISLATIVE DASHBOARD CONTROLLER - VERSION CORRIGÉE ET HARMONISÉE
 * ═══════════════════════════════════════════════════════════════
 * 
 * AMÉLIORATIONS :
 * ✅ Utilisation prioritaire de X-Election-Id (header injecté par le front)
 * ✅ Réponses JSON harmonisées avec ApiEnvelope
 * ✅ Gestion d'erreurs cohérente et informative
 * ✅ Logs détaillés pour le debugging
 * ✅ Validation systématique de l'election_id
 * 
 * @package App\Http\Controllers\Api\Dashboard
 * @version 2.0.0
 */
class LegislativeDashboardController extends Controller
{
    public function __construct(
        private LegislativeDashboardService $dashboardService
    ) {}

    /**
     * ═══════════════════════════════════════════════════════════════
     * MÉTHODE HELPER : Récupération de l'ID d'élection
     * ═══════════════════════════════════════════════════════════════
     * 
     * Priorité de récupération :
     * 1. request->attributes (set par middleware CheckElectionAccess)
     * 2. Header X-Election-Id (injecté automatiquement par le front Next.js)
     * 3. Query parameter election_id (fallback pour compatibilité)
     */
    private function getElectionId(Request $request): ?int
    {
        // Priorité 1 : Depuis les attributes (middleware)
        $id = $request->attributes->get('active_election_id');
        
        // Priorité 2 : Header X-Election-Id (standard front Next.js)
        if (!$id) {
            $id = $request->header('X-Election-Id');
        }
        
        // Priorité 3 : Query parameter (fallback)
        if (!$id) {
            $id = $request->input('election_id');
        }

        return $id ? (int) $id : null;
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * MÉTHODE HELPER : Réponse JSON standardisée (succès)
     * ═══════════════════════════════════════════════════════════════
     */
    private function successResponse(array $data, string $message = 'Opération réussie', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * MÉTHODE HELPER : Réponse JSON standardisée (erreur)
     * ═══════════════════════════════════════════════════════════════
     */
    private function errorResponse(string $message, int $status = 500, ?string $error = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($error && config('app.debug')) {
            $response['error'] = $error;
        }

        return response()->json($response, $status);
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * ENDPOINTS PRINCIPAUX
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * GET /api/v1/dashboard/legislative
     * Statistiques globales du dashboard législatif
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse(
                    'ID d\'élection non fourni. Veuillez sélectionner une élection active.',
                    400
                );
            }

            $data = $this->dashboardService->getStats($electionId);

            return $this->successResponse(
                $data,
                'Statistiques globales récupérées avec succès'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Stats Error", [
                'election_id' => $electionId ?? 'N/A',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération des statistiques',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * GET /api/v1/dashboard/legislative/participation
     * Données de participation électorale
     */
    public function participation(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getParticipation($electionId);

            return $this->successResponse(
                $data,
                'Données de participation récupérées'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Participation Error", [
                'election_id' => $electionId ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération de la participation',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * GET /api/v1/dashboard/legislative/avancement
     * Avancement du dépouillement
     */
    public function avancement(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getAvancementDepouillement($electionId);

            return $this->successResponse(
                $data,
                'Avancement du dépouillement récupéré'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Avancement Error", [
                'election_id' => $electionId ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération de l\'avancement',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * GET /api/v1/dashboard/legislative/vitesse-saisie
     * Vitesse de saisie des PV
     */
    public function vitesseSaisie(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getVitesseSaisie($electionId);

            return $this->successResponse(
                $data,
                'Vitesse de saisie récupérée'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Vitesse Saisie Error", [
                'election_id' => $electionId ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération de la vitesse de saisie',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * EXPLORATEUR GÉOGRAPHIQUE
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * GET /api/v1/dashboard/legislative/vue-nationale
     * Vue nationale (statistiques par département)
     */
    public function vueNationale(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getVueNationale($electionId);

            return $this->successResponse(
                $data,
                'Vue nationale récupérée'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Vue Nationale Error", [
                'election_id' => $electionId ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération de la vue nationale',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * GET /api/v1/dashboard/legislative/vue-departement/{departementId}
     * Vue département (statistiques par circonscription)
     */
    public function vueDepartement(Request $request, int $departementId): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getVueDepartement($electionId, $departementId);

            return $this->successResponse(
                $data,
                "Vue département {$departementId} récupérée"
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Vue Departement Error", [
                'election_id' => $electionId ?? 'N/A',
                'departement_id' => $departementId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération de la vue département',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * GET /api/v1/dashboard/legislative/palmares-departements
     * Palmarès des départements (classement par taux de participation)
     */
    public function palmaresDepartements(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getPalmaresDepartements($electionId);

            return $this->successResponse(
                $data,
                'Palmarès des départements récupéré'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Palmares Error", [
                'election_id' => $electionId ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération du palmarès',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * KPIs LÉGISLATIVES (SEUILS 10% ET 20%)
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * GET /api/v1/dashboard/legislative/barometre-10
     * Baromètre 10% (seuil national d'éligibilité)
     */
    public function barometre10(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getBarometre10Pourcent($electionId);

            return $this->successResponse(
                $data,
                'Baromètre 10% récupéré avec succès'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Barometre 10% Error", [
                'election_id' => $electionId ?? 'N/A',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération du baromètre 10%',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * GET /api/v1/dashboard/legislative/matrice-20
     * Matrice 20% (seuil par circonscription)
     */
    public function matrice20(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getMatrice20Pourcent($electionId);

            return $this->successResponse(
                $data,
                'Matrice 20% récupérée avec succès'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Matrice 20% Error", [
                'election_id' => $electionId ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération de la matrice 20%',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * GET /api/v1/dashboard/legislative/hemicycle
     * Hémicycle : répartition des 109 sièges
     */
    public function hemicycle(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getHemicycle($electionId);

            return $this->successResponse(
                $data,
                'Répartition de l\'hémicycle récupérée'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Hemicycle Error", [
                'election_id' => $electionId ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération de l\'hémicycle',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * MODULE CONFRONTATION (ANOMALIES & DIVERGENCES)
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * GET /api/v1/dashboard/legislative/radar-divergence
     * Radar de divergence (PV litigieux et anomalies)
     */
    public function radarDivergence(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getRadarDivergence($electionId);

            return $this->successResponse(
                $data,
                'Radar de divergence récupéré'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Radar Divergence Error", [
                'election_id' => $electionId ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération du radar de divergence',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * GET /api/v1/dashboard/legislative/flux-anomalies
     * Flux des anomalies récentes
     */
    public function fluxAnomalies(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $limit = $request->input('limit', 20);
            $data = $this->dashboardService->getFluxAnomalies($electionId, $limit);

            return $this->successResponse(
                $data,
                'Flux des anomalies récupéré'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Flux Anomalies Error", [
                'election_id' => $electionId ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération des anomalies',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * MÉTHODES COMPLÉMENTAIRES ET ANALYSES DÉTAILLÉES
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * GET /api/v1/dashboard/legislative/statistiques-partis
     * Statistiques détaillées par parti
     */
    public function statistiquesPartis(Request $request): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getStatistiquesPartis($electionId);

            return $this->successResponse(
                $data,
                'Statistiques des partis récupérées'
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Statistiques Partis Error", [
                'election_id' => $electionId ?? 'N/A',
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération des statistiques des partis',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * GET /api/v1/dashboard/legislative/circonscription/{circonscriptionId}
     * Résultats détaillés d'une circonscription
     */
    public function resultatCirconscription(Request $request, int $circonscriptionId): JsonResponse
    {
        try {
            $electionId = $this->getElectionId($request);

            if (!$electionId) {
                return $this->errorResponse('ID d\'élection manquant', 400);
            }

            $data = $this->dashboardService->getResultatCirconscription($electionId, $circonscriptionId);

            return $this->successResponse(
                $data,
                "Résultats de la circonscription {$circonscriptionId} récupérés"
            );

        } catch (\Exception $e) {
            Log::error("Legislative Dashboard - Resultat Circonscription Error", [
                'election_id' => $electionId ?? 'N/A',
                'circonscription_id' => $circonscriptionId,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Erreur lors de la récupération des résultats de la circonscription',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════
     * MÉTHODES ALIAS POUR COMPATIBILITÉ AVEC LE FRONTEND
     * ═══════════════════════════════════════════════════════════════
     */

    /**
     * Alias pour stats() - compatibilité
     */
    public function resultats(Request $request): JsonResponse
    {
        return $this->stats($request);
    }

    /**
     * Alias pour avancement() - compatibilité
     */
    public function progression(Request $request): JsonResponse
    {
        return $this->avancement($request);
    }

    /**
     * Alias pour participation() - compatibilité
     */
    public function tauxParticipation(Request $request): JsonResponse
    {
        return $this->participation($request);
    }

    /**
     * Alias pour hemicycle() - compatibilité
     */
    public function repartitionSieges(Request $request): JsonResponse
    {
        return $this->hemicycle($request);
    }
}