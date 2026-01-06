<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

/**
 * AgregationController
 * 
 * Gestion des agrégations de résultats électoraux
 */
class AgregationController extends Controller
{
    /**
     * Agrégation par niveau géographique
     * 
     * GET /api/v1/agregations/{niveau}/{niveauId}
     * 
     * @OA\Get(
     *     path="/agregations/{niveau}/{niveauId}",
     *     tags={"Agrégations"},
     *     summary="Agrégation par niveau géographique",
     *     description="Retourne les résultats agrégés pour un niveau géographique spécifique (bureau, arrondissement, commune, circonscription, national)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="niveau",
     *         in="path",
     *         description="Niveau géographique",
     *         required=true,
     *         @OA\Schema(type="string", enum={"bureau", "arrondissement", "commune", "circonscription", "national"}, example="circonscription")
     *     ),
     *     @OA\Parameter(
     *         name="niveauId",
     *         in="path",
     *         description="ID de l'entité géographique",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agrégations du niveau",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="niveau", type="string", example="circonscription"),
     *                 @OA\Property(property="niveau_id", type="integer", example=1),
     *                 @OA\Property(
     *                     property="agregations",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="election", type="string"),
     *                         @OA\Property(property="entite_nom", type="string"),
     *                         @OA\Property(property="sigle", type="string"),
     *                         @OA\Property(property="total_voix", type="integer"),
     *                         @OA\Property(property="pourcentage_exprimes", type="number", format="float"),
     *                         @OA\Property(property="sieges_obtenus", type="integer"),
     *                         @OA\Property(property="rang", type="integer")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Niveau invalide"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function parNiveau(string $niveau, int $niveauId): JsonResponse
    {
        // ✅ CORRECTION: Niveaux valides basés sur CHECK constraint
        $niveauxValides = ['bureau', 'arrondissement', 'commune', 'circonscription', 'national'];
        
        if (!in_array($niveau, $niveauxValides)) {
            return response()->json([
                'success' => false,
                'message' => "Niveau invalide. Niveaux autorisés : " . implode(', ', $niveauxValides),
            ], 400);
        }

        // ✅ CORRECTION: Table 'agregations_calculs' (pas 'agregations_resultats')
        $agregations = DB::table('agregations_calculs as ag')
            ->leftJoin('candidatures as c', 'ag.candidature_id', '=', 'c.id')
            ->leftJoin('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
            ->leftJoin('elections as e', 'ag.election_id', '=', 'e.id')
            ->where('ag.niveau', $niveau)
            ->where('ag.niveau_id', $niveauId)
            ->select(
                'ag.id',
                'ag.election_id',
                'e.nom as election',
                'ag.candidature_id',
                'ep.nom as entite_nom',
                'ep.sigle',
                'ag.total_voix',
                'ag.total_inscrits',
                'ag.total_votants',
                'ag.total_bulletins_nuls',
                'ag.total_suffrages_exprimes',
                'ag.pourcentage_inscrits',
                'ag.pourcentage_exprimes',
                'ag.sieges_obtenus',
                'ag.rang',
                'ag.statut',
                'ag.calcule_a'
            )
            ->orderBy('ag.total_voix', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'niveau' => $niveau,
                'niveau_id' => $niveauId,
                'agregations' => $agregations,
            ],
            'count' => $agregations->count(),
        ]);
    }

    /**
     * Calculer les agrégations pour une élection
     * 
     * POST /api/v1/agregations/calculer/{electionId}
     * 
     * @OA\Post(
     *     path="/agregations/calculer/{electionId}",
     *     tags={"Agrégations"},
     *     summary="Calculer les agrégations",
     *     description="Lance le calcul des agrégations pour une élection à différents niveaux géographiques (national, circonscription, etc.)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="electionId",
     *         in="path",
     *         description="ID de l'élection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="niveaux",
     *                 type="array",
     *                 @OA\Items(type="string", enum={"national", "circonscription", "commune", "arrondissement", "bureau"}),
     *                 example={"national", "circonscription"},
     *                 description="Niveaux à calculer (défaut: national et circonscription)"
     *             ),
     *             @OA\Property(
     *                 property="recalculer",
     *                 type="boolean",
     *                 example=false,
     *                 description="Forcer le recalcul si des agrégations existent déjà"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Calcul terminé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Calcul des agrégations terminé avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="election_id", type="integer", example=1),
     *                 @OA\Property(property="pv_valides", type="integer", example=15456),
     *                 @OA\Property(property="niveaux_calcules", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="details_par_niveau", type="object"),
     *                 @OA\Property(property="agregations_totales", type="integer", example=192)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Aucun PV validé ou agrégations existantes"),
     *     @OA\Response(response=404, description="Élection non trouvée"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function calculer(Request $request, int $electionId): JsonResponse
    {
        // Vérifier que l'élection existe
        $election = DB::table('elections')->where('id', $electionId)->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }

        $validated = $request->validate([
            'niveaux' => 'sometimes|array',
            'niveaux.*' => 'string|in:national,circonscription,commune,arrondissement,bureau',
            'recalculer' => 'sometimes|boolean',
        ]);

        // Niveaux par défaut : national et circonscription
        $niveaux = $validated['niveaux'] ?? ['national', 'circonscription'];

        // Récupérer tous les PV validés de cette élection
        $pvsValides = DB::table('proces_verbaux')
            ->where('election_id', $electionId)
            ->where('statut', 'valide')
            ->count();

        if ($pvsValides === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun PV validé trouvé pour cette élection',
            ], 400);
        }

        // Vérifier si des agrégations existent déjà
        $agregationsExistantes = DB::table('agregations_calculs')
            ->where('election_id', $electionId)
            ->count();

        $recalculer = $validated['recalculer'] ?? false;

        if ($agregationsExistantes > 0 && !$recalculer) {
            return response()->json([
                'success' => false,
                'message' => "Des agrégations existent déjà pour cette élection. Utilisez 'recalculer: true' pour forcer le recalcul.",
                'agregations_existantes' => $agregationsExistantes,
            ], 400);
        }

        // Si recalcul demandé, supprimer les anciennes agrégations
        if ($recalculer && $agregationsExistantes > 0) {
            DB::table('agregations_calculs')
                ->where('election_id', $electionId)
                ->delete();
        }

        $resultats = [];

        // Calculer les agrégations pour chaque niveau demandé
        foreach ($niveaux as $niveau) {
            switch ($niveau) {
                case 'national':
                    $this->calculerNiveauNational($electionId);
                    $resultats['national'] = DB::table('agregations_calculs')
                        ->where('election_id', $electionId)
                        ->where('niveau', 'national')
                        ->count();
                    break;

                case 'circonscription':
                    $nbCalcules = $this->calculerNiveauCirconscription($electionId);
                    $resultats['circonscription'] = $nbCalcules;
                    break;

                // Les autres niveaux peuvent être ajoutés ultérieurement
                default:
                    $resultats[$niveau] = 'Non implémenté';
            }
        }

        // Compter les nouvelles agrégations créées
        $nouvellesAgregations = DB::table('agregations_calculs')
            ->where('election_id', $electionId)
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Calcul des agrégations terminé avec succès',
            'data' => [
                'election_id' => $electionId,
                'pv_valides' => $pvsValides,
                'niveaux_calcules' => $niveaux,
                'details_par_niveau' => $resultats,
                'agregations_totales' => $nouvellesAgregations,
            ],
        ]);
    }

    /**
     * Calculer les agrégations au niveau national
     * 
     * @param int $electionId
     * @return void
     */
    private function calculerNiveauNational(int $electionId): void
    {
        // ✅ CORRECTION: Calculer le total des suffrages exprimés UNE SEULE FOIS pour toute l'élection
        $totauxElection = DB::table('proces_verbaux')
            ->where('election_id', $electionId)
            ->where('statut', 'valide')
            ->selectRaw('
                SUM(nombre_inscrits) as total_inscrits,
                SUM(nombre_votants) as total_votants,
                SUM(nombre_bulletins_nuls) as total_bulletins_nuls,
                SUM(nombre_suffrages_exprimes) as total_suffrages_exprimes
            ')
            ->first();

        // Récupérer toutes les candidatures pour cette élection
        $candidatures = DB::table('candidatures')
            ->where('election_id', $electionId)
            ->get();

        foreach ($candidatures as $candidature) {
            // Calculer seulement le total des voix pour cette candidature
            $totalVoix = DB::table('resultats as r')
                ->join('proces_verbaux as pv', 'r.proces_verbal_id', '=', 'pv.id')
                ->where('pv.election_id', $electionId)
                ->where('pv.statut', 'valide')
                ->where('r.candidature_id', $candidature->id)
                ->sum('r.nombre_voix');

            // Calculer les pourcentages
            $pourcentageInscrits = $totauxElection->total_inscrits > 0 
                ? round(($totalVoix / $totauxElection->total_inscrits) * 100, 2) 
                : 0;

            $pourcentageExprimes = $totauxElection->total_suffrages_exprimes > 0 
                ? round(($totalVoix / $totauxElection->total_suffrages_exprimes) * 100, 2) 
                : 0;

            // Insérer l'agrégation
            DB::table('agregations_calculs')->insert([
                'election_id' => $electionId,
                'candidature_id' => $candidature->id,
                'niveau' => 'national',
                'niveau_id' => null,
                'total_voix' => $totalVoix ?? 0,
                'total_inscrits' => $totauxElection->total_inscrits ?? 0,
                'total_votants' => $totauxElection->total_votants ?? 0,
                'total_bulletins_nuls' => $totauxElection->total_bulletins_nuls ?? 0,
                'total_suffrages_exprimes' => $totauxElection->total_suffrages_exprimes ?? 0,
                'pourcentage_inscrits' => $pourcentageInscrits,
                'pourcentage_exprimes' => $pourcentageExprimes,
                'sieges_obtenus' => 0,
                'statut' => 'provisoire',
                'calcule_a' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Calculer les rangs
        $this->calculerRangs($electionId, 'national', null);
    }

    /**
     * Calculer les agrégations au niveau circonscription
     * 
     * @param int $electionId
     * @return int Nombre de circonscriptions calculées
     */
    private function calculerNiveauCirconscription(int $electionId): int
    {
        // Récupérer toutes les circonscriptions
        $circonscriptions = DB::table('circonscriptions_electorales')->get();

        $nbCalcules = 0;

        foreach ($circonscriptions as $circonscription) {
            // ✅ CORRECTION: Calculer les totaux de la circonscription UNE SEULE FOIS
            $totauxCirconscription = DB::table('proces_verbaux as pv')
                ->join('postes_vote as p', 'pv.niveau_id', '=', 'p.id')
                ->join('centres_vote as cv', 'p.centre_vote_id', '=', 'cv.id')
                ->where('pv.election_id', $electionId)
                ->where('pv.niveau', 'bureau')
                ->where('pv.statut', 'valide')
                ->where('cv.circonscription_id', $circonscription->id)
                ->selectRaw('
                    SUM(pv.nombre_inscrits) as total_inscrits,
                    SUM(pv.nombre_votants) as total_votants,
                    SUM(pv.nombre_bulletins_nuls) as total_bulletins_nuls,
                    SUM(pv.nombre_suffrages_exprimes) as total_suffrages_exprimes,
                    COUNT(*) as nb_pv
                ')
                ->first();

            // Si pas de PV pour cette circonscription, on passe
            if (!$totauxCirconscription || $totauxCirconscription->nb_pv === 0) {
                continue;
            }

            // Récupérer toutes les candidatures pour cette élection
            $candidatures = DB::table('candidatures')
                ->where('election_id', $electionId)
                ->get();

            foreach ($candidatures as $candidature) {
                // Calculer seulement le total des voix pour cette candidature dans cette circonscription
                $totalVoix = DB::table('resultats as r')
                    ->join('proces_verbaux as pv', 'r.proces_verbal_id', '=', 'pv.id')
                    ->join('postes_vote as p', 'pv.niveau_id', '=', 'p.id')
                    ->join('centres_vote as cv', 'p.centre_vote_id', '=', 'cv.id')
                    ->where('pv.election_id', $electionId)
                    ->where('pv.niveau', 'bureau')
                    ->where('pv.statut', 'valide')
                    ->where('cv.circonscription_id', $circonscription->id)
                    ->where('r.candidature_id', $candidature->id)
                    ->sum('r.nombre_voix');

                // Si aucun résultat, passer
                if ($totalVoix === null || $totalVoix === 0) {
                    // On crée quand même l'entrée avec 0 voix pour garder la cohérence
                    $totalVoix = 0;
                }

                // Calculer les pourcentages
                $pourcentageInscrits = $totauxCirconscription->total_inscrits > 0 
                    ? round(($totalVoix / $totauxCirconscription->total_inscrits) * 100, 2) 
                    : 0;

                $pourcentageExprimes = $totauxCirconscription->total_suffrages_exprimes > 0 
                    ? round(($totalVoix / $totauxCirconscription->total_suffrages_exprimes) * 100, 2) 
                    : 0;

                // Insérer l'agrégation
                DB::table('agregations_calculs')->insert([
                    'election_id' => $electionId,
                    'candidature_id' => $candidature->id,
                    'niveau' => 'circonscription',
                    'niveau_id' => $circonscription->id,
                    'total_voix' => $totalVoix,
                    'total_inscrits' => $totauxCirconscription->total_inscrits ?? 0,
                    'total_votants' => $totauxCirconscription->total_votants ?? 0,
                    'total_bulletins_nuls' => $totauxCirconscription->total_bulletins_nuls ?? 0,
                    'total_suffrages_exprimes' => $totauxCirconscription->total_suffrages_exprimes ?? 0,
                    'pourcentage_inscrits' => $pourcentageInscrits,
                    'pourcentage_exprimes' => $pourcentageExprimes,
                    'sieges_obtenus' => 0,
                    'statut' => 'provisoire',
                    'calcule_a' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Calculer les rangs pour cette circonscription
            $this->calculerRangs($electionId, 'circonscription', $circonscription->id);

            $nbCalcules++;
        }

        return $nbCalcules;
    }

    /**
     * Calculer les rangs des candidatures
     * 
     * @param int $electionId
     * @param string $niveau
     * @param int|null $niveauId
     * @return void
     */
    private function calculerRangs(int $electionId, string $niveau, ?int $niveauId): void
    {
        $query = DB::table('agregations_calculs')
            ->where('election_id', $electionId)
            ->where('niveau', $niveau);

        if ($niveauId !== null) {
            $query->where('niveau_id', $niveauId);
        } else {
            $query->whereNull('niveau_id');
        }

        $agregations = $query->orderBy('total_voix', 'desc')->get();

        $rang = 1;
        foreach ($agregations as $agregation) {
            DB::table('agregations_calculs')
                ->where('id', $agregation->id)
                ->update(['rang' => $rang]);
            $rang++;
        }
    }

    /**
     * Résultats nationaux d'une élection
     * 
     * GET /api/v1/agregations/election/{electionId}/national
     * 
     * @OA\Get(
     *     path="/agregations/election/{electionId}/national",
     *     tags={"Agrégations"},
     *     summary="Résultats nationaux",
     *     description="Retourne les résultats agrégés au niveau national pour une élection",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="electionId",
     *         in="path",
     *         description="ID de l'élection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Résultats nationaux",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="election", type="object"),
     *                 @OA\Property(
     *                     property="resultats",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="entite_nom", type="string"),
     *                         @OA\Property(property="sigle", type="string"),
     *                         @OA\Property(property="numero_liste", type="integer"),
     *                         @OA\Property(property="total_voix", type="integer"),
     *                         @OA\Property(property="pourcentage_exprimes", type="number", format="float"),
     *                         @OA\Property(property="sieges_obtenus", type="integer"),
     *                         @OA\Property(property="rang", type="integer")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="totaux",
     *                     type="object",
     *                     @OA\Property(property="total_voix", type="integer"),
     *                     @OA\Property(property="total_inscrits", type="integer"),
     *                     @OA\Property(property="total_votants", type="integer")
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Élection non trouvée"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function national(int $electionId): JsonResponse
    {
        $election = DB::table('elections')->where('id', $electionId)->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }

        // ✅ CORRECTION: Table et colonnes correctes
        $resultats = DB::table('agregations_calculs as ag')
            ->join('candidatures as c', 'ag.candidature_id', '=', 'c.id')
            ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
            ->where('ag.election_id', $electionId)
            ->where('ag.niveau', 'national')
            ->select(
                'ag.id',
                'ep.nom as entite_nom',
                'ep.sigle',
                'c.numero_liste',
                'ag.total_voix',
                'ag.total_inscrits',
                'ag.total_votants',
                'ag.pourcentage_inscrits',
                'ag.pourcentage_exprimes',
                'ag.sieges_obtenus',
                'ag.rang',
                'ag.statut'
            )
            ->orderBy('ag.rang')
            ->get();

        // Calculer les totaux généraux
        $totaux = DB::table('agregations_calculs')
            ->where('election_id', $electionId)
            ->where('niveau', 'national')
            ->selectRaw('
                SUM(total_voix) as total_voix,
                MAX(total_inscrits) as total_inscrits,
                MAX(total_votants) as total_votants,
                MAX(total_bulletins_nuls) as total_bulletins_nuls,
                MAX(total_suffrages_exprimes) as total_suffrages_exprimes
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'election' => $election,
                'resultats' => $resultats,
                'totaux' => $totaux,
            ],
            'count' => $resultats->count(),
        ]);
    }

    /**
     * Résultats par circonscription
     * 
     * GET /api/v1/agregations/election/{electionId}/circonscription/{circonscriptionId}
     * 
     * @OA\Get(
     *     path="/agregations/election/{electionId}/circonscription/{circonscriptionId}",
     *     tags={"Agrégations"},
     *     summary="Résultats par circonscription",
     *     description="Retourne les résultats agrégés pour une circonscription spécifique",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="electionId",
     *         in="path",
     *         description="ID de l'élection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="circonscriptionId",
     *         in="path",
     *         description="ID de la circonscription",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Résultats de la circonscription",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="election", type="object"),
     *                 @OA\Property(property="circonscription", type="object"),
     *                 @OA\Property(
     *                     property="resultats",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="entite_nom", type="string"),
     *                         @OA\Property(property="sigle", type="string"),
     *                         @OA\Property(property="total_voix", type="integer"),
     *                         @OA\Property(property="pourcentage_exprimes", type="number"),
     *                         @OA\Property(property="sieges_obtenus", type="integer"),
     *                         @OA\Property(property="rang", type="integer")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Élection ou circonscription non trouvée"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function parCirconscription(int $electionId, int $circonscriptionId): JsonResponse
    {
        $election = DB::table('elections')->where('id', $electionId)->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }

        $circonscription = DB::table('circonscriptions_electorales')
            ->where('id', $circonscriptionId)
            ->first();

        if (!$circonscription) {
            return response()->json([
                'success' => false,
                'message' => 'Circonscription non trouvée',
            ], 404);
        }

        // ✅ CORRECTION: Table et colonnes correctes
        $resultats = DB::table('agregations_calculs as ag')
            ->join('candidatures as c', 'ag.candidature_id', '=', 'c.id')
            ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
            ->where('ag.election_id', $electionId)
            ->where('ag.niveau', 'circonscription')
            ->where('ag.niveau_id', $circonscriptionId)
            ->select(
                'ag.id',
                'ep.nom as entite_nom',
                'ep.sigle',
                'c.numero_liste',
                'ag.total_voix',
                'ag.total_inscrits',
                'ag.total_votants',
                'ag.pourcentage_inscrits',
                'ag.pourcentage_exprimes',
                'ag.sieges_obtenus',
                'ag.rang',
                'ag.statut'
            )
            ->orderBy('ag.rang')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'election' => $election,
                'circonscription' => $circonscription,
                'resultats' => $resultats,
            ],
            'count' => $resultats->count(),
        ]);
    }

    /**
     * Marquer les agrégations comme définitives
     * 
     * POST /api/v1/agregations/election/{electionId}/definir
     * 
     * @OA\Post(
     *     path="/agregations/election/{electionId}/definir",
     *     tags={"Agrégations"},
     *     summary="Marquer comme définitif",
     *     description="Marque toutes les agrégations d'une élection comme définitives (passage de 'provisoire' à 'definitif')",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="electionId",
     *         in="path",
     *         description="ID de l'élection",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Résultats marqués comme définitifs",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Résultats marqués comme définitifs"),
     *             @OA\Property(property="nb_agregations", type="integer", example=192, description="Nombre d'agrégations mises à jour")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Élection non trouvée"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function definir(int $electionId): JsonResponse
    {
        $election = DB::table('elections')->where('id', $electionId)->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }

        // Mettre à jour toutes les agrégations provisoires en définitif
        $updated = DB::table('agregations_calculs')
            ->where('election_id', $electionId)
            ->where('statut', 'provisoire')
            ->update([
                'statut' => 'definitif',
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Résultats marqués comme définitifs',
            'nb_agregations' => $updated,
        ]);
    }

    /**
     * Statistiques générales d'une élection
     * 
     * GET /api/v1/agregations/election/{electionId}/statistiques
     * 
     * @OA\Get(
     *     path="/agregations/election/{electionId}/statistiques",
     *     tags={"Agrégations"},
     *     summary="Statistiques d'une élection",
     *     description="Retourne les statistiques complètes d'une élection (PV, agrégations, etc.)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="electionId",
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
     *                     property="pv",
     *                     type="object",
     *                     @OA\Property(property="total_pv", type="integer", example=15500),
     *                     @OA\Property(property="pv_valides", type="integer", example=15456),
     *                     @OA\Property(property="pv_litigieux", type="integer", example=30),
     *                     @OA\Property(property="pv_brouillon", type="integer", example=14)
     *                 ),
     *                 @OA\Property(
     *                     property="agregations",
     *                     type="object",
     *                     @OA\Property(property="total_agregations", type="integer", example=192),
     *                     @OA\Property(property="nb_niveaux", type="integer", example=2),
     *                     @OA\Property(property="provisoires", type="integer", example=0),
     *                     @OA\Property(property="definitifs", type="integer", example=192)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Élection non trouvée"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function statistiques(int $electionId): JsonResponse
    {
        $election = DB::table('elections')->where('id', $electionId)->first();

        if (!$election) {
            return response()->json([
                'success' => false,
                'message' => 'Élection non trouvée',
            ], 404);
        }

        // Statistiques des PV
        $statsPv = DB::table('proces_verbaux')
            ->where('election_id', $electionId)
            ->selectRaw('
                COUNT(*) as total_pv,
                COUNT(CASE WHEN statut = \'valide\' THEN 1 END) as pv_valides,
                COUNT(CASE WHEN statut = \'litigieux\' THEN 1 END) as pv_litigieux,
                COUNT(CASE WHEN statut = \'brouillon\' THEN 1 END) as pv_brouillon
            ')
            ->first();

        // Agrégations disponibles
        $statsAgregations = DB::table('agregations_calculs')
            ->where('election_id', $electionId)
            ->selectRaw('
                COUNT(*) as total_agregations,
                COUNT(DISTINCT niveau) as nb_niveaux,
                COUNT(CASE WHEN statut = \'provisoire\' THEN 1 END) as provisoires,
                COUNT(CASE WHEN statut = \'definitif\' THEN 1 END) as definitifs
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'election' => $election,
                'pv' => $statsPv,
                'agregations' => $statsAgregations,
            ],
        ]);
    }
}