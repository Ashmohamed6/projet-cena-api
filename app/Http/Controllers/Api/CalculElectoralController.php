<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Electoral\CalculElectoralService;
use App\Services\Electoral\Strategies\{StandardLegislativeCalculator, CenaOfficialCalculator};
use Illuminate\Http\{Request, JsonResponse};

/**
 * @OA\Tag(
 * name="Calculs Électoraux",
 * description="Algorithmes de répartition des sièges (Standard et CENA)"
 * )
 */
class CalculElectoralController extends Controller
{
    /**
     * Service de calcul électoral
     */
    private CalculElectoralService $calculService;

    /**
     * Constructeur avec injection de dépendance
     */
    public function __construct(CalculElectoralService $calculService)
    {
        $this->calculService = $calculService;
    }

    /**
     * @OA\Post(
     * path="/api/v1/calculs/repartition-sieges",
     * operationId="calculerRepartitionSieges",
     * tags={"Calculs Électoraux"},
     * summary="Calculer la répartition des sièges",
     * description="Calcule la répartition des sièges pour une circonscription selon la stratégie sélectionnée",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"election_id", "circonscription_id"},
     * @OA\Property(property="election_id", type="integer", example=1, description="ID de l'élection"),
     * @OA\Property(property="circonscription_id", type="integer", example=1, description="ID de la circonscription"),
     * @OA\Property(
     * property="strategy",
     * type="string",
     * enum={"standard", "cena_official"},
     * example="standard",
     * description="Stratégie de calcul (optionnel)"
     * )
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Répartition calculée",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="circonscription_id", type="integer", example=1),
     * @OA\Property(property="nombre_sieges", type="integer", example=3),
     * @OA\Property(property="quotient_electoral", type="number", format="float", example=1250.5),
     * @OA\Property(
     * property="repartition",
     * type="object",
     * description="Répartition par candidature",
     * additionalProperties=true,
     * example={
     * "1": {"sieges": 2, "voix": 2500, "reste": 0},
     * "2": {"sieges": 1, "voix": 1300, "reste": 49.5}
     * }
     * ),
     * @OA\Property(property="strategy_used", type="string", example="standard")
     * )
     * ),
     * @OA\Response(response=500, description="Erreur de calcul"),
     * @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function calculerRepartitionSieges(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|exists:elections,id',
            'circonscription_id' => 'required|exists:circonscriptions_electorales,id',
            'strategy' => 'nullable|in:standard,cena_official',
        ]);

        try {
            // Si une stratégie spécifique est demandée, l'injecter
            if (isset($validated['strategy'])) {
                $this->switchStrategy($validated['strategy']);
            }

            $resultat = $this->calculService->calculerRepartitionSieges(
                $validated['election_id'],
                $validated['circonscription_id']
            );

            return response()->json($resultat);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors du calcul de répartition',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     * path="/api/v1/calculs/repartition-nationale",
     * operationId="calculerRepartitionNationale",
     * tags={"Calculs Électoraux"},
     * summary="Calculer la répartition nationale",
     * description="Calcule la répartition des sièges au niveau national",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"election_id"},
     * @OA\Property(property="election_id", type="integer", example=1),
     * @OA\Property(property="strategy", type="string", enum={"standard", "cena_official"}, example="cena_official")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Répartition nationale calculée",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="election_id", type="integer", example=1),
     * @OA\Property(property="total_sieges", type="integer", example=109),
     * @OA\Property(
     * property="resultats_par_circonscription",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="circonscription_id", type="integer"),
     * @OA\Property(property="nom", type="string"),
     * @OA\Property(property="sieges", type="integer"),
     * @OA\Property(property="repartition", type="object")
     * )
     * ),
     * @OA\Property(property="total_par_candidature", type="object", additionalProperties=true),
     * @OA\Property(property="strategy_used", type="string", example="cena_official")
     * )
     * ),
     * @OA\Response(response=500, description="Erreur de calcul")
     * )
     */
    public function calculerRepartitionNationale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|exists:elections,id',
            'strategy' => 'nullable|in:standard,cena_official',
        ]);

        try {
            if (isset($validated['strategy'])) {
                $this->switchStrategy($validated['strategy']);
            }

            $resultat = $this->calculService->calculerRepartitionNationale(
                $validated['election_id']
            );

            return response()->json($resultat);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors du calcul national',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     * path="/api/v1/calculs/verifier-seuils/{candidatureId}",
     * operationId="verifierSeuils",
     * tags={"Calculs Électoraux"},
     * summary="Vérifier les seuils électoraux",
     * description="Vérifie si une candidature atteint les seuils requis",
     * @OA\Parameter(name="candidatureId", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Parameter(name="election_id", in="query", required=true, @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Résultat de la vérification",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="candidature_id", type="integer"),
     * @OA\Property(property="passe_seuil_national", type="boolean"),
     * @OA\Property(property="pourcentage_national", type="number", format="float"),
     * @OA\Property(property="seuil_national_requis", type="number", format="float"),
     * @OA\Property(property="passe_seuil_circonscription", type="boolean"),
     * @OA\Property(property="pourcentage_circonscription", type="number", format="float"),
     * @OA\Property(property="seuil_circonscription_requis", type="number", format="float"),
     * @OA\Property(property="eligible", type="boolean")
     * )
     * )
     * )
     */
    public function verifierSeuils(Request $request, int $candidatureId): JsonResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|exists:elections,id',
        ]);

        try {
            $resultat = $this->calculService->verifierSeuils(
                $candidatureId,
                $validated['election_id']
            );

            return response()->json($resultat);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la vérification des seuils',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     * path="/api/v1/calculs/strategy-info",
     * operationId="getStrategyInfo",
     * tags={"Calculs Électoraux"},
     * summary="Informations sur la stratégie",
     * description="Retourne les informations sur la stratégie de calcul active",
     * @OA\Response(
     * response=200,
     * description="Informations sur la stratégie",
     * @OA\JsonContent(
     * @OA\Property(property="name", type="string"),
     * @OA\Property(property="code", type="string"),
     * @OA\Property(property="description", type="string"),
     * @OA\Property(property="version", type="string"),
     * @OA\Property(property="parameters", type="object")
     * )
     * )
     * )
     */
    public function getStrategyInfo(): JsonResponse
    {
        return response()->json($this->calculService->getStrategyInfo());
    }

    /**
     * @OA\Post(
     * path="/api/v1/calculs/change-strategy",
     * operationId="changeStrategy",
     * tags={"Calculs Électoraux"},
     * summary="Changer de stratégie",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"strategy"},
     * @OA\Property(
     * property="strategy",
     * type="string",
     * enum={"standard", "cena_official"},
     * example="cena_official"
     * )
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Stratégie changée avec succès",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string"),
     * @OA\Property(property="strategy", type="object")
     * )
     * ),
     * @OA\Response(response=422, description="Stratégie invalide")
     * )
     */
    public function changeStrategy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'strategy' => 'required|in:standard,cena_official',
        ]);

        try {
            $this->switchStrategy($validated['strategy']);

            return response()->json([
                'message' => 'Stratégie changée avec succès',
                'strategy' => $this->calculService->getStrategyInfo(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors du changement de stratégie',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     * path="/api/v1/calculs/comparer-strategies",
     * operationId="comparerStrategies",
     * tags={"Calculs Électoraux"},
     * summary="Comparer les stratégies",
     * description="Compare les résultats entre la stratégie standard et CENA official",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"election_id", "circonscription_id"},
     * @OA\Property(property="election_id", type="integer", example=1),
     * @OA\Property(property="circonscription_id", type="integer", example=1)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Comparaison effectuée",
     * @OA\JsonContent(
     * @OA\Property(property="standard", type="object"),
     * @OA\Property(property="cena_official", type="object"),
     * @OA\Property(
     * property="differences",
     * type="array",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="candidature_id", type="integer"),
     * @OA\Property(property="type", type="string", enum={"sieges_different", "absent_in_cena", "absent_in_standard"}),
     * @OA\Property(property="sieges_standard", type="integer"),
     * @OA\Property(property="sieges_cena", type="integer"),
     * @OA\Property(property="ecart", type="integer")
     * )
     * ),
     * @OA\Property(property="identical", type="boolean")
     * )
     * ),
     * @OA\Response(response=500, description="Erreur de calcul")
     * )
     */
    public function comparerStrategies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|exists:elections,id',
            'circonscription_id' => 'required|exists:circonscriptions_electorales,id',
        ]);

        try {
            // Créer 2 services séparés pour la comparaison
            $serviceStandard = new CalculElectoralService(new StandardLegislativeCalculator());
            $serviceCena = new CalculElectoralService(new CenaOfficialCalculator());

            // Calculer avec stratégie standard
            $resultatStandard = $serviceStandard->calculerRepartitionSieges(
                $validated['election_id'],
                $validated['circonscription_id']
            );

            // Calculer avec stratégie CENA
            try {
                $resultatCena = $serviceCena->calculerRepartitionSieges(
                    $validated['election_id'],
                    $validated['circonscription_id']
                );
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'ne peut pas s\'appliquer')) {
                    return response()->json([
                        'warning' => 'La stratégie CENA Official n\'est pas activée',
                        'message' => 'Activez-la dans la configuration (config/electoral.php)',
                        'standard' => $resultatStandard,
                        'note' => 'Seul le résultat standard est disponible',
                    ], 200);
                }
                throw $e;
            }

            // Comparer les résultats
            $differences = $this->compareResults(
                $resultatStandard['repartition'],
                $resultatCena['repartition']
            );

            return response()->json([
                'standard' => $resultatStandard,
                'cena_official' => $resultatCena,
                'differences' => $differences,
                'identical' => empty($differences),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la comparaison des stratégies',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Basculer vers une stratégie spécifique
     */
    private function switchStrategy(string $strategyName): void
    {
        $strategy = match ($strategyName) {
            'cena_official' => new CenaOfficialCalculator(),
            'standard' => new StandardLegislativeCalculator(),
            default => new StandardLegislativeCalculator(),
        };

        $this->calculService->setStrategy($strategy);
    }

    /**
     * Comparer deux résultats de répartition
     */
    private function compareResults(array $result1, array $result2): array
    {
        $differences = [];

        foreach ($result1 as $candidatureId => $data1) {
            $data2 = $result2[$candidatureId] ?? null;

            if (!$data2) {
                $differences[] = [
                    'candidature_id' => $candidatureId,
                    'type' => 'absent_in_cena',
                    'sieges_standard' => $data1['sieges'],
                ];
                continue;
            }

            if ($data1['sieges'] !== $data2['sieges']) {
                $differences[] = [
                    'candidature_id' => $candidatureId,
                    'type' => 'sieges_different',
                    'sieges_standard' => $data1['sieges'],
                    'sieges_cena' => $data2['sieges'],
                    'ecart' => abs($data1['sieges'] - $data2['sieges']),
                ];
            }
        }

        foreach ($result2 as $candidatureId => $data2) {
            if (!isset($result1[$candidatureId])) {
                $differences[] = [
                    'candidature_id' => $candidatureId,
                    'type' => 'absent_in_standard',
                    'sieges_cena' => $data2['sieges'],
                ];
            }
        }

        return $differences;
    }
}