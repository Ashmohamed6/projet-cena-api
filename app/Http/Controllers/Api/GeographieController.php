<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * GeographieController
 * 
 * Gestion des données géographiques : départements, circonscriptions, communes, etc.
 */
class GeographieController extends Controller
{
    /**
     * Liste des départements
     * 
     * GET /api/v1/geographie/departements
     * 
     * @OA\Get(
     *     path="/geographie/departements",
     *     tags={"Géographie"},
     *     summary="Liste des départements",
     *     description="Retourne la liste complète des 12 départements du Bénin",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche par nom ou code",
     *         required=false,
     *         @OA\Schema(type="string", example="ATLANTIQUE")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des départements",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="AL"),
     *                     @OA\Property(property="nom", type="string", example="Alibori")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=12)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function departements(Request $request): JsonResponse
    {
        $query = DB::table('departements')
            ->select('id', 'code', 'nom');
        
        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom', 'ILIKE', "%{$search}%")
                  ->orWhere('code', 'ILIKE', "%{$search}%");
            });
        }
        
        $departements = $query->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => $departements,
            'count' => $departements->count(),
        ]);
    }

    /**
     * Liste des circonscriptions électorales
     * 
     * GET /api/v1/geographie/circonscriptions
     * 
     * @OA\Get(
     *     path="/geographie/circonscriptions",
     *     tags={"Géographie"},
     *     summary="Liste des circonscriptions électorales",
     *     description="Retourne la liste des circonscriptions électorales avec le nombre de sièges",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="departement_id",
     *         in="query",
     *         description="Filtrer par département",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche par nom ou code",
     *         required=false,
     *         @OA\Schema(type="string", example="Alibori")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des circonscriptions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="CE-AL-1"),
     *                     @OA\Property(property="nom", type="string", example="Alibori 1ère circonscription"),
     *                     @OA\Property(property="nombre_sieges_total", type="integer", example=3),
     *                     @OA\Property(property="departement", type="string", example="Alibori"),
     *                     @OA\Property(property="departement_code", type="string", example="AL")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=24)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function circonscriptions(Request $request): JsonResponse
    {
        $query = DB::table('circonscriptions_electorales as c')
            ->join('departements as d', 'c.departement_id', '=', 'd.id')
            ->select(
                'c.id',
                'c.code',
                'c.nom',
                'c.nombre_sieges_total',
                'd.nom as departement',
                'd.code as departement_code'
            );
        
        // Filtrer par département
        if ($request->has('departement_id')) {
            $query->where('c.departement_id', $request->departement_id);
        }
        
        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('c.nom', 'ILIKE', "%{$search}%")
                  ->orWhere('c.code', 'ILIKE', "%{$search}%");
            });
        }
        
        $circonscriptions = $query->orderBy('c.code')->get();

        return response()->json([
            'success' => true,
            'data' => $circonscriptions,
            'count' => $circonscriptions->count(),
        ]);
    }

    /**
     * Liste des communes
     * 
     * GET /api/v1/geographie/communes
     * 
     * @OA\Get(
     *     path="/geographie/communes",
     *     tags={"Géographie"},
     *     summary="Liste des communes",
     *     description="Retourne la liste de toutes les communes du Bénin",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="departement_id",
     *         in="query",
     *         description="Filtrer par département",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche par nom ou code",
     *         required=false,
     *         @OA\Schema(type="string", example="ABOMEY")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des communes",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="AL-BAK"),
     *                     @OA\Property(property="nom", type="string", example="Banikoara"),
     *                     @OA\Property(property="departement_id", type="integer", example=1),
     *                     @OA\Property(property="departement", type="string", example="Alibori"),
     *                     @OA\Property(property="departement_code", type="string", example="AL")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=77)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function communes(Request $request): JsonResponse
    {
        $query = DB::table('communes as co')
            ->join('departements as d', 'co.departement_id', '=', 'd.id')
            ->select(
                'co.id',
                'co.code',
                'co.nom',
                'co.departement_id',
                'd.nom as departement',
                'd.code as departement_code'
            );
        
        // Filtrer par département
        if ($request->has('departement_id')) {
            $query->where('co.departement_id', $request->departement_id);
        }
        
        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('co.nom', 'ILIKE', "%{$search}%")
                  ->orWhere('co.code', 'ILIKE', "%{$search}%");
            });
        }
        
        $communes = $query->orderBy('co.code')->get();

        return response()->json([
            'success' => true,
            'data' => $communes,
            'count' => $communes->count(),
        ]);
    }

    /**
     * Liste des arrondissements
     * 
     * GET /api/v1/geographie/arrondissements
     * 
     * @OA\Get(
     *     path="/geographie/arrondissements",
     *     tags={"Géographie"},
     *     summary="Liste des arrondissements",
     *     description="Retourne la liste de tous les arrondissements",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="commune_id",
     *         in="query",
     *         description="Filtrer par commune",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="departement_id",
     *         in="query",
     *         description="Filtrer par département",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche par nom ou code",
     *         required=false,
     *         @OA\Schema(type="string", example="CENTRE")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des arrondissements",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="AL-BAK-01"),
     *                     @OA\Property(property="nom", type="string", example="Banikoara Centre"),
     *                     @OA\Property(property="commune_id", type="integer", example=1),
     *                     @OA\Property(property="commune", type="string", example="Banikoara"),
     *                     @OA\Property(property="departement", type="string", example="Alibori")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function arrondissements(Request $request): JsonResponse
    {
        $query = DB::table('arrondissements as a')
            ->join('communes as co', 'a.commune_id', '=', 'co.id')
            ->join('departements as d', 'co.departement_id', '=', 'd.id')
            ->select(
                'a.id',
                'a.code',
                'a.nom',
                'a.commune_id',
                'co.nom as commune',
                'd.nom as departement'
            );
        
        // Filtrer par commune
        if ($request->has('commune_id')) {
            $query->where('a.commune_id', $request->commune_id);
        }
        
        // Filtrer par département
        if ($request->has('departement_id')) {
            $query->where('co.departement_id', $request->departement_id);
        }
        
        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('a.nom', 'ILIKE', "%{$search}%")
                  ->orWhere('a.code', 'ILIKE', "%{$search}%");
            });
        }
        
        $arrondissements = $query->orderBy('a.code')->get();

        return response()->json([
            'success' => true,
            'data' => $arrondissements,
            'count' => $arrondissements->count(),
        ]);
    }

    /**
     * Liste des villages et quartiers
     * 
     * GET /api/v1/geographie/villages-quartiers
     * 
     * @OA\Get(
     *     path="/geographie/villages-quartiers",
     *     tags={"Géographie"},
     *     summary="Liste des villages et quartiers",
     *     description="Retourne la liste de tous les villages et quartiers de ville",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="arrondissement_id",
     *         in="query",
     *         description="Filtrer par arrondissement",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche par nom ou code",
     *         required=false,
     *         @OA\Schema(type="string", example="GANHI")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des villages/quartiers",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="AL-BAK-01-V01"),
     *                     @OA\Property(property="nom", type="string", example="Gbégourou"),
     *                     @OA\Property(property="type", type="string", example="village"),
     *                     @OA\Property(property="arrondissement_id", type="integer", example=1),
     *                     @OA\Property(property="arrondissement", type="string", example="Banikoara Centre"),
     *                     @OA\Property(property="commune", type="string", example="Banikoara")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function villagesQuartiers(Request $request): JsonResponse
    {
        $query = DB::table('villages_quartiers as v')
            ->join('arrondissements as a', 'v.arrondissement_id', '=', 'a.id')
            ->join('communes as co', 'a.commune_id', '=', 'co.id')
            ->select(
                'v.id',
                'v.code',
                'v.nom',
                'v.type_entite as type',
                'v.arrondissement_id',
                'a.nom as arrondissement',
                'co.nom as commune'
            );
        
        // Filtrer par arrondissement
        if ($request->has('arrondissement_id')) {
            $query->where('v.arrondissement_id', $request->arrondissement_id);
        }
        
        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('v.nom', 'ILIKE', "%{$search}%")
                  ->orWhere('v.code', 'ILIKE', "%{$search}%");
            });
        }
        
        $villages = $query->orderBy('v.code')->get();

        return response()->json([
            'success' => true,
            'data' => $villages,
            'count' => $villages->count(),
        ]);
    }

    /**
     * Créer un village/quartier
     * 
     * POST /api/v1/geographie/villages-quartiers
     * 
     * @OA\Post(
     *     path="/geographie/villages-quartiers",
     *     tags={"Géographie"},
     *     summary="Créer un village/quartier",
     *     description="Crée un nouveau village ou quartier de ville dans le système",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nom","arrondissement_id","type_entite"},
     *             @OA\Property(property="nom", type="string", maxLength=200, example="GANHI", description="Nom du village/quartier (sera converti en majuscules)"),
     *             @OA\Property(property="arrondissement_id", type="integer", example=1, description="ID de l'arrondissement"),
     *             @OA\Property(property="type_entite", type="string", enum={"village", "quartier"}, example="village", description="Type d'entité"),
     *             @OA\Property(property="code", type="string", maxLength=50, example="V-GANHI-01", description="Code unique (auto-généré si non fourni)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Village/Quartier créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Village/Quartier créé avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="V-ABC123"),
     *                 @OA\Property(property="nom", type="string", example="GANHI"),
     *                 @OA\Property(property="arrondissement_id", type="integer", example=1),
     *                 @OA\Property(property="type_entite", type="string", example="village")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function createVillageQuartier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:200',
            'arrondissement_id' => 'required|integer|exists:arrondissements,id',
            'type_entite' => 'required|string|in:village,quartier',
            'code' => 'nullable|string|max:50|unique:villages_quartiers,code',
        ]);
        
        // Générer un code si non fourni
        if (!isset($validated['code'])) {
            $prefix = strtoupper(substr($validated['type_entite'], 0, 1));
            $validated['code'] = $prefix . '-' . strtoupper(Str::random(6));
        }
        
        $validated['created_at'] = now();
        $validated['updated_at'] = now();
        
        $id = DB::table('villages_quartiers')->insertGetId($validated);
        $village = DB::table('villages_quartiers')->where('id', $id)->first();
        
        return response()->json([
            'success' => true,
            'message' => 'Village/Quartier créé avec succès',
            'data' => $village,
        ], 201);
    }

    /**
     * Liste des centres de vote
     * 
     * GET /api/v1/geographie/centres-vote
     * 
     * @OA\Get(
     *     path="/geographie/centres-vote",
     *     tags={"Géographie"},
     *     summary="Liste des centres de vote",
     *     description="Retourne la liste des centres de vote avec possibilité de filtrage par village/quartier",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="village_quartier_id",
     *         in="query",
     *         description="Filtrer par village/quartier",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="arrondissement_id",
     *         in="query",
     *         description="Filtrer par arrondissement",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche par nom ou code",
     *         required=false,
     *         @OA\Schema(type="string", example="EPP")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des centres de vote",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="CV-ABC123"),
     *                     @OA\Property(property="nom", type="string", example="EPP GANHI"),
     *                     @OA\Property(property="village_quartier_id", type="integer", example=1),
     *                     @OA\Property(property="type_etablissement", type="string", example="École"),
     *                     @OA\Property(property="adresse", type="string", example="Rue de l'école"),
     *                     @OA\Property(property="village_quartier", type="string", example="GANHI")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function centresVote(Request $request): JsonResponse
    {
        $query = DB::table('centres_vote as cv')
            ->leftJoin('villages_quartiers as vq', 'cv.village_quartier_id', '=', 'vq.id')
            ->select(
                'cv.id',
                'cv.code',
                'cv.nom',
                'cv.village_quartier_id',
                'cv.type_etablissement',
                'cv.adresse',
                'vq.nom as village_quartier'
            );
        
        // Filtrer par village/quartier
        if ($request->has('village_quartier_id')) {
            $query->where('cv.village_quartier_id', $request->village_quartier_id);
        }
        
        // Filtrer par arrondissement
        if ($request->has('arrondissement_id')) {
            $query->where('cv.arrondissement_id', $request->arrondissement_id);
        }
        
        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('cv.nom', 'ILIKE', "%{$search}%")
                  ->orWhere('cv.code', 'ILIKE', "%{$search}%");
            });
        }
        
        $centres = $query->orderBy('cv.nom')->get();
        
        return response()->json([
            'success' => true,
            'data' => $centres,
        ]);
    }

    /**
     * Créer un centre de vote
     * 
     * POST /api/v1/geographie/centres-vote
     * 
     * @OA\Post(
     *     path="/geographie/centres-vote",
     *     tags={"Géographie"},
     *     summary="Créer un centre de vote",
     *     description="Crée un nouveau centre de vote dans le système",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nom","village_quartier_id"},
     *             @OA\Property(property="nom", type="string", maxLength=255, example="EPP GANHI", description="Nom du centre de vote (sera converti en majuscules)"),
     *             @OA\Property(property="village_quartier_id", type="integer", example=1, description="ID du village/quartier"),
     *             @OA\Property(property="arrondissement_id", type="integer", example=1, description="ID de l'arrondissement (optionnel)"),
     *             @OA\Property(property="commune_id", type="integer", example=1, description="ID de la commune (optionnel)"),
     *             @OA\Property(property="code", type="string", maxLength=50, example="CV-GANHI-01", description="Code unique (auto-généré si non fourni)"),
     *             @OA\Property(property="type_etablissement", type="string", maxLength=100, example="École", description="Type d'établissement"),
     *             @OA\Property(property="adresse", type="string", example="Rue de l'école", description="Adresse du centre")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Centre de vote créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Centre de vote créé avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="CV-ABC123"),
     *                 @OA\Property(property="nom", type="string", example="EPP GANHI")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function createCentreVote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'village_quartier_id' => 'required|integer|exists:villages_quartiers,id',
            'arrondissement_id' => 'nullable|integer|exists:arrondissements,id',
            'commune_id' => 'nullable|integer|exists:communes,id',
            'code' => 'nullable|string|max:50|unique:centres_vote,code',
            'type_etablissement' => 'nullable|string|max:100',
            'adresse' => 'nullable|string',
        ]);
        
        // Générer un code si non fourni
        if (!isset($validated['code'])) {
            $validated['code'] = 'CV-' . strtoupper(Str::random(6));
        }
        
        $validated['created_at'] = now();
        $validated['updated_at'] = now();
        
        $id = DB::table('centres_vote')->insertGetId($validated);
        $centre = DB::table('centres_vote')->where('id', $id)->first();
        
        return response()->json([
            'success' => true,
            'message' => 'Centre de vote créé avec succès',
            'data' => $centre,
        ], 201);
    }

    /**
     * Liste des postes de vote
     * 
     * GET /api/v1/geographie/postes-vote
     * 
     * @OA\Get(
     *     path="/geographie/postes-vote",
     *     tags={"Géographie"},
     *     summary="Liste des postes de vote",
     *     description="Retourne la liste des postes de vote avec possibilité de filtrage par centre de vote",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="centre_vote_id",
     *         in="query",
     *         description="Filtrer par centre de vote",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="village_quartier_id",
     *         in="query",
     *         description="Filtrer par village/quartier",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche par nom ou code",
     *         required=false,
     *         @OA\Schema(type="string", example="PV01")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des postes de vote",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="PV-ABC123"),
     *                     @OA\Property(property="nom", type="string", example="PV01"),
     *                     @OA\Property(property="centre_vote_id", type="integer", example=1),
     *                     @OA\Property(property="electeurs_inscrits", type="integer", example=450),
     *                     @OA\Property(property="centre_vote", type="string", example="EPP GANHI")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function postesVote(Request $request): JsonResponse
    {
        $query = DB::table('postes_vote as pv')
            ->leftJoin('centres_vote as cv', 'pv.centre_vote_id', '=', 'cv.id')
            ->select(
                'pv.id',
                'pv.code',
                'pv.nom',
                'pv.centre_vote_id',
                'pv.electeurs_inscrits',
                'cv.nom as centre_vote'
            );
        
        // Filtrer par centre de vote
        if ($request->has('centre_vote_id')) {
            $query->where('pv.centre_vote_id', $request->centre_vote_id);
        }
        
        // Filtrer par village/quartier
        if ($request->has('village_quartier_id')) {
            $query->where('pv.village_quartier_id', $request->village_quartier_id);
        }
        
        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('pv.nom', 'ILIKE', "%{$search}%")
                  ->orWhere('pv.code', 'ILIKE', "%{$search}%");
            });
        }
        
        $postes = $query->orderBy('pv.nom')->get();
        
        return response()->json([
            'success' => true,
            'data' => $postes,
        ]);
    }

    /**
     * Créer un poste de vote
     * 
     * POST /api/v1/geographie/postes-vote
     * 
     * @OA\Post(
     *     path="/geographie/postes-vote",
     *     tags={"Géographie"},
     *     summary="Créer un poste de vote",
     *     description="Crée un nouveau poste de vote (bureau de vote) dans le système",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nom","centre_vote_id"},
     *             @OA\Property(property="nom", type="string", maxLength=200, example="PV01", description="Nom du poste (sera converti en majuscules)"),
     *             @OA\Property(property="centre_vote_id", type="integer", example=1, description="ID du centre de vote"),
     *             @OA\Property(property="village_quartier_id", type="integer", example=1, description="ID du village/quartier (optionnel)"),
     *             @OA\Property(property="code", type="string", maxLength=50, example="PV-GANHI-01", description="Code unique (auto-généré si non fourni)"),
     *             @OA\Property(property="electeurs_inscrits", type="integer", minimum=0, example=450, description="Nombre d'électeurs inscrits")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Poste de vote créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Poste de vote créé avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="PV-ABC123"),
     *                 @OA\Property(property="nom", type="string", example="PV01"),
     *                 @OA\Property(property="centre_vote_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function createPosteVote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:200',
            'centre_vote_id' => 'required|integer|exists:centres_vote,id',
            'village_quartier_id' => 'nullable|integer|exists:villages_quartiers,id',
            'code' => 'nullable|string|max:50|unique:postes_vote,code',
            'electeurs_inscrits' => 'nullable|integer|min:0',
        ]);
        
        // Générer un code si non fourni
        if (!isset($validated['code'])) {
            $validated['code'] = 'PV-' . strtoupper(Str::random(6));
        }
        
        $validated['actif'] = true;
        $validated['created_at'] = now();
        $validated['updated_at'] = now();
        
        $id = DB::table('postes_vote')->insertGetId($validated);
        $poste = DB::table('postes_vote')->where('id', $id)->first();
        
        return response()->json([
            'success' => true,
            'message' => 'Poste de vote créé avec succès',
            'data' => $poste,
        ], 201);
    }

    /**
     * Hiérarchie géographique complète
     * 
     * GET /api/v1/geographie/hierarchie
     * 
     * @OA\Get(
     *     path="/geographie/hierarchie",
     *     tags={"Géographie"},
     *     summary="Hiérarchie géographique complète",
     *     description="Retourne la hiérarchie géographique complète : départements, circonscriptions et communes",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Hiérarchie géographique",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(
     *                         property="departement",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="AL"),
     *                         @OA\Property(property="nom", type="string", example="Alibori")
     *                     ),
     *                     @OA\Property(
     *                         property="circonscriptions",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="code", type="string"),
     *                             @OA\Property(property="nom", type="string"),
     *                             @OA\Property(property="nombre_sieges_total", type="integer")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="communes",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="code", type="string"),
     *                             @OA\Property(property="nom", type="string")
     *                         )
     *                     ),
     *                     @OA\Property(property="nb_circonscriptions", type="integer", example=2),
     *                     @OA\Property(property="nb_communes", type="integer", example=6)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function hierarchie(): JsonResponse
    {
        $departements = DB::table('departements')
            ->select('id', 'code', 'nom')
            ->orderBy('code')
            ->get();

        $hierarchie = [];

        foreach ($departements as $dept) {
            $circonscriptions = DB::table('circonscriptions_electorales')
                ->where('departement_id', $dept->id)
                ->select('id', 'code', 'nom', 'nombre_sieges_total')
                ->get();

            $communes = DB::table('communes')
                ->where('departement_id', $dept->id)
                ->select('id', 'code', 'nom')
                ->get();

            $hierarchie[] = [
                'departement' => $dept,
                'circonscriptions' => $circonscriptions,
                'communes' => $communes,
                'nb_circonscriptions' => $circonscriptions->count(),
                'nb_communes' => $communes->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $hierarchie,
        ]);
    }

    /**
     * Hiérarchie pour un département spécifique
     * 
     * GET /api/v1/geographie/hierarchie/{departementId}
     * 
     * @OA\Get(
     *     path="/geographie/hierarchie/{departementId}",
     *     tags={"Géographie"},
     *     summary="Hiérarchie d'un département",
     *     description="Retourne la hiérarchie géographique complète d'un département spécifique avec statistiques",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="departementId",
     *         in="path",
     *         description="ID du département",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hiérarchie du département",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="departement",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="AL"),
     *                     @OA\Property(property="nom", type="string", example="Alibori")
     *                 ),
     *                 @OA\Property(
     *                     property="circonscriptions",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(
     *                     property="communes",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(
     *                     property="arrondissements",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(
     *                     property="statistiques",
     *                     type="object",
     *                     @OA\Property(property="nb_circonscriptions", type="integer", example=2),
     *                     @OA\Property(property="nb_communes", type="integer", example=6),
     *                     @OA\Property(property="nb_arrondissements", type="integer", example=42),
     *                     @OA\Property(property="total_sieges", type="integer", example=6)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Département non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Département non trouvé")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function hierarchieParDepartement(int $departementId): JsonResponse
    {
        $departement = DB::table('departements')
            ->where('id', $departementId)
            ->select('id', 'code', 'nom')
            ->first();

        if (!$departement) {
            return response()->json([
                'success' => false,
                'message' => 'Département non trouvé',
            ], 404);
        }

        $circonscriptions = DB::table('circonscriptions_electorales')
            ->where('departement_id', $departementId)
            ->select('id', 'code', 'nom', 'nombre_sieges_total')
            ->get();

        $communes = DB::table('communes')
            ->where('departement_id', $departementId)
            ->select('id', 'code', 'nom')
            ->get();

        $arrondissements = DB::table('arrondissements as a')
            ->join('communes as co', 'a.commune_id', '=', 'co.id')
            ->where('co.departement_id', $departementId)
            ->select('a.id', 'a.code', 'a.nom', 'a.commune_id', 'co.nom as commune_nom')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'departement' => $departement,
                'circonscriptions' => $circonscriptions,
                'communes' => $communes,
                'arrondissements' => $arrondissements,
                'statistiques' => [
                    'nb_circonscriptions' => $circonscriptions->count(),
                    'nb_communes' => $communes->count(),
                    'nb_arrondissements' => $arrondissements->count(),
                    'total_sieges' => $circonscriptions->sum('nombre_sieges_total'),
                ],
            ],
        ]);
    }

  /**
 * Liste des coordonnateurs (filtrée par arrondissement) avec zones
 * 
 * GET /api/v1/geographie/coordonnateurs?arrondissement_id=X
 * 
 /**
 * Liste des coordonnateurs avec zones (si arrondissement a plusieurs coordonnateurs)
 * 
 * GET /api/v1/geographie/coordonnateurs?arrondissement_id=X
 * 
 * LOGIQUE :
 * - Si arrondissement a plusieurs coordonnateurs → Afficher "NOM (ZONE)"
 * - Si arrondissement a 1 seul coordonnateur → Afficher juste "NOM"
 */
/**
 * Liste des coordonnateurs avec zones (si arrondissement a plusieurs coordonnateurs)
 * 
 * GET /api/v1/geographie/coordonnateurs?arrondissement_id=X
 * 
 * LOGIQUE :
 * - Si arrondissement a plusieurs coordonnateurs → Afficher "NOM (ZONE)"
 * - Si arrondissement a 1 seul coordonnateur → Afficher juste "NOM"
 */
public function coordonnateurs(Request $request): JsonResponse
{
    try {
        $arrondissementId = $request->query('arrondissement_id');
        
        // Récupérer tous les coordonnateurs (ou filtrés par arrondissement)
        $query = DB::table('coordonnateurs')
            ->select(
                'id',
                'nom',
                'telephone',
                'email',
                'arrondissement_id',
                'arrondissement_zone',
                'actif'
            )
            ->where('actif', true);
        
        if ($arrondissementId) {
            $query->where('arrondissement_id', $arrondissementId);
        }
        
        $coordonnateurs = $query->orderBy('nom')->get();
        
        // ✅ Compter les coordonnateurs par arrondissement
        $countByArrondissement = DB::table('coordonnateurs')
            ->select('arrondissement_id', DB::raw('COUNT(*) as count'))
            ->where('actif', true)
            ->groupBy('arrondissement_id')
            ->pluck('count', 'arrondissement_id');
        
        // ✅ Construire le nom avec zone si nécessaire
        $coordonnateursMapped = $coordonnateurs->map(function ($c) use ($countByArrondissement) {
            $count = $countByArrondissement[$c->arrondissement_id] ?? 1;
            
            // Si plusieurs coordonnateurs pour cet arrondissement → Ajouter la zone
            if ($count > 1 && !empty($c->arrondissement_zone)) {
                $nomComplet = $c->nom . ' (' . $c->arrondissement_zone . ')';
            } else {
                $nomComplet = $c->nom;
            }
            
            return [
                'id' => $c->id,
                'nom' => $nomComplet,
                'nom_base' => $c->nom,
                'telephone' => $c->telephone,
                'email' => $c->email,
                'arrondissement_id' => $c->arrondissement_id,
                'arrondissement_zone' => $c->arrondissement_zone,
                'actif' => $c->actif,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $coordonnateursMapped->values(),
            'count' => $coordonnateursMapped->count(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du chargement des coordonnateurs',
            'error' => $e->getMessage(),
        ], 500);
    }
}

/**
 * Récupérer le nombre d'électeurs inscrits pour un niveau géographique
 */
public function getNombreInscrits(Request $request): JsonResponse
{
    $niveau = $request->input('niveau'); // 'arrondissement' ou 'village_quartier'
    $niveauId = $request->input('niveau_id');
    
    if (!$niveau || !$niveauId) {
        return response()->json([
            'success' => false,
            'message' => 'niveau et niveau_id requis'
        ], 400);
    }
    
    $nombreInscrits = 0;
    
    try {
        if ($niveau === 'arrondissement') {
            $arrondissement = Arrondissement::find($niveauId);
            $nombreInscrits = $arrondissement ? $arrondissement->nombre_inscrits : 0;
        } elseif ($niveau === 'village_quartier') {
            $village = VillageQuartier::find($niveauId);
            $nombreInscrits = $village ? $village->nombre_inscrits : 0;
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'nombre_inscrits' => $nombreInscrits,
            ],
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur récupération inscrits',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}