<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

/**
 * ResultatController
 * 
 * Gestion des résultats (saisie, comparaison, validation)
 */
class ResultatController extends Controller
{
    /**
     * Saisie d'un résultat
     * 
     * POST /api/v1/resultats/saisie
     * 
     * @OA\Post(
     *     path="/resultats/saisie",
     *     tags={"Résultats"},
     *     summary="Saisir un résultat",
     *     description="Enregistre le résultat d'une candidature pour un PV spécifique",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"proces_verbal_id","candidature_id","nombre_voix"},
     *             @OA\Property(property="proces_verbal_id", type="integer", example=1, description="ID du PV"),
     *             @OA\Property(property="candidature_id", type="integer", example=1, description="ID de la candidature"),
     *             @OA\Property(property="nombre_voix", type="integer", minimum=0, example=150, description="Nombre de voix obtenues"),
     *             @OA\Property(property="version", type="integer", minimum=1, example=1, description="Numéro de version (défaut: 1)"),
     *             @OA\Property(property="operateur_user_id", type="integer", example=1, description="ID de l'opérateur saisissant")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Résultat saisi avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Résultat saisi avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function saisie(Request $request): JsonResponse
    {
        // ✅ CORRECTION: Validation avec les bonnes colonnes
        $validated = $request->validate([
            'proces_verbal_id' => 'required|integer|exists:proces_verbaux,id',
            'candidature_id' => 'required|integer|exists:candidatures,id',
            'nombre_voix' => 'required|integer|min:0',
            'version' => 'sometimes|integer|min:1',
            'operateur_user_id' => 'nullable|integer|exists:users,id',
        ]);

        // Version par défaut = 1
        if (!isset($validated['version'])) {
            $validated['version'] = 1;
        }

        $validated['date_saisie'] = now();
        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        // ✅ CORRECTION: Table 'resultats' (pas 'resultats_pv')
        $resultatId = DB::table('resultats')->insertGetId($validated);

        $resultat = DB::table('resultats')->where('id', $resultatId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Résultat saisi avec succès',
            'data' => $resultat,
        ], 201);
    }

    /**
     * Saisie multiple (tous les résultats d'un PV)
     * 
     * POST /api/v1/resultats/saisie-multiple
     * 
     * @OA\Post(
     *     path="/resultats/saisie-multiple",
     *     tags={"Résultats"},
     *     summary="Saisir plusieurs résultats",
     *     description="Enregistre tous les résultats d'un PV en une seule opération (saisie complète)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"proces_verbal_id","resultats"},
     *             @OA\Property(property="proces_verbal_id", type="integer", example=1, description="ID du PV"),
     *             @OA\Property(property="version", type="integer", minimum=1, example=1, description="Numéro de version (défaut: 1)"),
     *             @OA\Property(property="operateur_user_id", type="integer", example=1, description="ID de l'opérateur"),
     *             @OA\Property(
     *                 property="resultats",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"candidature_id","nombre_voix"},
     *                     @OA\Property(property="candidature_id", type="integer", example=1),
     *                     @OA\Property(property="nombre_voix", type="integer", minimum=0, example=150)
     *                 ),
     *                 example={
     *                     {"candidature_id": 1, "nombre_voix": 150},
     *                     {"candidature_id": 2, "nombre_voix": 75},
     *                     {"candidature_id": 3, "nombre_voix": 50}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Résultats saisis avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Résultats saisis avec succès"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="count", type="integer", example=3),
     *             @OA\Property(property="version", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=400, description="Des résultats existent déjà pour cette version"),
     *     @OA\Response(response=404, description="PV non trouvé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function saisieMultiple(Request $request): JsonResponse
    {
        // ✅ CORRECTION: Validation avec les bonnes colonnes
        $validated = $request->validate([
            'proces_verbal_id' => 'required|integer|exists:proces_verbaux,id',
            'version' => 'sometimes|integer|min:1',
            'operateur_user_id' => 'nullable|integer|exists:users,id',
            'resultats' => 'required|array|min:1',
            'resultats.*.candidature_id' => 'required|integer|exists:candidatures,id',
            'resultats.*.nombre_voix' => 'required|integer|min:0',
        ]);

        // Version par défaut = 1
        $version = $validated['version'] ?? 1;

        // Vérifier que le PV existe
        $pv = DB::table('proces_verbaux')->where('id', $validated['proces_verbal_id'])->first();

        if (!$pv) {
            return response()->json([
                'success' => false,
                'message' => 'PV non trouvé',
            ], 404);
        }

        // Vérifier qu'il n'y a pas déjà des résultats pour cette version
        $existants = DB::table('resultats')
            ->where('proces_verbal_id', $validated['proces_verbal_id'])
            ->where('version', $version)
            ->count();

        if ($existants > 0) {
            return response()->json([
                'success' => false,
                'message' => "Des résultats existent déjà pour cette version ($version). Utilisez une version différente ou supprimez les résultats existants.",
            ], 400);
        }

        $resultatsInseres = [];

        // ✅ CORRECTION: Utiliser 'nombre_voix' au lieu de 'voix'
        foreach ($validated['resultats'] as $resultatData) {
            $data = [
                'proces_verbal_id' => $validated['proces_verbal_id'],
                'candidature_id' => $resultatData['candidature_id'],
                'nombre_voix' => $resultatData['nombre_voix'],
                'version' => $version,
                'operateur_user_id' => $validated['operateur_user_id'] ?? null,
                'date_saisie' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // ✅ CORRECTION: Table 'resultats' (pas 'resultats_pv')
            $resultatId = DB::table('resultats')->insertGetId($data);
            $resultatsInseres[] = DB::table('resultats')->where('id', $resultatId)->first();
        }

        // ✅ CORRECTION: Statut 'en_verification' (pas 'en_attente')
        DB::table('proces_verbaux')->where('id', $validated['proces_verbal_id'])->update([
            'statut' => 'en_verification',
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Résultats saisis avec succès',
            'data' => $resultatsInseres,
            'count' => count($resultatsInseres),
            'version' => $version,
        ], 201);
    }

    /**
     * Comparaison des saisies multiples (par version)
     * 
     * GET /api/v1/resultats/comparaison/{pvId}
     * 
     * @OA\Get(
     *     path="/resultats/comparaison/{pvId}",
     *     tags={"Résultats"},
     *     summary="Comparer les versions de saisie",
     *     description="Compare les différentes versions de saisie d'un PV pour détecter les divergences",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="pvId",
     *         in="path",
     *         description="ID du procès-verbal",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Comparaison des versions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="pv_id", type="integer", example=1),
     *                 @OA\Property(property="versions", type="array", @OA\Items(type="string"), example={"version_1", "version_2"}),
     *                 @OA\Property(property="nb_versions", type="integer", example=2),
     *                 @OA\Property(
     *                     property="resultats",
     *                     type="object",
     *                     description="Résultats groupés par version"
     *                 ),
     *                 @OA\Property(
     *                     property="differences",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="entite", type="string"),
     *                         @OA\Property(property="version_1", type="integer"),
     *                         @OA\Property(property="version_2", type="integer"),
     *                         @OA\Property(property="ecart", type="integer")
     *                     )
     *                 ),
     *                 @OA\Property(property="nb_differences", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="PV non trouvé"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function comparaison(int $pvId): JsonResponse
    {
        $pv = DB::table('proces_verbaux')->where('id', $pvId)->first();

        if (!$pv) {
            return response()->json([
                'success' => false,
                'message' => 'PV non trouvé',
            ], 404);
        }

        // ✅ CORRECTION: Table 'resultats' + colonne 'nombre_voix'
        $resultatsParVersion = DB::table('resultats as r')
            ->join('candidatures as c', 'r.candidature_id', '=', 'c.id')
            ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
            ->where('r.proces_verbal_id', $pvId)
            ->select(
                'r.version',
                'c.numero_liste',
                'ep.nom as entite',
                'ep.sigle',
                'r.nombre_voix'
            )
            ->orderBy('r.version')
            ->orderBy('c.numero_liste')
            ->get();

        // Grouper par version
        $comparaison = [];
        foreach ($resultatsParVersion as $resultat) {
            $comparaison['version_' . $resultat->version][] = [
                'numero_liste' => $resultat->numero_liste,
                'entite' => $resultat->entite,
                'sigle' => $resultat->sigle,
                'nombre_voix' => $resultat->nombre_voix,
            ];
        }

        // Détecter les différences entre versions
        $differences = [];
        $versions = array_keys($comparaison);

        if (count($versions) > 1) {
            $version1 = $versions[0];
            $version2 = $versions[1];

            for ($i = 0; $i < count($comparaison[$version1]); $i++) {
                if (isset($comparaison[$version2][$i]) && 
                    $comparaison[$version1][$i]['nombre_voix'] !== $comparaison[$version2][$i]['nombre_voix']) {
                    
                    $differences[] = [
                        'entite' => $comparaison[$version1][$i]['entite'],
                        $version1 => $comparaison[$version1][$i]['nombre_voix'],
                        $version2 => $comparaison[$version2][$i]['nombre_voix'],
                        'ecart' => abs($comparaison[$version1][$i]['nombre_voix'] - $comparaison[$version2][$i]['nombre_voix']),
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'pv_id' => $pvId,
                'versions' => $versions,
                'nb_versions' => count($versions),
                'resultats' => $comparaison,
                'differences' => $differences,
                'nb_differences' => count($differences),
            ],
        ]);
    }

    /**
     * Validation des résultats d'un PV
     * 
     * POST /api/v1/resultats/validation/{pvId}
     * 
     * @OA\Post(
     *     path="/resultats/validation/{pvId}",
     *     tags={"Résultats"},
     *     summary="Valider les résultats d'un PV",
     *     description="Valide une version spécifique des résultats et supprime les autres versions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="pvId",
     *         in="path",
     *         description="ID du procès-verbal",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"version_validee"},
     *             @OA\Property(property="version_validee", type="integer", minimum=1, example=1, description="Numéro de la version à valider"),
     *             @OA\Property(property="valide_par_user_id", type="integer", example=1, description="ID de l'utilisateur validant")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Résultats validés avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Résultats validés avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="pv_id", type="integer", example=1),
     *                 @OA\Property(property="version_validee", type="integer", example=1),
     *                 @OA\Property(property="resultats", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="count", type="integer", example=8)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="PV ou version non trouvée"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function validation(Request $request, int $pvId): JsonResponse
    {
        // ✅ CORRECTION: Valider la version à garder
        $validated = $request->validate([
            'version_validee' => 'required|integer|min:1',
            'valide_par_user_id' => 'sometimes|integer|exists:users,id',
        ]);

        $pv = DB::table('proces_verbaux')->where('id', $pvId)->first();

        if (!$pv) {
            return response()->json([
                'success' => false,
                'message' => 'PV non trouvé',
            ], 404);
        }

        // Vérifier que la version existe
        $versionExiste = DB::table('resultats')
            ->where('proces_verbal_id', $pvId)
            ->where('version', $validated['version_validee'])
            ->exists();

        if (!$versionExiste) {
            return response()->json([
                'success' => false,
                'message' => "La version {$validated['version_validee']} n'existe pas pour ce PV",
            ], 404);
        }

        // ✅ CORRECTION: Supprimer les autres versions
        DB::table('resultats')
            ->where('proces_verbal_id', $pvId)
            ->where('version', '!=', $validated['version_validee'])
            ->delete();

        // ✅ CORRECTION: Marquer le PV comme validé
        $updateData = [
            'statut' => 'valide',
            'date_validation' => now(),
            'updated_at' => now(),
        ];

        if (isset($validated['valide_par_user_id'])) {
            $updateData['valide_par_user_id'] = $validated['valide_par_user_id'];
        }

        DB::table('proces_verbaux')->where('id', $pvId)->update($updateData);

        // ✅ CORRECTION: Table 'resultats'
        $resultatsValides = DB::table('resultats')
            ->where('proces_verbal_id', $pvId)
            ->where('version', $validated['version_validee'])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Résultats validés avec succès',
            'data' => [
                'pv_id' => $pvId,
                'version_validee' => $validated['version_validee'],
                'resultats' => $resultatsValides,
                'count' => $resultatsValides->count(),
            ],
        ]);
    }

    /**
     * Historique des modifications d'un résultat
     * 
     * GET /api/v1/resultats/historique/{resultatId}
     * 
     * @OA\Get(
     *     path="/resultats/historique/{resultatId}",
     *     tags={"Résultats"},
     *     summary="Historique d'un résultat",
     *     description="Retourne l'historique complet des modifications d'un résultat spécifique",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="resultatId",
     *         in="path",
     *         description="ID du résultat",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Historique du résultat",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="resultat_actuel", type="object", description="État actuel du résultat"),
     *                 @OA\Property(
     *                     property="historique",
     *                     type="array",
     *                     @OA\Items(type="object"),
     *                     description="Liste des modifications passées"
     *                 ),
     *                 @OA\Property(property="nb_modifications", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Résultat non trouvé"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function historique(int $resultatId): JsonResponse
    {
        // ✅ CORRECTION: Table 'resultats'
        $resultat = DB::table('resultats')->where('id', $resultatId)->first();

        if (!$resultat) {
            return response()->json([
                'success' => false,
                'message' => 'Résultat non trouvé',
            ], 404);
        }

        // Récupérer l'historique depuis la table resultats_historique
        $historique = DB::table('resultats_historique')
            ->where('resultat_id', $resultatId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'resultat_actuel' => $resultat,
                'historique' => $historique,
                'nb_modifications' => $historique->count(),
            ],
        ]);
    }

    /**
     * Supprimer tous les résultats d'un PV pour une version donnée
     * 
     * DELETE /api/v1/resultats/pv/{pvId}/version/{version}
     * 
     * @OA\Delete(
     *     path="/resultats/pv/{pvId}/version/{version}",
     *     tags={"Résultats"},
     *     summary="Supprimer une version de saisie",
     *     description="Supprime tous les résultats d'une version spécifique d'un PV",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="pvId",
     *         in="path",
     *         description="ID du procès-verbal",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="version",
     *         in="path",
     *         description="Numéro de la version à supprimer",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Version supprimée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Résultats de la version 2 supprimés avec succès"),
     *             @OA\Property(property="nb_supprimes", type="integer", example=8)
     *         )
     *     ),
     *     @OA\Response(response=404, description="PV non trouvé ou aucun résultat pour cette version"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function supprimerVersion(int $pvId, int $version): JsonResponse
    {
        $pv = DB::table('proces_verbaux')->where('id', $pvId)->first();

        if (!$pv) {
            return response()->json([
                'success' => false,
                'message' => 'PV non trouvé',
            ], 404);
        }

        // Supprimer les résultats de cette version
        $deleted = DB::table('resultats')
            ->where('proces_verbal_id', $pvId)
            ->where('version', $version)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => "Aucun résultat trouvé pour la version $version",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => "Résultats de la version $version supprimés avec succès",
            'nb_supprimes' => $deleted,
        ]);
    }

    /**
     * Obtenir les résultats d'un PV (toutes versions)
     * 
     * GET /api/v1/resultats/pv/{pvId}
     * 
     * @OA\Get(
     *     path="/resultats/pv/{pvId}",
     *     tags={"Résultats"},
     *     summary="Résultats d'un PV",
     *     description="Retourne tous les résultats d'un PV avec toutes les versions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="pvId",
     *         in="path",
     *         description="ID du procès-verbal",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Résultats du PV",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="pv", type="object", description="Informations du PV"),
     *                 @OA\Property(
     *                     property="resultats",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="version", type="integer"),
     *                         @OA\Property(property="numero_liste", type="integer"),
     *                         @OA\Property(property="entite", type="string"),
     *                         @OA\Property(property="sigle", type="string"),
     *                         @OA\Property(property="nombre_voix", type="integer"),
     *                         @OA\Property(property="date_saisie", type="string", format="date-time"),
     *                         @OA\Property(property="operateur_user_id", type="integer", nullable=true)
     *                     )
     *                 ),
     *                 @OA\Property(property="count", type="integer", example=16, description="Nombre total de résultats (toutes versions)")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="PV non trouvé"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function getResultatsPv(int $pvId): JsonResponse
    {
        $pv = DB::table('proces_verbaux')->where('id', $pvId)->first();

        if (!$pv) {
            return response()->json([
                'success' => false,
                'message' => 'PV non trouvé',
            ], 404);
        }

        $resultats = DB::table('resultats as r')
            ->join('candidatures as c', 'r.candidature_id', '=', 'c.id')
            ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
            ->where('r.proces_verbal_id', $pvId)
            ->select(
                'r.id',
                'r.version',
                'c.numero_liste',
                'ep.nom as entite',
                'ep.sigle',
                'r.nombre_voix',
                'r.date_saisie',
                'r.operateur_user_id'
            )
            ->orderBy('r.version')
            ->orderBy('c.numero_liste')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pv' => $pv,
                'resultats' => $resultats,
                'count' => $resultats->count(),
            ],
        ]);
    }
}