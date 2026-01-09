<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

/**
 * GeographieController - CORRIGÉ
 * ✅ Retire nombre_inscrits de la table arrondissements (n'existe pas)
 * ✅ Le calcul se fera côté backend si nécessaire
 */
class GeographieController extends Controller
{
    /**
     * Liste des départements
     * GET /api/v1/geographie/departements
     */
    public function departements(Request $request): JsonResponse
    {
        $query = DB::table('departements')
            ->select('id', 'code', 'nom');
        
        if ($request->has('search') || $request->has('q')) {
            $search = $request->search ?? $request->q;
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
     * Liste des communes
     * GET /api/v1/geographie/communes
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
        
        if ($request->has('departement_id')) {
            $query->where('co.departement_id', $request->departement_id);
        }
        
        if ($request->has('search') || $request->has('q')) {
            $search = $request->search ?? $request->q;
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
     * GET /api/v1/geographie/arrondissements
     * 
     * ✅ CORRECTION : Calcule nombre_inscrits via postes_vote
     */
    public function arrondissements(Request $request): JsonResponse
    {
        // Sous-requête pour calculer les inscrits par arrondissement
        $inscritsSubQuery = DB::table('postes_vote as pv')
            ->join('villages_quartiers as vq', 'pv.village_quartier_id', '=', 'vq.id')
            ->select('vq.arrondissement_id')
            ->selectRaw('COALESCE(SUM(pv.electeurs_inscrits), 0) as nombre_inscrits')
            ->groupBy('vq.arrondissement_id');
        
        $query = DB::table('arrondissements as a')
            ->join('communes as co', 'a.commune_id', '=', 'co.id')
            ->join('departements as d', 'co.departement_id', '=', 'd.id')
            ->leftJoinSub($inscritsSubQuery, 'inscrits', function($join) {
                $join->on('a.id', '=', 'inscrits.arrondissement_id');
            })
            ->select(
                'a.id',
                'a.code',
                'a.nom',
                'a.commune_id',
                'co.nom as commune',
                'd.nom as departement',
                DB::raw('COALESCE(inscrits.nombre_inscrits, 0) as nombre_inscrits')
            );
        
        if ($request->has('commune_id')) {
            $query->where('a.commune_id', $request->commune_id);
        }
        
        if ($request->has('departement_id')) {
            $query->where('co.departement_id', $request->departement_id);
        }
        
        if ($request->has('search') || $request->has('q')) {
            $search = $request->search ?? $request->q;
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
     * Détails d'un arrondissement
     * GET /api/v1/geographie/arrondissements/{id}
     * 
     * ✅ NOUVEAU : Calcule le nombre d'inscrits depuis villages_quartiers
     */
    public function arrondissement(int $id): JsonResponse
    {
        // Récupérer l'arrondissement
        $arrondissement = DB::table('arrondissements as a')
            ->join('communes as co', 'a.commune_id', '=', 'co.id')
            ->join('departements as d', 'co.departement_id', '=', 'd.id')
            ->where('a.id', $id)
            ->select(
                'a.*',
                'co.nom as commune',
                'd.nom as departement'
            )
            ->first();

        if (!$arrondissement) {
            return response()->json([
                'success' => false,
                'message' => 'Arrondissement non trouvé',
            ], 404);
        }

        // ✅ Calculer le nombre d'inscrits via postes_vote
        $nombreInscrits = DB::table('postes_vote as pv')
            ->join('villages_quartiers as vq', 'pv.village_quartier_id', '=', 'vq.id')
            ->where('vq.arrondissement_id', $id)
            ->sum('pv.electeurs_inscrits');

        // Ajouter le nombre d'inscrits calculé
        $arrondissement->nombre_inscrits = $nombreInscrits ?? 0;

        return response()->json([
            'success' => true,
            'data' => $arrondissement,
        ]);
    }

    /**
     * Liste des villages et quartiers
     * GET /api/v1/geographie/villages-quartiers
     * 
     * ✅ Calcule nombre_inscrits via postes_vote
     */
    public function villagesQuartiers(Request $request): JsonResponse
{
    try {
        $query = DB::table('villages_quartiers as v')
            ->join('arrondissements as a', 'v.arrondissement_id', '=', 'a.id')
            ->join('communes as co', 'a.commune_id', '=', 'co.id')
            ->select(
                'v.id',
                'v.code',
                'v.nom',
                'v.type_entite', // ✅ CORRIGÉ: Utiliser type_entite au lieu de type
                'v.arrondissement_id',
                'a.nom as arrondissement',
                'a.code as arrondissement_code',
                'co.nom as commune',
                'co.code as commune_code'
            );
        
        // ✅ Filtrer par arrondissement (IMPORTANT pour le frontend)
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

    } catch (\Exception $e) {
        \Log::error('Erreur GeographieController@villagesQuartiers:', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des villages/quartiers',
            'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
        ], 500);
    }
}

    /**
     * Détails d'un village/quartier
     * GET /api/v1/geographie/villages-quartiers/{id}
     * 
     * ✅ Calcule nombre_inscrits via postes_vote
     */
    public function villageQuartier(int $id): JsonResponse
    {
        $village = DB::table('villages_quartiers as v')
            ->join('arrondissements as a', 'v.arrondissement_id', '=', 'a.id')
            ->join('communes as co', 'a.commune_id', '=', 'co.id')
            ->join('departements as d', 'co.departement_id', '=', 'd.id')
            ->where('v.id', $id)
            ->select(
                'v.*',
                'a.nom as arrondissement',
                'co.nom as commune',
                'd.nom as departement'
            )
            ->first();

        if (!$village) {
            return response()->json([
                'success' => false,
                'message' => 'Village/Quartier non trouvé',
            ], 404);
        }

        // ✅ Calculer le nombre d'inscrits via postes_vote
        $nombreInscrits = DB::table('postes_vote')
            ->where('village_quartier_id', $id)
            ->sum('electeurs_inscrits');
        
        $village->nombre_inscrits = $nombreInscrits ?? 0;

        return response()->json([
            'success' => true,
            'data' => $village,
        ]);
    }

    /**
     * Liste des centres de vote
     * GET /api/v1/geographie/centres-vote
     */
    /**
 * Liste des centres de vote
 * 
 * GET /api/v1/geographie/centres-vote
 * Query params: ?village_quartier_id=X
 */
public function centresVote(Request $request): JsonResponse
{
    try {
        $query = DB::table('centres_vote as c')
            ->leftJoin('villages_quartiers as v', 'c.village_quartier_id', '=', 'v.id')
            ->leftJoin('arrondissements as a', 'c.arrondissement_id', '=', 'a.id')
            ->leftJoin('communes as co', 'c.commune_id', '=', 'co.id')
            ->select(
                'c.id',
                'c.code',
                'c.nom',
                'c.village_quartier_id',
                'c.arrondissement_id',
                'c.commune_id',
                'c.type_etablissement',
                'v.nom as village_quartier',
                'a.nom as arrondissement',
                'co.nom as commune'
            );
        
        // ✅ Filtrer par village/quartier
        if ($request->has('village_quartier_id')) {
            $query->where('c.village_quartier_id', $request->village_quartier_id);
        }
        
        // Filtrer par arrondissement
        if ($request->has('arrondissement_id')) {
            $query->where('c.arrondissement_id', $request->arrondissement_id);
        }
        
        // Filtrer par commune
        if ($request->has('commune_id')) {
            $query->where('c.commune_id', $request->commune_id);
        }
        
        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('c.nom', 'ILIKE', "%{$search}%")
                  ->orWhere('c.code', 'ILIKE', "%{$search}%");
            });
        }
        
        $centres = $query->orderBy('c.code')->get();

        return response()->json([
            'success' => true,
            'data' => $centres,
            'count' => $centres->count(),
        ]);

    } catch (\Exception $e) {
        \Log::error('Erreur GeographieController@centresVote:', [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des centres de vote',
            'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
        ], 500);
    }
}

/**
 * Liste des postes de vote
 * 
 * GET /api/v1/geographie/postes-vote
 * Query params: ?centre_vote_id=X ou ?village_quartier_id=X
 */
public function postesVote(Request $request): JsonResponse
{
    try {
        $query = DB::table('postes_vote as p')
            ->leftJoin('centres_vote as c', 'p.centre_vote_id', '=', 'c.id')
            ->leftJoin('villages_quartiers as v', 'p.village_quartier_id', '=', 'v.id')
            ->select(
                'p.id',
                'p.code',
                'p.nom',
                'p.centre_vote_id',
                'p.village_quartier_id',
                'p.electeurs_inscrits',
                'p.actif',
                'c.nom as centre_vote',
                'v.nom as village_quartier'
            );
        
        // ✅ Filtrer par centre de vote
        if ($request->has('centre_vote_id')) {
            $query->where('p.centre_vote_id', $request->centre_vote_id);
        }
        
        // Filtrer par village/quartier
        if ($request->has('village_quartier_id')) {
            $query->where('p.village_quartier_id', $request->village_quartier_id);
        }
        
        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('p.nom', 'ILIKE', "%{$search}%")
                  ->orWhere('p.code', 'ILIKE', "%{$search}%");
            });
        }
        
        $postes = $query
            ->where('p.actif', true)
            ->orderBy('p.code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $postes,
            'count' => $postes->count(),
        ]);

    } catch (\Exception $e) {
        \Log::error('Erreur GeographieController@postesVote:', [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des postes de vote',
            'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
        ], 500);
    }
}
    /**
     * Détails d'un poste de vote
     * GET /api/v1/geographie/postes-vote/{id}
     */
    public function posteVote(int $id): JsonResponse
    {
        $poste = DB::table('postes_vote as p')
            ->leftJoin('centres_vote as c', 'p.centre_vote_id', '=', 'c.id')
            ->leftJoin('villages_quartiers as v', 'p.village_quartier_id', '=', 'v.id')
            ->where('p.id', $id)
            ->select(
                'p.*',
                'c.nom as centre_vote',
                'v.nom as village_quartier'
            )
            ->first();

        if (!$poste) {
            return response()->json([
                'success' => false,
                'message' => 'Poste de vote non trouvé',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $poste,
        ]);
    }
}