<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ElectionController
 * 
 * Gestion des élections
 */
class ElectionController extends Controller
{
    /**
     * Liste des élections
     * 
     * GET /api/v1/elections
     * 
     * @OA\Get(
     *     path="/elections",
     *     tags={"Élections"},
     *     summary="Liste des élections",
     *     description="Retourne la liste de toutes les élections avec leurs types",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des élections",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="LEG2026"),
     *                     @OA\Property(property="nom", type="string", example="Élections Législatives 2026"),
     *                     @OA\Property(property="date_scrutin", type="string", format="date", example="2026-04-15"),
     *                     @OA\Property(property="statut", type="string", enum={"preparation", "en_cours", "cloture", "annule"}, example="preparation"),
     *                     @OA\Property(property="type_election", type="string", example="Législatives"),
     *                     @OA\Property(property="type_code", type="string", example="LEG")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(): JsonResponse
    {
        $elections = DB::table('elections as e')
            ->leftJoin('types_election as te', 'e.type_election_id', '=', 'te.id')
            ->select(
                'e.id',
                'e.code',
                'e.nom',
                'e.date_scrutin',
                'e.statut',
                'te.nom as type_election',
                'te.code as type_code',
                'e.created_at'
            )
            ->orderBy('e.date_scrutin', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $elections,
            'count' => $elections->count(),
        ]);
    }

    /**
     * Créer une élection
     * 
     * POST /api/v1/elections
     * 
     * @OA\Post(
     *     path="/elections",
     *     tags={"Élections"},
     *     summary="Créer une élection",
     *     description="Crée une nouvelle élection dans le système",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","nom","type_election_id","date_scrutin"},
     *             @OA\Property(property="code", type="string", maxLength=50, example="LEG2026", description="Code unique de l'élection"),
     *             @OA\Property(property="nom", type="string", maxLength=255, example="Élections Législatives 2026"),
     *             @OA\Property(property="type_election_id", type="integer", example=1, description="ID du type d'élection"),
     *             @OA\Property(property="date_scrutin", type="string", format="date", example="2026-04-15"),
     *             @OA\Property(property="statut", type="string", enum={"preparation", "en_cours", "cloture", "annule"}, example="preparation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Élection créée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Élection créée avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="LEG2026"),
     *                 @OA\Property(property="nom", type="string", example="Élections Législatives 2026")
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
            'code' => 'required|string|max:50|unique:elections,code',
            'nom' => 'required|string|max:255',
            'type_election_id' => 'required|integer|exists:types_election,id',
            'date_scrutin' => 'required|date',
            'statut' => 'sometimes|string|in:preparation,en_cours,cloture,annule',
        ]);

        $validated['statut'] = $validated['statut'] ?? 'preparation';
        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        $electionId = DB::table('elections')->insertGetId($validated);

        $election = DB::table('elections')->where('id', $electionId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Élection créée avec succès',
            'data' => $election,
        ], 201);
    }

    /**
     * Détail d'une élection
     * 
     * GET /api/v1/elections/{id}
     * 
     * @OA\Get(
     *     path="/elections/{id}",
     *     tags={"Élections"},
     *     summary="Détail d'une élection",
     *     description="Retourne les informations complètes d'une élection spécifique",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'élection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'élection",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="LEG2026"),
     *                 @OA\Property(property="nom", type="string", example="Élections Législatives 2026"),
     *                 @OA\Property(property="date_scrutin", type="string", format="date", example="2026-04-15"),
     *                 @OA\Property(property="statut", type="string", example="preparation"),
     *                 @OA\Property(property="type_election", type="string", example="Législatives"),
     *                 @OA\Property(property="type_code", type="string", example="LEG")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Élection non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Élection non trouvée")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $election = DB::table('elections as e')
            ->leftJoin('types_election as te', 'e.type_election_id', '=', 'te.id')
            ->where('e.id', $id)
            ->select(
                'e.*',
                'te.nom as type_election',
                'te.code as type_code'
            )
            ->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $election,
        ]);
    }

    /**
     * Mettre à jour une élection
     * 
     * PUT /api/v1/elections/{id}
     * 
     * @OA\Put(
     *     path="/elections/{id}",
     *     tags={"Élections"},
     *     summary="Mettre à jour une élection",
     *     description="Modifie les informations d'une élection existante",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'élection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="nom", type="string", maxLength=255, example="Élections Législatives 2026 - Modifié"),
     *             @OA\Property(property="date_scrutin", type="string", format="date", example="2026-04-20"),
     *             @OA\Property(property="statut", type="string", enum={"preparation", "en_cours", "cloture", "annule"}, example="en_cours"),
     *             @OA\Property(property="type_election_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Élection mise à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Élection mise à jour avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Élection non trouvée"),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $election = DB::table('elections')->where('id', $id)->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'date_scrutin' => 'sometimes|date',
            'statut' => 'sometimes|string|in:preparation,en_cours,cloture,annule',
            'type_election_id' => 'sometimes|integer|exists:types_election,id',
        ]);

        $validated['updated_at'] = now();

        DB::table('elections')->where('id', $id)->update($validated);

        $updatedElection = DB::table('elections')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Élection mise à jour avec succès',
            'data' => $updatedElection,
        ]);
    }

    /**
     * Supprimer une élection
     * 
     * DELETE /api/v1/elections/{id}
     * 
     * @OA\Delete(
     *     path="/elections/{id}",
     *     tags={"Élections"},
     *     summary="Supprimer une élection",
     *     description="Supprime définitivement une élection du système",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'élection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Élection supprimée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Élection supprimée avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Élection non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Élection non trouvée")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $election = DB::table('elections')->where('id', $id)->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }

        DB::table('elections')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Élection supprimée avec succès',
        ]);
    }

    /**
     * Candidatures d'une élection (TOUTES - ancien endpoint)
     * 
     * GET /api/v1/elections/{id}/candidatures
     * 
     * ⚠️ ATTENTION : Retourne TOUTES les candidatures (peut être 120+ pour législatives)
     * Pour la saisie PV, utiliser /elections/{id}/entites-politiques à la place
     * 
     * @OA\Get(
     *     path="/elections/{id}/candidatures",
     *     tags={"Élections"},
     *     summary="Candidatures d'une élection",
     *     description="Retourne la liste des candidatures pour une élection spécifique avec les détails des entités politiques",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'élection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
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
     *                     @OA\Property(property="election_id", type="integer", example=1),
     *                     @OA\Property(property="entite_politique_id", type="integer", example=1),
     *                     @OA\Property(property="circonscription_id", type="integer", example=1),
     *                     @OA\Property(property="numero_liste", type="integer", example=1),
     *                     @OA\Property(property="tete_liste", type="string", example="NOM Tête de Liste"),
     *                     @OA\Property(property="statut", type="string", example="validee"),
     *                     @OA\Property(
     *                         property="entite_politique",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nom", type="string", example="Union Progressiste"),
     *                         @OA\Property(property="sigle", type="string", example="UP"),
     *                         @OA\Property(property="type", type="string", example="parti")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=8)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Élection non trouvée"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function candidatures(int $id): JsonResponse
    {
        $election = DB::table('elections')->where('id', $id)->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }

        // ✅ CORRECTION PostgreSQL : Éviter le conflit de noms avec json_build_object
        $candidatures = DB::table('candidatures as c')
            ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
            ->where('c.election_id', $id)
            ->select(
                'c.id',
                'c.election_id',
                'c.entite_politique_id',
                'c.circonscription_id',
                'c.numero_liste',
                'c.tete_liste',
                'c.statut',
                DB::raw("json_build_object(
                    'id', ep.id,
                    'nom', ep.nom,
                    'sigle', ep.sigle,
                    'type', ep.type
                ) as entite_politique")
            )
            ->orderBy('c.numero_liste')
            ->get();

        // ✅ Décoder le JSON PostgreSQL en objet PHP pour chaque candidature
        $candidatures = $candidatures->map(function($candidature) {
            // PostgreSQL retourne json_build_object comme string JSON
            if (is_string($candidature->entite_politique)) {
                $candidature->entite_politique = json_decode($candidature->entite_politique);
            }
            return $candidature;
        });

        return response()->json([
            'success' => true,
            'data' => $candidatures,
            'count' => $candidatures->count(),
        ]);
    }

    /**
     * ✅ NOUVEAU CORRIGÉ : Entités politiques DISTINCTES d'une élection
     * 
     * GET /api/v1/elections/{id}/entites-politiques
     * 
     * Retourne les entités politiques DISTINCTES (5 pour législatives au lieu de 120 candidatures)
     * Utilisé pour la saisie des PV - VOTE PAR PARTI, PAS PAR CANDIDATURE !
     * 
     * @OA\Get(
     *     path="/elections/{id}/entites-politiques",
     *     tags={"Élections"},
     *     summary="Entités politiques DISTINCTES d'une élection",
     *     description="Retourne les entités politiques uniques pour la saisie PV (5 partis pour législatives, pas 120 candidatures). Le vote se fait par PARTI, donc on groupe les candidatures par entité politique.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'élection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des entités politiques distinctes",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=16, description="ID de l'entité politique"),
     *                     @OA\Property(property="nom", type="string", example="Force Cauris pour un Bénin Émergent"),
     *                     @OA\Property(property="sigle", type="string", example="FCBE"),
     *                     @OA\Property(property="code", type="string", example="FCBE"),
     *                     @OA\Property(property="type", type="string", example="parti"),
     *                     @OA\Property(property="logo", type="string", nullable=true),
     *                     @OA\Property(property="couleur", type="string", nullable=true),
     *                     @OA\Property(property="candidature_id", type="integer", example=77, description="ID candidature de référence pour le payload"),
     *                     @OA\Property(property="numero_liste", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="total_entites", type="integer", example=5, description="Nombre d'entités distinctes"),
     *                 @OA\Property(property="total_candidatures", type="integer", example=120, description="Nombre total de candidatures"),
     *                 @OA\Property(
     *                     property="election",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nom", type="string", example="Élections Législatives 2026"),
     *                     @OA\Property(property="code", type="string", example="LEG_2026")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Élection non trouvée"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     * 
     * @param int $id ID de l'élection
     * @return JsonResponse
     */
    public function entitesPolitiques(int $id): JsonResponse
{
    try {
        $election = DB::table('elections')->where('id', $id)->first();
        
        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }
        
        // Récupérer toutes les candidatures de l'élection
        $candidatures = DB::table('candidatures as c')
            ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
            ->where('c.election_id', $id)
            ->where('c.statut', 'validee')
            ->select(
                'c.id as candidature_id',
                'c.numero_liste',
                'ep.id as entite_id',
                'ep.nom',
                'ep.sigle',
                'ep.code',
                'ep.type',
                'ep.logo',
                'ep.couleur'
            )
            ->get();
        
        // ✅ GROUPER PAR ENTITÉ POLITIQUE (DISTINCT)
        $entitesUniques = [];
        $seenIds = [];
        
        foreach ($candidatures as $candidature) {
            $entiteId = $candidature->entite_id;
            
            if (in_array($entiteId, $seenIds)) {
                continue;
            }
            
            $seenIds[] = $entiteId;
            
            $entitesUniques[] = [
                'id' => $entiteId,
                'nom' => $candidature->nom,
                'sigle' => $candidature->sigle,
                'code' => $candidature->code,
                'type' => $candidature->type ?? 'parti',
                'logo' => $candidature->logo,
                'couleur' => $candidature->couleur,
                'candidature_id' => $candidature->candidature_id,
                'numero_liste' => $candidature->numero_liste ?? 999,
            ];
        }
        
        // ✅ TRI PERSONNALISÉ SELON CODE ÉLECTION
        $codeElection = $election->code;
        
        // Définir l'ordre selon le code de l'élection
        if (strpos($codeElection, 'LEG') !== false) {
            // LÉGISLATIVES : FCBE → LD → BR → MOELE-BENIN → UP LE RENOUVEAU
            $ordrePersonnalise = ['FCBE', 'LD', 'BR', 'MOELE-BENIN', 'UP'];
        } elseif (strpos($codeElection, 'COM') !== false) {
            // COMMUNALES : FCBE → UP LE RENOUVEAU → BR
            $ordrePersonnalise = ['FCBE', 'UP', 'BR'];
        } else {
            // PRÉSIDENTIELLES ou autre : ordre par numero_liste
            usort($entitesUniques, function($a, $b) {
                return $a['numero_liste'] <=> $b['numero_liste'];
            });
            
            Log::info('Entités politiques chargées (ordre numero_liste)', [
                'election_id' => $id,
                'code_election' => $codeElection,
                'total_entites' => count($entitesUniques),
                'ordre' => array_column($entitesUniques, 'sigle'),
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $entitesUniques,
                'meta' => [
                    'total_entites' => count($entitesUniques),
                    'total_candidatures' => $candidatures->count(),
                    'election' => [
                        'id' => $election->id,
                        'nom' => $election->nom,
                        'code' => $election->code,
                    ],
                ],
            ]);
        }
        
        // Appliquer l'ordre personnalisé
        usort($entitesUniques, function($a, $b) use ($ordrePersonnalise) {
            $posA = array_search($a['sigle'], $ordrePersonnalise);
            $posB = array_search($b['sigle'], $ordrePersonnalise);
            
            // Si sigle non trouvé, mettre à la fin
            if ($posA === false) $posA = 999;
            if ($posB === false) $posB = 999;
            
            return $posA <=> $posB;
        });
        
        Log::info('Entités politiques chargées (ordre personnalisé)', [
            'election_id' => $id,
            'code_election' => $codeElection,
            'total_entites' => count($entitesUniques),
            'total_candidatures' => $candidatures->count(),
            'ordre_attendu' => $ordrePersonnalise,
            'ordre_obtenu' => array_column($entitesUniques, 'sigle'),
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $entitesUniques,
            'meta' => [
                'total_entites' => count($entitesUniques),
                'total_candidatures' => $candidatures->count(),
                'election' => [
                    'id' => $election->id,
                    'nom' => $election->nom,
                    'code' => $election->code,
                ],
                'ordre_applique' => $ordrePersonnalise ?? null,
            ],
        ]);
    } catch (\Exception $e) {
        Log::error('Erreur chargement entités politiques', [
            'election_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du chargement des entités politiques',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Résultats d'une élection
     * 
     * GET /api/v1/elections/{id}/resultats
     * 
     * @OA\Get(
     *     path="/elections/{id}/resultats",
     *     tags={"Élections"},
     *     summary="Résultats d'une élection",
     *     description="Retourne les résultats agrégés au niveau national pour une élection",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'élection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Résultats de l'élection",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="entite_nom", type="string", example="Bloc Républicain"),
     *                     @OA\Property(property="sigle", type="string", example="BR"),
     *                     @OA\Property(property="total_voix", type="integer", example=450000),
     *                     @OA\Property(property="pourcentage", type="number", format="float", example=35.5),
     *                     @OA\Property(property="sieges_obtenus", type="integer", example=38)
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=8)
     *         )
     *     ),
     *     @OA\Response(response=404, description="Élection non trouvée"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function resultats(int $id): JsonResponse
    {
        $election = DB::table('elections')->where('id', $id)->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }

        $resultats = DB::table('agregations_calculs as ar')
            ->join('candidatures as c', 'ar.candidature_id', '=', 'c.id')
            ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
            ->where('ar.election_id', $id)
            ->where('ar.niveau', 'national')
            ->select(
                'ar.id',
                'ep.nom as entite_nom',
                'ep.sigle',
                'ar.total_voix',
                'ar.pourcentage_exprimes as pourcentage',
                'ar.sieges_obtenus'
            )
            ->orderBy('ar.total_voix', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $resultats,
            'count' => $resultats->count(),
        ]);
    }

