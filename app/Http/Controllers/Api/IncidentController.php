<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * IncidentController
 * 
 * Gestion des incidents et anomalies signalés pendant le processus électoral
 * 
 * @package App\Http\Controllers\Api
 * @version 1.0
 */
class IncidentController extends Controller
{
    /**
     * Liste des incidents avec filtres optionnels
     * 
     * GET /api/v1/incidents
     * 
     * @OA\Get(
     *     path="/incidents",
     *     tags={"Incidents"},
     *     summary="Liste des incidents",
     *     description="Retourne la liste des incidents avec filtres optionnels (statut, gravité, type, élection, niveau)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="statut",
     *         in="query",
     *         description="Filtrer par statut",
     *         required=false,
     *         @OA\Schema(type="string", enum={"ouvert", "en_cours", "resolu", "rejete", "transmis"}, example="ouvert")
     *     ),
     *     @OA\Parameter(
     *         name="gravite",
     *         in="query",
     *         description="Filtrer par gravité",
     *         required=false,
     *         @OA\Schema(type="string", enum={"faible", "moyenne", "grave", "critique"}, example="grave")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filtrer par type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"irregularite", "fraude", "violence", "dysfonctionnement", "autre"}, example="fraude")
     *     ),
     *     @OA\Parameter(
     *         name="election_id",
     *         in="query",
     *         description="Filtrer par élection",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="niveau",
     *         in="query",
     *         description="Filtrer par niveau géographique",
     *         required=false,
     *         @OA\Schema(type="string", enum={"bureau", "arrondissement", "commune", "circonscription", "national"}, example="bureau")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des incidents",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="INC-675F8A2B"),
     *                     @OA\Property(property="type", type="string", example="fraude"),
     *                     @OA\Property(property="gravite", type="string", example="grave"),
     *                     @OA\Property(property="statut", type="string", example="ouvert"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="niveau", type="string", example="bureau"),
     *                     @OA\Property(property="niveau_id", type="integer", example=1),
     *                     @OA\Property(property="election", type="string", example="Élections Législatives 2026"),
     *                     @OA\Property(property="pv_numero", type="string", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=15)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     * 
     * Filtres disponibles:
     * - statut: ouvert, en_traitement, resolu, clos
     * - gravite: faible, moyenne, elevee, critique
     * - type: type de l'incident
     * - election_id: ID de l'élection
     * 
     * @param Request $request
     * @return JsonResponse
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
     * Créer un nouvel incident
     * 
     * POST /api/v1/incidents
     * 
     * @OA\Post(
     *     path="/incidents",
     *     tags={"Incidents"},
     *     summary="Créer un incident",
     *     description="Enregistre un nouvel incident ou anomalie signalé pendant le processus électoral",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"election_id","type","description"},
     *             @OA\Property(property="code", type="string", maxLength=50, example="INC-001", description="Code unique (auto-généré si non fourni)"),
     *             @OA\Property(property="election_id", type="integer", example=1, description="ID de l'élection concernée"),
     *             @OA\Property(property="proces_verbal_id", type="integer", nullable=true, example=1, description="ID du PV concerné (optionnel)"),
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 enum={"irregularite", "fraude", "violence", "dysfonctionnement", "autre"},
     *                 example="fraude",
     *                 description="Type d'incident"
     *             ),
     *             @OA\Property(
     *                 property="gravite",
     *                 type="string",
     *                 enum={"faible", "moyenne", "grave", "critique"},
     *                 example="grave",
     *                 description="Niveau de gravité"
     *             ),
     *             @OA\Property(
     *                 property="niveau",
     *                 type="string",
     *                 enum={"bureau", "arrondissement", "commune", "circonscription", "national"},
     *                 example="bureau",
     *                 description="Niveau géographique"
     *             ),
     *             @OA\Property(property="niveau_id", type="integer", nullable=true, example=1, description="ID de l'entité géographique"),
     *             @OA\Property(property="description", type="string", example="Tentative d'intimidation d'électeurs constatée au bureau de vote BV-001"),
     *             @OA\Property(
     *                 property="pieces_jointes",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"photo1.jpg", "rapport.pdf"},
     *                 description="Liste des pièces jointes"
     *             ),
     *             @OA\Property(property="rapporte_par_user_id", type="integer", example=1, description="ID de l'utilisateur rapportant l'incident")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Incident créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Incident créé avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="INC-675F8A2B"),
     *                 @OA\Property(property="statut", type="string", example="ouvert")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     * 
     * @param Request $request
     * @return JsonResponse
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
     * Détail d'un incident
     * 
     * GET /api/v1/incidents/{id}
     * 
     * @OA\Get(
     *     path="/incidents/{id}",
     *     tags={"Incidents"},
     *     summary="Détail d'un incident",
     *     description="Retourne les informations complètes d'un incident avec les utilisateurs ayant rapporté et traité l'incident",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'incident",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'incident",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="INC-675F8A2B"),
     *                 @OA\Property(property="type", type="string", example="fraude"),
     *                 @OA\Property(property="gravite", type="string", example="grave"),
     *                 @OA\Property(property="statut", type="string", example="resolu"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="resolution", type="string", nullable=true),
     *                 @OA\Property(property="election", type="string", example="Élections Législatives 2026"),
     *                 @OA\Property(property="pv_numero", type="string", nullable=true),
     *                 @OA\Property(property="rapporte_par", type="string", example="Jean Dupont"),
     *                 @OA\Property(property="traite_par", type="string", example="Marie Martin"),
     *                 @OA\Property(property="date_resolution", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="pieces_jointes", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Incident non trouvé"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     * 
     * @param int $id
     * @return JsonResponse
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
     * Mettre à jour un incident
     * 
     * PUT /api/v1/incidents/{id}
     * 
     * @OA\Put(
     *     path="/incidents/{id}",
     *     tags={"Incidents"},
     *     summary="Mettre à jour un incident",
     *     description="Modifie les informations d'un incident existant",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'incident",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", enum={"irregularite", "fraude", "violence", "dysfonctionnement", "autre"}),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="gravite", type="string", enum={"faible", "moyenne", "grave", "critique"}),
     *             @OA\Property(property="statut", type="string", enum={"ouvert", "en_cours", "resolu", "rejete", "transmis"}),
     *             @OA\Property(property="niveau", type="string", enum={"bureau", "arrondissement", "commune", "circonscription", "national"}),
     *             @OA\Property(property="niveau_id", type="integer"),
     *             @OA\Property(property="resolution", type="string"),
     *             @OA\Property(property="pieces_jointes", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="traite_par_user_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Incident mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Incident mis à jour avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Incident non trouvé"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
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
     * Supprimer un incident
     * 
     * DELETE /api/v1/incidents/{id}
     * 
     * @OA\Delete(
     *     path="/incidents/{id}",
     *     tags={"Incidents"},
     *     summary="Supprimer un incident",
     *     description="Supprime définitivement un incident du système",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'incident",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Incident supprimé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Incident supprimé avec succès")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Incident non trouvé"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     * 
     * @param int $id
     * @return JsonResponse
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
     * Marquer un incident comme "en traitement"
     * 
     * POST /api/v1/incidents/{id}/traiter
     * 
     * @OA\Post(
     *     path="/incidents/{id}/traiter",
     *     tags={"Incidents"},
     *     summary="Prendre en charge un incident",
     *     description="Change le statut d'un incident à 'en_cours' et assigne un responsable du traitement",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'incident",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"traite_par_user_id"},
     *             @OA\Property(property="traite_par_user_id", type="integer", example=1, description="ID de l'utilisateur prenant en charge l'incident")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Incident pris en charge avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Incident pris en charge avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="statut", type="string", example="en_cours"),
     *                 @OA\Property(property="traite_par_user_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Incident déjà traité",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cet incident est déjà résolu, rejeté ou transmis")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Incident non trouvé"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
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

        if ($incident->statut === 'resolu' || $incident->statut === 'rejete' || $incident->statut === 'transmis') {
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
     * Résoudre un incident
     * 
     * POST /api/v1/incidents/{id}/resoudre
     * 
     * @OA\Post(
     *     path="/incidents/{id}/resoudre",
     *     tags={"Incidents"},
     *     summary="Résoudre un incident",
     *     description="Marque un incident comme résolu avec une description de la résolution et enregistre la date de résolution",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'incident",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"resolution"},
     *             @OA\Property(
     *                 property="resolution",
     *                 type="string",
     *                 example="L'incident a été vérifié et les mesures correctives ont été prises. Les électeurs ont pu voter dans des conditions normales.",
     *                 description="Description de la résolution de l'incident"
     *             ),
     *             @OA\Property(property="traite_par_user_id", type="integer", example=1, description="ID de l'utilisateur résolvant l'incident")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Incident résolu avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Incident résolu avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="statut", type="string", example="resolu"),
     *                 @OA\Property(property="resolution", type="string"),
     *                 @OA\Property(property="date_resolution", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Incident déjà traité",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cet incident est déjà résolu, rejeté ou transmis")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Incident non trouvé"),
     *     @OA\Response(response=422, description="Résolution requise"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
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

        if ($incident->statut === 'resolu' || $incident->statut === 'rejete' || $incident->statut === 'transmis') {
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