<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProcesVerbal;
use App\Models\Arrondissement;
use App\Models\VillageQuartier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PVValidationController extends Controller
{
    /**
     * Vérifier si un PV existe déjà pour une localisation donnée
     */
    public function checkExistant(Request $request)
    {
        $request->validate([
            'niveau' => 'required|in:arrondissement,village_quartier',
            'niveau_id' => 'required|integer',
            'election_id' => 'required|integer',
        ]);

        $pv = ProcesVerbal::where('election_id', $request->election_id)
            ->where('niveau', $request->niveau)
            ->where('niveau_id', $request->niveau_id)
            ->first();

        if ($pv) {
            return response()->json([
                'success' => true,
                'data' => [
                    'existe' => true,
                    'pv' => [
                        'id' => $pv->id,
                        'code' => $pv->code,
                    ],
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'existe' => false,
            ],
        ]);
    }

    /**
     * Calculer le nombre total d'inscrits pour un arrondissement
     */
    public function getInscritsArrondissement(int $arrondissementId)
    {
        try {
            // Récupérer tous les villages de l'arrondissement
            $villages = VillageQuartier::where('arrondissement_id', $arrondissementId)->pluck('id');

            // Somme des électeurs inscrits de tous les postes des villages
            $totalInscrits = DB::table('postes_vote')
                ->whereIn('village_quartier_id', $villages)
                ->sum('electeurs_inscrits');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_inscrits' => $totalInscrits ?? 0,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des inscrits',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculer le nombre total d'inscrits pour un village/quartier
     */
    public function getInscritsVillageQuartier(int $villageQuartierId)
    {
        try {
            // Somme des électeurs inscrits de tous les postes du village/quartier
            $totalInscrits = DB::table('postes_vote')
                ->where('village_quartier_id', $villageQuartierId)
                ->sum('electeurs_inscrits');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_inscrits' => $totalInscrits ?? 0,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des inscrits',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}