    /**
     * Statistiques d'une élection
     * 
     * GET /api/v1/elections/{id}/statistiques
     * 
     * @OA\Get(
     *     path="/elections/{id}/statistiques",
     *     tags={"Élections"},
     *     summary="Statistiques d'une élection",
     *     description="Retourne les statistiques complètes d'une élection (candidatures, PV, taux de saisie, etc.)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'élection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques de l'élection",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="election", type="object"),
     *                 @OA\Property(
     *                     property="statistiques",
     *                     type="object",
     *                     @OA\Property(property="nb_candidatures", type="integer", example=8),
     *                     @OA\Property(property="nb_pv_saisis", type="integer", example=15456),
     *                     @OA\Property(property="nb_pv_valides", type="integer", example=15200),
     *                     @OA\Property(property="total_bureaux", type="integer", example=15500),
     *                     @OA\Property(property="taux_saisie", type="number", format="float", example=99.72),
     *                     @OA\Property(property="taux_validation", type="number", format="float", example=98.34)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Élection non trouvée"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function statistiques(int $id): JsonResponse
    {
        $election = DB::table('elections')->where('id', $id)->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }

        $nbCandidatures = DB::table('candidatures')
            ->where('election_id', $id)
            ->count();

        $nbPvSaisis = DB::table('proces_verbaux')
            ->where('election_id', $id)
            ->whereNotNull('created_at')
            ->count();

        $nbPvValides = DB::table('proces_verbaux')
            ->where('election_id', $id)
            ->where('statut', 'valide')
            ->count();

        $totalBureaux = DB::table('postes_vote')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'election' => $election,
                'statistiques' => [
                    'nb_candidatures' => $nbCandidatures,
                    'nb_pv_saisis' => $nbPvSaisis,
                    'nb_pv_valides' => $nbPvValides,
                    'total_bureaux' => $totalBureaux,
                    'taux_saisie' => $totalBureaux > 0 ? round(($nbPvSaisis / $totalBureaux) * 100, 2) : 0,
                    'taux_validation' => $nbPvSaisis > 0 ? round(($nbPvValides / $nbPvSaisis) * 100, 2) : 0,
                ],
            ],
        ]);
    }

    /**
     * Liste des types d'élection
     * 
     * GET /api/v1/elections/types
     * 
     * @OA\Get(
     *     path="/elections/types",
     *     tags={"Élections"},
     *     summary="Liste des types d'élection",
     *     description="Retourne la liste des types d'élection disponibles (Législatives, Présidentielles, etc.)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des types d'élection",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="LEG"),
     *                     @OA\Property(property="nom", type="string", example="Législatives"),
     *                     @OA\Property(property="description", type="string", example="Élections législatives pour le renouvellement de l'Assemblée Nationale")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function types(): JsonResponse
    {
        $types = DB::table('types_election')
            ->select('id', 'code', 'nom', 'description')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $types,
            'count' => $types->count(),
        ]);
    }

    /**
 * Récupérer les entités politiques avec leurs candidatures pour une élection
 * ✅ IMPORTANT : Retourne candidature_id pour le payload PV
 */
public function getEntitesAvecCandidatures(int $id): JsonResponse
{
    try {
        $election = Election::findOrFail($id);
        
        // Récupérer entités avec candidatures
        $entites = EntitePolitique::with(['candidatures' => function ($query) use ($id) {
            $query->where('election_id', $id);
        }])
        ->whereHas('candidatures', function ($query) use ($id) {
            $query->where('election_id', $id);
        })
        ->orderBy('numero_ordre')
        ->get()
        ->map(function ($entite) {
            // ✅ Retourner candidature_id pour le frontend
            $candidature = $entite->candidatures->first();
            
            return [
                'id' => $entite->id,
                'nom' => $entite->nom,
                'sigle' => $entite->sigle,
                'numero_ordre' => $entite->numero_ordre,
                'candidature_id' => $candidature ? $candidature->id : $entite->id, // ✅ IMPORTANT
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $entites,
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur chargement entités',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}