<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 * name="Elections",
 * description="Gestion des élections et configuration"
 * )
 */
class ElectionController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/v1/elections",
     * operationId="getElectionsList",
     * tags={"Elections"},
     * summary="Liste des élections",
     * description="Récupère la liste de toutes les élections",
     * @OA\Response(
     * response=200,
     * description="Succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="array", @OA\Items(type="object")),
     * @OA\Property(property="count", type="integer")
     * )
     * )
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
     * @OA\Post(
     * path="/api/v1/elections",
     * operationId="storeElection",
     * tags={"Elections"},
     * summary="Créer une élection",
     * description="Enregistre une nouvelle élection",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"code", "nom", "type_election_id", "date_scrutin"},
     * @OA\Property(property="code", type="string", example="PRES-2026"),
     * @OA\Property(property="nom", type="string", example="Présidentielle 2026"),
     * @OA\Property(property="type_election_id", type="integer", example=1),
     * @OA\Property(property="date_scrutin", type="string", format="date", example="2026-04-11"),
     * @OA\Property(property="statut", type="string", enum={"preparation", "en_cours", "cloture", "annule"}, default="preparation")
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Élection créée",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string"),
     * @OA\Property(property="data", type="object")
     * )
     * )
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
     * @OA\Get(
     * path="/api/v1/elections/{id}",
     * operationId="getElectionById",
     * tags={"Elections"},
     * summary="Détail d'une élection",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID de l'élection",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Détails de l'élection",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     * @OA\Response(response=404, description="Non trouvé")
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
     * @OA\Put(
     * path="/api/v1/elections/{id}",
     * operationId="updateElection",
     * tags={"Elections"},
     * summary="Mettre à jour une élection",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="nom", type="string"),
     * @OA\Property(property="date_scrutin", type="string", format="date"),
     * @OA\Property(property="statut", type="string", enum={"preparation", "en_cours", "cloture", "annule"}),
     * @OA\Property(property="type_election_id", type="integer")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Élection mise à jour",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string"),
     * @OA\Property(property="data", type="object")
     * )
     * )
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
     * @OA\Delete(
     * path="/api/v1/elections/{id}",
     * operationId="deleteElection",
     * tags={"Elections"},
     * summary="Supprimer une élection",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer")
     * ),
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
     * @OA\Get(
     * path="/api/v1/elections/{id}/candidatures",
     * operationId="getElectionCandidatures",
     * tags={"Elections"},
     * summary="Candidatures d'une élection (Toutes)",
     * description="Retourne toutes les candidatures brutes",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="array", @OA\Items(type="object")),
     * @OA\Property(property="count", type="integer")
     * )
     * )
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

        // Décoder le JSON PostgreSQL en objet PHP
        $candidatures = $candidatures->map(function($candidature) {
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
     * @OA\Get(
     * path="/api/v1/elections/{id}/entites-politiques",
     * operationId="getElectionEntitesPolitiques",
     * tags={"Elections"},
     * summary="Entités politiques d'une élection (Format PV)",
     * description="Retourne les entités politiques uniques pour la saisie des PV, triées selon le type d'élection",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Liste des entités politiques",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(
     * property="data", 
     * type="array", 
     * @OA\Items(
     * @OA\Property(property="id", type="integer"),
     * @OA\Property(property="nom", type="string"),
     * @OA\Property(property="sigle", type="string"),
     * @OA\Property(property="candidature_id", type="integer")
     * )
     * ),
     * @OA\Property(property="meta", type="object")
     * )
     * )
     * )
     */
    public function entitesPolitiques(int $id): JsonResponse
    {
        try {
            // Vérifier que l'élection existe
            $election = DB::table('elections')->where('id', $id)->first();
            
            if (!$election) {
                return response()->json([
                    'success' => false,
                    'message' => 'Élection non trouvée',
                ], 404);
            }
            
            // ✅ Récupérer toutes les candidatures VALIDÉES de l'élection
            $candidatures = DB::table('candidatures as c')
                ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
                ->where('c.election_id', $id)  // ✅ FILTRAGE PAR ELECTION_ID
                ->where('c.statut', 'validee')  // ✅ SEULEMENT LES VALIDÉES
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
            
            if ($candidatures->isEmpty()) {
                Log::warning('Aucune candidature trouvée pour cette élection', [
                    'election_id' => $id,
                    'code_election' => $election->code,
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'meta' => [
                        'total_entites' => 0,
                        'total_candidatures' => 0,
                        'election' => [
                            'id' => $election->id,
                            'nom' => $election->nom,
                            'code' => $election->code,
                        ],
                        'message' => 'Aucune candidature validée pour cette élection',
                    ],
                ]);
            }
            
            // ✅ GROUPER PAR ENTITÉ POLITIQUE (DISTINCT)
            $entitesUniques = [];
            $seenIds = [];
            
            foreach ($candidatures as $candidature) {
                $entiteId = $candidature->entite_id;
                
                // Si déjà vue, passer
                if (in_array($entiteId, $seenIds)) {
                    continue;
                }
                
                $seenIds[] = $entiteId;
                
                $entitesUniques[] = [
                    'id' => $entiteId,
                    'nom' => $candidature->nom,
                    'sigle' => $candidature->sigle,
                    'code' => $candidature->code ?? $candidature->sigle,
                    'type' => $candidature->type ?? 'parti',
                    'logo' => $candidature->logo,
                    'couleur' => $candidature->couleur,
                    'candidature_id' => $candidature->candidature_id,  // ✅ IMPORTANT POUR PAYLOAD
                    'numero_liste' => $candidature->numero_liste ?? 999,
                ];
            }
            
            // ✅ TRI PERSONNALISÉ SELON CODE ÉLECTION
            $codeElection = strtoupper($election->code);
            $ordrePersonnalise = null;
            
            // Déterminer l'ordre selon le type d'élection
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
                    'total_candidatures' => $candidatures->count(),
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
                
                // Si sigle non trouvé dans l'ordre, mettre à la fin
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
                    'ordre_applique' => $ordrePersonnalise,
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
     * @OA\Get(
     * path="/api/v1/elections/{id}/resultats",
     * operationId="getElectionResultats",
     * tags={"Elections"},
     * summary="Résultats d'une élection (National)",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="array", @OA\Items(type="object"))
     * )
     * )
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
     * @OA\Get(
     * path="/api/v1/elections/{id}/statistiques",
     * operationId="getElectionStatistiques",
     * tags={"Elections"},
     * summary="Statistiques d'une élection",
     * description="Retourne les taux de participation, PV saisis, etc.",
     * @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Statistiques récupérées",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="object")
     * )
     * )
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
     * @OA\Get(
     * path="/api/v1/elections/types",
     * operationId="getElectionTypes",
     * tags={"Elections"},
     * summary="Liste des types d'élection",
     * @OA\Response(
     * response=200,
     * description="Succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="array", @OA\Items(type="object"))
     * )
     * )
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
}