<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\{DB, Storage, Log};
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PVExportController
 * 
 * Gestion de l'export PDF des procès-verbaux
 */
class PVExportController extends Controller
{
    /**
     * GET /api/v1/pv/{id}/export-pdf
     * 
     * Génère et télécharge le PDF d'un PV
     */
    public function exportPDF(Request $request, int $pvId)
    {
        try {
            // 1. Récupérer le PV avec toutes ses données
            $pv = DB::table('proces_verbaux as pv')
                ->join('elections as e', 'pv.election_id', '=', 'e.id')
                ->leftJoin('users as u', 'pv.user_id', '=', 'u.id')
                ->where('pv.id', $pvId)
                ->select(
                    'pv.*',
                    'e.nom as election_nom',
                    'e.date_scrutin',
                    'u.nom as user_nom',
                    'u.prenom as user_prenom'
                )
                ->first();

            if (!$pv) {
                return response()->json([
                    'success' => false,
                    'message' => 'PV introuvable',
                ], 404);
            }

            // 2. Récupérer la localisation selon le niveau
            $localisation = $this->getLocalisation($pv->niveau, $pv->niveau_id);

            // 3. Récupérer les lignes du PV
            $lignes = DB::table('pv_lignes as pl')
                ->where('pl.proces_verbal_id', $pvId)
                ->orderBy('pl.ordre')
                ->get();

            $lignesAvecResultats = [];
            foreach ($lignes as $ligne) {
                // Récupérer les résultats de la ligne
                $resultats = DB::table('pv_ligne_resultats as plr')
                    ->join('candidatures as c', 'plr.candidature_id', '=', 'c.id')
                    ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
                    ->where('plr.pv_ligne_id', $ligne->id)
                    ->select(
                        'plr.*',
                        'ep.nom as entite_nom',
                        'ep.sigle',
                        'c.numero_liste'
                    )
                    ->orderBy('plr.nombre_voix', 'desc')
                    ->get();

                // Localisation de la ligne
                $ligneLocalisation = $this->getLigneLocalisation($ligne);

                $lignesAvecResultats[] = [
                    'id' => $ligne->id,
                    'localisation' => $ligneLocalisation,
                    'ordre' => $ligne->ordre,
                    'nombre_inscrits' => $ligne->nombre_inscrits,
                    'bulletins_nuls' => $ligne->bulletins_nuls,
                    'resultats' => $resultats,
                ];
            }

            // 4. Récupérer les résultats globaux
            $resultatsGlobaux = [];
            $entitesIds = DB::table('pv_ligne_resultats as plr')
                ->join('pv_lignes as pl', 'plr.pv_ligne_id', '=', 'pl.id')
                ->where('pl.proces_verbal_id', $pvId)
                ->distinct()
                ->pluck('plr.candidature_id');

            foreach ($entitesIds as $candidatureId) {
                $total = DB::table('pv_ligne_resultats as plr')
                    ->join('pv_lignes as pl', 'plr.pv_ligne_id', '=', 'pl.id')
                    ->where('pl.proces_verbal_id', $pvId)
                    ->where('plr.candidature_id', $candidatureId)
                    ->sum('plr.nombre_voix');

                $candidature = DB::table('candidatures as c')
                    ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
                    ->where('c.id', $candidatureId)
                    ->select('ep.nom', 'ep.sigle', 'c.numero_liste')
                    ->first();

                if ($candidature) {
                    $resultatsGlobaux[] = [
                        'entite_nom' => $candidature->nom,
                        'sigle' => $candidature->sigle,
                        'numero_liste' => $candidature->numero_liste,
                        'total_voix' => $total,
                    ];
                }
            }

            // Trier par voix décroissantes
            usort($resultatsGlobaux, function($a, $b) {
                return $b['total_voix'] <=> $a['total_voix'];
            });

            // 5. Récupérer les signatures
            $signatures = DB::table('signatures_pv as sp')
                ->leftJoin('entites_politiques as ep', 'sp.parti_politique_id', '=', 'ep.id')
                ->where('sp.proces_verbal_id', $pvId)
                ->select(
                    'sp.*',
                    'ep.sigle as parti_sigle',
                    'ep.nom as parti_nom'
                )
                ->orderBy('sp.ordre')
                ->get();

            // 6. Préparer les données pour la vue
            $data = [
                'pv' => $pv,
                'localisation' => $localisation,
                'lignes' => $lignesAvecResultats,
                'resultats_globaux' => $resultatsGlobaux,
                'signatures' => $signatures,
                'date_generation' => now()->format('d/m/Y à H:i'),
            ];

            // 7. Générer le PDF
            $pdf = Pdf::loadView('pdf.pv-detail', $data);
            
            // Configuration du PDF
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOption('margin-top', 10);
            $pdf->setOption('margin-right', 10);
            $pdf->setOption('margin-bottom', 10);
            $pdf->setOption('margin-left', 10);

            // Nom du fichier
            $filename = 'PV_' . $pv->code . '_' . now()->format('Y-m-d') . '.pdf';

            // 8. Retourner le PDF pour téléchargement
            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('PVExportController@exportPDF:', [
                'pv_id' => $pvId,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Récupère la localisation selon le niveau
     */
    private function getLocalisation(string $niveau, int $niveauId): string
    {
        switch ($niveau) {
            case 'commune':
                $commune = DB::table('communes')->where('id', $niveauId)->first();
                return $commune ? $commune->nom : 'N/A';

            case 'arrondissement':
                $arrond = DB::table('arrondissements as a')
                    ->join('communes as c', 'a.commune_id', '=', 'c.id')
                    ->where('a.id', $niveauId)
                    ->select('a.nom', 'c.nom as commune_nom')
                    ->first();
                return $arrond ? "{$arrond->nom} ({$arrond->commune_nom})" : 'N/A';

            case 'village_quartier':
                $village = DB::table('villages_quartiers as vq')
                    ->join('arrondissements as a', 'vq.arrondissement_id', '=', 'a.id')
                    ->join('communes as c', 'a.commune_id', '=', 'c.id')
                    ->where('vq.id', $niveauId)
                    ->select('vq.nom', 'a.nom as arrond_nom', 'c.nom as commune_nom')
                    ->first();
                return $village ? "{$village->nom} ({$village->arrond_nom}, {$village->commune_nom})" : 'N/A';

            default:
                return 'N/A';
        }
    }

    /**
     * Récupère la localisation d'une ligne
     */
    private function getLigneLocalisation($ligne): string
    {
        if ($ligne->centre_vote_id) {
            $centre = DB::table('centres_vote')->where('id', $ligne->centre_vote_id)->first();
            return $centre ? $centre->nom : 'Centre inconnu';
        }

        if ($ligne->village_quartier_id) {
            $village = DB::table('villages_quartiers')->where('id', $ligne->village_quartier_id)->first();
            return $village ? $village->nom : 'Village/Quartier inconnu';
        }

        if ($ligne->poste_vote_id) {
            $poste = DB::table('postes_vote')->where('id', $ligne->poste_vote_id)->first();
            return $poste ? $poste->nom : 'Poste inconnu';
        }

        return 'Localisation inconnue';
    }
}
