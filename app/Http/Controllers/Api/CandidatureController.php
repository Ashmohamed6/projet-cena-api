<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

/**
 * CandidatureController
 * 
 * Gestion des candidatures
 */
class CandidatureController extends Controller
{
    /**
     * Liste des candidatures
     * 
     * GET /api/v1/candidatures
     * 
     * @OA\Get(
     *     path="/candidatures",
     *     tags={"Candidatures"},
     *     summary="Liste des candidatures",
     *     description="Retourne la liste des candidatures avec possibilité de filtrage par élection et statut",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="election_id",
     *         in="query",
     *         description="ID de l'élection pour filtrer",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="statut",
     *         in="query",
     *         description="Statut de la candidature",
     *         required=false,
     *         @OA\Schema(type="string", enum={"enregistree", "validee", "rejetee"}, example="validee")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des candidatures",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="numero_liste", type="integer", example=1),
     *                     @OA\Property(property="statut", type="string", example="validee"),
     *                     @OA\Property(property="election", type="string", example="Élections Législatives 2026"),
     *                     @OA\Property(property="entite_nom", type="string", example="Bloc Républicain"),
     *                     @OA\Property(property="sigle", type="string", example="BR"),
     *                     @OA\Property(property="type", type="string", example="Parti politique")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=8)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('candidatures as c')
            ->join('elections as e', 'c.election_id', '=', 'e.id')
            ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
            ->select(
                'c.id',
                'c.numero_liste',
                'c.statut',
                'e.nom as election',
                'ep.nom as entite_nom',
                'ep.sigle',
                'ep.type',
                'c.created_at'
            );

        // Filtrer par élection si spécifié
        if ($request->has('election_id')) {
            $query->where('c.election_id', $request->election_id);
        }

        // Filtrer par statut si spécifié
        if ($request->has('statut')) {
            $query->where('c.statut', $request->statut);
        }

        $candidatures = $query->orderBy('c.numero_liste')->get();

        return response()->json([
            'success' => true,
            'data' => $candidatures,
            'count' => $candidatures->count(),
        ]);
    }

    /**
     * Créer une candidature
     * 
     * POST /api/v1/candidatures
     * 
     * @OA\Post(
     *     path="/candidatures",
     *     tags={"Candidatures"},
     *     summary="Créer une candidature",
     *     description="Enregistre une nouvelle candidature pour une élection",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"election_id","entite_politique_id","numero_liste"},
     *             @OA\Property(property="election_id", type="integer", example=1, description="ID de l'élection"),
     *             @OA\Property(property="entite_politique_id", type="integer", example=1, description="ID du parti politique ou entité"),
     *             @OA\Property(property="circonscription_id", type="integer", nullable=true, example=1, description="ID de la circonscription (optionnel)"),
     *             @OA\Property(property="numero_liste", type="integer", example=1, description="Numéro de la liste électorale"),
     *             @OA\Property(property="statut", type="string", enum={"enregistree", "validee", "rejetee"}, example="enregistree")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Candidature créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Candidature créée avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="numero_liste", type="integer", example=1),
     *                 @OA\Property(property="statut", type="string", example="enregistree")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'election_id' => 'required|integer|exists:elections,id',
            'entite_politique_id' => 'required|integer|exists:entites_politiques,id',
            'circonscription_id' => 'nullable|integer|exists:circonscriptions_electorales,id',
            'numero_liste' => 'required|integer',
            'statut' => 'sometimes|string|in:enregistree,validee,rejetee',
        ]);

        $validated['statut'] = $validated['statut'] ?? 'enregistree';
        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        $candidatureId = DB::table('candidatures')->insertGetId($validated);

        $candidature = DB::table('candidatures')->where('id', $candidatureId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Candidature créée avec succès',
            'data' => $candidature,
        ], 201);
    }

    /**
     * Détail d'une candidature
     * 
     * GET /api/v1/candidatures/{id}
     * 
     * @OA\Get(
     *     path="/candidatures/{id}",
     *     tags={"Candidatures"},
     *     summary="Détail d'une candidature",
     *     description="Retourne les informations complètes d'une candidature spécifique",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la candidature",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de la candidature",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="numero_liste", type="integer", example=1),
     *                 @OA\Property(property="statut", type="string", example="validee"),
     *                 @OA\Property(property="election", type="string", example="Élections Législatives 2026"),
     *                 @OA\Property(property="election_code", type="string", example="LEG2026"),
     *                 @OA\Property(property="entite_nom", type="string", example="Bloc Républicain"),
     *                 @OA\Property(property="sigle", type="string", example="BR"),
     *                 @OA\Property(property="type", type="string", example="Parti politique"),
     *                 @OA\Property(property="circonscription", type="string", nullable=true, example="Alibori 1ère circonscription")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Candidature non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Candidature non trouvée")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $candidature = DB::table('candidatures as c')
            ->join('elections as e', 'c.election_id', '=', 'e.id')
            ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
            ->leftJoin('circonscriptions_electorales as circ', 'c.circonscription_id', '=', 'circ.id')
            ->where('c.id', $id)
            ->select(
                'c.*',
                'e.nom as election',
                'e.code as election_code',
                'ep.nom as entite_nom',
                'ep.sigle',
                'ep.type',
                'circ.nom as circonscription'
            )
            ->first();

        if (!$candidature) {
            return response()->json([
                'success' => false,
                'message' => 'Candidature non trouvée',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $candidature,
        ]);
    }

    /**
     * Mettre à jour une candidature
     * 
     * PUT /api/v1/candidatures/{id}
     * 
     * @OA\Put(
     *     path="/candidatures/{id}",
     *     tags={"Candidatures"},
     *     summary="Mettre à jour une candidature",
     *     description="Modifie les informations d'une candidature existante",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la candidature",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="numero_liste", type="integer", example=2, description="Nouveau numéro de liste"),
     *             @OA\Property(property="statut", type="string", enum={"enregistree", "validee", "rejetee"}, example="validee")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Candidature mise à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Candidature mise à jour avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Candidature non trouvée"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $candidature = DB::table('candidatures')->where('id', $id)->first();

        if (!$candidature) {
            return response()->json([
                'success' => false,
                'message' => 'Candidature non trouvée',
            ], 404);
        }

        $validated = $request->validate([
            'numero_liste' => 'sometimes|integer',
            'statut' => 'sometimes|string|in:enregistree,validee,rejetee',
        ]);

        $validated['updated_at'] = now();

        DB::table('candidatures')->where('id', $id)->update($validated);

        $updatedCandidature = DB::table('candidatures')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Candidature mise à jour avec succès',
            'data' => $updatedCandidature,
        ]);
    }

    /**
     * Supprimer une candidature
     * 
     * DELETE /api/v1/candidatures/{id}
     * 
     * @OA\Delete(
     *     path="/candidatures/{id}",
     *     tags={"Candidatures"},
     *     summary="Supprimer une candidature",
     *     description="Supprime définitivement une candidature du système",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la candidature",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Candidature supprimée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Candidature supprimée avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Candidature non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Candidature non trouvée")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $candidature = DB::table('candidatures')->where('id', $id)->first();

        if (!$candidature) {
            return response()->json([
                'success' => false,
                'message' => 'Candidature non trouvée',
            ], 404);
        }

        DB::table('candidatures')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Candidature supprimée avec succès',
        ]);
    }

    /**
     * Valider une candidature
     * 
     * POST /api/v1/candidatures/{id}/valider
     * 
     * @OA\Post(
     *     path="/candidatures/{id}/valider",
     *     tags={"Candidatures"},
     *     summary="Valider une candidature",
     *     description="Change le statut d'une candidature à 'validee'",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la candidature",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Candidature validée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Candidature validée avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="statut", type="string", example="validee")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Candidature non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Candidature non trouvée")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function valider(int $id): JsonResponse
    {
        $candidature = DB::table('candidatures')->where('id', $id)->first();

        if (!$candidature) {
            return response()->json([
                'success' => false,
                'message' => 'Candidature non trouvée',
            ], 404);
        }

        DB::table('candidatures')->where('id', $id)->update([
            'statut' => 'validee',
            'updated_at' => now(),
        ]);

        $updatedCandidature = DB::table('candidatures')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Candidature validée avec succès',
            'data' => $updatedCandidature,
        ]);
    }

    /**
     * Rejeter une candidature
     * 
     * POST /api/v1/candidatures/{id}/rejeter
     * 
     * @OA\Post(
     *     path="/candidatures/{id}/rejeter",
     *     tags={"Candidatures"},
     *     summary="Rejeter une candidature",
     *     description="Change le statut d'une candidature à 'rejetee' avec motif de rejet",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la candidature",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"motif_rejet"},
     *             @OA\Property(
     *                 property="motif_rejet",
     *                 type="string",
     *                 maxLength=500,
     *                 example="Documents incomplets",
     *                 description="Motif du rejet de la candidature"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Candidature rejetée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Candidature rejetée avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="statut", type="string", example="rejetee")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Candidature non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Candidature non trouvée")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation - Motif requis"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function rejeter(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'motif_rejet' => 'required|string|max:500',
        ]);

        $candidature = DB::table('candidatures')->where('id', $id)->first();

        if (!$candidature) {
            return response()->json([
                'success' => false,
                'message' => 'Candidature non trouvée',
            ], 404);
        }

        DB::table('candidatures')->where('id', $id)->update([
            'statut' => 'rejetee',
            'updated_at' => now(),
        ]);

        $updatedCandidature = DB::table('candidatures')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Candidature rejetée avec succès',
            'data' => $updatedCandidature,
        ]);
    }
}