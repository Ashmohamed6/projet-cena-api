<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PVValidationController extends Controller
{
    // Récupérer les inscrits par Arrondissement
    public function getInscritsArrondissement($id)
    {
        // On récupère la somme des inscrits de tous les postes de vote liés à cet arrondissement
        // via les villages/quartiers
        $inscrits = DB::table('postes_vote as pv')
            ->join('villages_quartiers as vq', 'pv.village_quartier_id', '=', 'vq.id')
            ->where('vq.arrondissement_id', $id)
            ->sum('pv.electeurs_inscrits');

        return response()->json([
            'success' => true,
            'data' => ['nombre_inscrits' => (int) $inscrits]
        ]);
    }

    // Récupérer les inscrits par Village/Quartier
    public function getInscritsVillageQuartier($id)
    {
        $inscrits = DB::table('postes_vote')
            ->where('village_quartier_id', $id)
            ->sum('electeurs_inscrits');

        return response()->json([
            'success' => true,
            'data' => ['nombre_inscrits' => (int) $inscrits]
        ]);
    }

    // Récupérer les inscrits par Commune
    public function getInscritsCommune($id)
    {
        $inscrits = DB::table('postes_vote as pv')
            ->join('villages_quartiers as vq', 'pv.village_quartier_id', '=', 'vq.id')
            ->join('arrondissements as a', 'vq.arrondissement_id', '=', 'a.id')
            ->where('a.commune_id', $id)
            ->sum('pv.electeurs_inscrits');

        return response()->json([
            'success' => true,
            'data' => ['nombre_inscrits' => (int) $inscrits]
        ]);
    }

    // Récupérer les inscrits par Centre de vote
    public function getInscritsCentreVote($id)
    {
        $inscrits = DB::table('postes_vote')
            ->where('centre_vote_id', $id)
            ->sum('electeurs_inscrits');

        return response()->json([
            'success' => true,
            'data' => ['nombre_inscrits' => (int) $inscrits]
        ]);
    }

    // Vérifier si un PV existe déjà
    public function checkExistant(Request $request)
    {
        $exists = DB::table('proces_verbaux')
            ->where('election_id', $request->election_id)
            ->where('niveau', $request->niveau)
            ->where('niveau_id', $request->niveau_id)
            ->exists();

        return response()->json([
            'success' => true,
            'exists' => $exists
        ]);
    }
}