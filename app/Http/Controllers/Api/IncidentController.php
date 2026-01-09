<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 * name="Incidents",
 * description="Gestion des incidents et anomalies signalés pendant le processus électoral"
 * )
 */
class IncidentController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/v1/incidents",
     * operationId="getIncidentsList",
     * tags={"Incidents"},
     * summary="Liste des incidents",
     * description="Retourne la liste des incidents avec filtres optionnels",
     * @OA\Parameter(
     * name="statut",
     * in="query",
     * description="Filtrer par statut",
     * required=false,
     * @OA\Schema(type="string", enum={"ouvert", "en_cours", "resolu", "rejete", "transmis"})
     * ),
     * @OA\Parameter(
     * name="gravite",
     * in="query",
     * description="Filtrer par gravité",
     * required=false,
     * @OA\Schema(type="string", enum={"faible", "moyenne", "grave", "critique"})
     * ),
     * @OA\Parameter(
     * name="type",
     * in="query",
     * description="Filtrer par type",
     * required=false,
     * @OA\Schema(type="string", enum={"irregularite", "fraude", "violence", "dysfonctionnement", "autre"})
     * ),
     * @OA\Parameter(name="election_id", in="query", description="Filtrer par élection", @OA\Schema(type="integer")),
     * @OA\Parameter(
     * name="niveau",
     * in="query",
     * description="Filtrer par niveau géographique",
     * required=false,
     * @OA\Schema(type="string", enum={"bureau", "arrondissement", "commune", "circonscription", "national"})
     * ),
     * @OA\Response(
     * response=200,
     * description="Liste des incidents",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="array", @OA\Items(type="object")),
     * @OA\Property(property="count", type="integer")
     * )
     * )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('incidents as i')
            ->leftJoin('elections as e', 'i.election_id', '=', 'e.id')
            ->leftJoin('proces_verbaux as pv', 'i.proces_verbal_id', '=', 'pv.id')
            ->select(
                'i.id',
                'i.code',
                'i.type',
                'i.gravite',
                'i.statut',
                'i.description',
                'i.niveau',
                'i.niveau_id',
                'e.nom as election',
                'e.code as election_code',
                'pv.numero_pv as pv_numero',
                'i.date_resolution',
                'i.created_at',
                'i.updated_at'
            );

        // Filtrer par statut
        if ($request->has('statut')) {
            $query->where('i.statut', $request->statut);
        }

        // Filtrer par gravité
        if ($request->has('gravite')) {
            $query->where('i.gravite', $request->gravite);
        }

        // Filtrer par type
        if ($request->has('type')) {
            $query->where('i.type', $request->type);
        }

        // Filtrer par élection
        if ($request->has('election_id')) {
            $query->where('i.election_id', $request->election_id);
        }

        // Filtrer par niveau
        if ($request->has('niveau')) {
            $query->where('i.niveau', $request->niveau);
        }

        $incidents = $query->orderBy('i.created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $incidents,
            'count' => $incidents->count(),
        ]);
    }

    /**
     * @OA\Post(
     * path="/api/v1/incidents",
     * operationId="storeIncident",
     * tags={"Incidents"},
     * summary="Créer un incident",
     * description="Enregistre un nouvel incident ou anomalie",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"election_id", "type", "description"},
     * @OA\Property(property="code", type="string", example="INC-001"),
     * @OA\Property(property="election_id", type="integer", example=1),
     * @OA\Property(property="proces_verbal_id", type="integer", nullable=true),
     * @OA\Property(property="type", type="string", enum={"irregularite", "fraude", "violence", "dysfonctionnement", "autre"}),
     * @OA\Property(property="gravite", type="string", enum={"faible", "moyenne", "grave", "critique"}),
     * @OA\Property(property="niveau", type="string", enum={"bureau", "arrondissement", "commune", "circonscription", "national"}),
     * @OA\Property(property="niveau_id", type="integer", nullable=true),
     * @OA\Property(property="description", type="string"),
     * @OA\Property(property="pieces_jointes", type="array", @OA\Items(type="string")),
     * @OA\Property(property="rapporte_par_user_id", type="integer")
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Incident créé avec succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string"),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     * @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:incidents,code',
            'election_id' => 'required|integer|exists:elections,id',
            'proces_verbal_id' => 'nullable|integer|exists:proces_verbaux,id',
            'type' => 'required|string|in:irregularite,fraude,violence,dysfonctionnement,autre',
            'gravite' => 'nullable|string|in:faible,moyenne,grave,critique',
            'niveau' => 'nullable|string|in:bureau,arrondissement,commune,circonscription,national',
            'niveau_id' => 'nullable|integer',
            'description' => 'required|string',
            'pieces_jointes' => 'nullable|array',
            'rapporte_par_user_id' => 'nullable|integer|exists:users,id',
        ]);

        // Générer un code si non fourni
        if (!isset($validated['code'])) {
            $validated['code'] = 'INC-' . strtoupper(uniqid());
        }

        // Statut par défaut
        $validated['statut'] = 'ouvert';
        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        // Convertir pieces_jointes en JSON si présent
        if (isset($validated['pieces_jointes'])) {
            $validated['pieces_jointes'] = json_encode($validated['pieces_jointes']);
        }

        $incidentId = DB::table('incidents')->insertGetId($validated);

        $incident = DB::table('incidents')->where('id', $incidentId)->first();

        // Décoder pieces_jointes pour le retour
        if ($incident && $incident->pieces_jointes) {
            $incident->pieces_jointes = json_decode($incident->pieces_jointes, true);
        }

        return response()->json([
            'success' => true,
            'message' => 'Incident créé avec succès',
            'data' => $incident,
        ], 201);
    }

    /**
     * @OA\Get(
     * path="/api/v1/incidents/{id}",
     * operationId="getIncidentById",
     * tags={"Incidents"},
     * summary="Détail d'un incident",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Détails de l'incident",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     * @OA\Response(response=404, description="Incident non trouvé")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $incident = DB::table('incidents as i')
            ->leftJoin('elections as e', 'i.election_id', '=', 'e.id')
            ->leftJoin('proces_verbaux as pv', 'i.proces_verbal_id', '=', 'pv.id')
            ->leftJoin('users as u1', 'i.rapporte_par_user_id', '=', 'u1.id')
            ->leftJoin('users as u2', 'i.traite_par_user_id', '=', 'u2.id')
            ->where('i.id', $id)
            ->select(
                'i.*',
                'e.nom as election',
                'e.code as election_code',
                'pv.numero_pv as pv_numero',
                DB::raw("CONCAT(u1.prenom, ' ', u1.nom) as rapporte_par"),
                DB::raw("CONCAT(u2.prenom, ' ', u2.nom) as traite_par")
            )
            ->first();

        if (!$incident) {
            return response()->json([
                'success' => false,
                'message' => 'Incident non trouvé',
            ], 404);
        }

        // Décoder pieces_jointes si présent
        if ($incident->pieces_jointes) {
            $incident->pieces_jointes = json_decode($incident->pieces_jointes, true);
        }

        return response()->json([
            'success' => true,
            'data' => $incident,
        ]);
    }

    /**
     * @OA\Put(
     * path="/api/v1/incidents/{id}",
     * operationId="updateIncident",
     * tags={"Incidents"},
     * summary="Mettre à jour un incident",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="type", type="string", enum={"irregularite", "fraude", "violence", "dysfonctionnement", "autre"}),
     * @OA\Property(property="description", type="string"),
     * @OA\Property(property="gravite", type="string", enum={"faible", "moyenne", "grave", "critique"}),
     * @OA\Property(property="statut", type="string", enum={"ouvert", "en_cours", "resolu", "rejete", "transmis"}),
     * @OA\Property(property="niveau", type="string", enum={"bureau", "arrondissement", "commune", "circonscription", "national"}),
     * @OA\Property(property="pieces_jointes", type="array", @OA\Items(type="string"))
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Incident mis à jour",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string"),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     * @OA\Response(response=404, description="Non trouvé")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $incident = DB::table('incidents')->where('id', $id)->first();

        if (!$incident) {
            return response()->json([
                'success' => false,
                'message' => 'Incident non trouvé',
            ], 404);
        }

        $validated = $request->validate([
            'type' => 'sometimes|string|in:irregularite,fraude,violence,dysfonctionnement,autre',
            'description' => 'sometimes|string',
            'gravite' => 'sometimes|string|in:faible,moyenne,grave,critique',
            'statut' => 'sometimes|string|in:ouvert,en_cours,resolu,rejete,transmis',
            'niveau' => 'sometimes|string|in:bureau,arrondissement,commune,circonscription,national',
            'niveau_id' => 'sometimes|integer',
            'resolution' => 'sometimes|string',
            'pieces_jointes' => 'sometimes|array',
            'traite_par_user_id' => 'sometimes|integer|exists:users,id',
        ]);

        $validated['updated_at'] = now();

        // Convertir pieces_jointes en JSON si présent
        if (isset($validated['pieces_jointes'])) {
            $validated['pieces_jointes'] = json_encode($validated['pieces_jointes']);
        }

        DB::table('incidents')->where('id', $id)->update($validated);

        $updatedIncident = DB::table('incidents')->where('id', $id)->first();

        // Décoder pieces_jointes pour le retour
        if ($updatedIncident && $updatedIncident->pieces_jointes) {
            $updatedIncident->pieces_jointes = json_decode($updatedIncident->pieces_jointes, true);
        }

        return response()->json([
            'success' => true,
            'message' => 'Incident mis à jour avec succès',
            'data' => $updatedIncident,
        ]);
    }

    /**
     * @OA\Delete(
     * path="/api/v1/incidents/{id}",
     * operationId="deleteIncident",
     * tags={"Incidents"},
     * summary="Supprimer un incident",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string")
     * )
     * )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $incident = DB::table('incidents')->where('id', $id)->first();

        if (!$incident) {
            return response()->json([
                'success' => false,
                'message' => 'Incident non trouvé',
            ], 404);
        }

        DB::table('incidents')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Incident supprimé avec succès',
        ]);
    }

    /**
     * @OA\Post(
     * path="/api/v1/incidents/{id}/traiter",
     * operationId="traiterIncident",
     * tags={"Incidents"},
     * summary="Prendre en charge un incident",
     * description="Passe le statut à 'en_cours'",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"traite_par_user_id"},
     * @OA\Property(property="traite_par_user_id", type="integer")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Pris en charge",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string")
     * )
     * ),
     * @OA\Response(response=400, description="Déjà traité")
     * )
     */
    public function traiter(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'traite_par_user_id' => 'required|integer|exists:users,id',
        ]);

        $incident = DB::table('incidents')->where('id', $id)->first();

        if (!$incident) {
            return response()->json([
                'success' => false,
                'message' => 'Incident non trouvé',
            ], 404);
        }

        if (in_array($incident->statut, ['resolu', 'rejete', 'transmis'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cet incident est déjà résolu, rejeté ou transmis',
            ], 400);
        }

        DB::table('incidents')->where('id', $id)->update([
            'statut' => 'en_cours',
            'traite_par_user_id' => $validated['traite_par_user_id'],
            'updated_at' => now(),
        ]);

        $updatedIncident = DB::table('incidents')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Incident pris en charge avec succès',
            'data' => $updatedIncident,
        ]);
    }

    /**
     * @OA\Post(
     * path="/api/v1/incidents/{id}/resoudre",
     * operationId="resoudreIncident",
     * tags={"Incidents"},
     * summary="Résoudre un incident",
     * description="Clôture l'incident avec une explication",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"resolution"},
     * @OA\Property(property="resolution", type="string"),
     * @OA\Property(property="traite_par_user_id", type="integer")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Incident résolu",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string")
     * )
     * )
     * )
     */
    public function resoudre(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => 'required|string',
            'traite_par_user_id' => 'sometimes|integer|exists:users,id',
        ]);

        $incident = DB::table('incidents')->where('id', $id)->first();

        if (!$incident) {
            return response()->json([
                'success' => false,
                'message' => 'Incident non trouvé',
            ], 404);
        }

        if (in_array($incident->statut, ['resolu', 'rejete', 'transmis'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cet incident est déjà résolu, rejeté ou transmis',
            ], 400);
        }

        $updateData = [
            'statut' => 'resolu',
            'resolution' => $validated['resolution'],
            'date_resolution' => now(),
            'updated_at' => now(),
        ];

        if (isset($validated['traite_par_user_id'])) {
            $updateData['traite_par_user_id'] = $validated['traite_par_user_id'];
        }

        DB::table('incidents')->where('id', $id)->update($updateData);

        $updatedIncident = DB::table('incidents')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Incident résolu avec succès',
            'data' => $updatedIncident,
        ]);
    }
}