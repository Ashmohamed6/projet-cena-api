<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProcesVerbal;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * @OA\Tag(
 * name="Exports",
 * description="Génération de documents et rapports (PDF)"
 * )
 */
class ExportController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/v1/pv/{id}/export/pdf",
     * operationId="exportPvPdf",
     * tags={"Exports"},
     * summary="Exporter un PV en PDF",
     * description="Génère et télécharge le PV au format PDF avec le template approprié (Arrondissement, Quartier, etc.)",
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="ID du Procès-Verbal",
     * @OA\Schema(type="integer")
     * ),
     * @OA\Response(
     * response=200,
     * description="Fichier PDF téléchargé avec succès",
     * @OA\MediaType(
     * mediaType="application/pdf",
     * @OA\Schema(type="string", format="binary")
     * )
     * ),
     * @OA\Response(response=404, description="PV non trouvé"),
     * @OA\Response(response=500, description="Erreur lors de la génération")
     * )
     */
    public function exportPdf(int $id)
    {
        try {
            // 1. Charger le PV avec toutes les relations
            $pv = ProcesVerbal::with([
                'election',
                'lignes' => function ($query) {
                    $query->orderBy('ordre');
                },
                'lignes.villageQuartier',
                'lignes.posteVote.centreVote', // ✅ IMPORTANT pour récupérer le nom du centre
                'lignes.resultats.candidature.entitePolitique',
                'resultats.candidature.entitePolitique',
                'signatures.partiPolitique',
            ])->findOrFail($id);

            // 2. Charger l'entité géographique
            $localisation = $this->chargerLocalisation($pv);

            // 3. Déterminer le template selon le niveau
            $template = $this->getTemplateName($pv->niveau);

            // 4. Charger les ENTITÉS POLITIQUES distinctes
            $entites = $this->getEntitesPolitiques($pv);

            // 5. Préparer les données pour le template
            $data = [
                'pv' => $pv,
                'localisation' => $localisation,
                'lignes' => $this->formatterLignes($pv),
                'resultatsGlobaux' => $this->formatterResultatsGlobaux($pv),
                'signatures' => $pv->signatures->sortBy('ordre'),
                'entites' => $entites,
                'coordonnateur' => '', // Champ vide pour signature manuelle si besoin
            ];

            // 6. Générer le PDF avec les marges "spécimen" (15mm)
            $pdf = Pdf::loadView("exports.pv.{$template}", $data)
                ->setPaper('a4', 'landscape')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true)
                ->setOption('margin-top', 15)    // 15mm
                ->setOption('margin-right', 15)  // 15mm
                ->setOption('margin-bottom', 15) // 15mm
                ->setOption('margin-left', 15);  // 15mm

            // 7. Nom du fichier
            $filename = $this->generateFilename($pv);

            // 8. Retourner le PDF
            return $pdf->download($filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Charger les informations de localisation
     */
    private function chargerLocalisation(ProcesVerbal $pv): array
    {
        $data = [
            'departement' => null,
            'commune' => null,
            'arrondissement' => null,
            'village_quartier' => null,
            'zone' => null,
        ];

        if (!$pv->niveau_id) {
            return $data;
        }

        switch ($pv->niveau) {
            case 'village_quartier':
                $entite = \App\Models\VillageQuartier::with([
                    'arrondissement.commune.departement'
                ])->find($pv->niveau_id);

                if ($entite) {
                    $data['village_quartier'] = $entite->nom;
                    $data['arrondissement'] = $entite->arrondissement?->nom;
                    $data['commune'] = $entite->arrondissement?->commune?->nom;
                    $data['departement'] = $entite->arrondissement?->commune?->departement?->nom;
                }
                break;

            case 'arrondissement':
                $entite = \App\Models\Arrondissement::with([
                    'commune.departement'
                ])->find($pv->niveau_id);

                if ($entite) {
                    $data['arrondissement'] = $entite->nom;
                    $data['zone'] = $entite->nom;
                    $data['commune'] = $entite->commune?->nom;
                    $data['departement'] = $entite->commune?->departement?->nom;
                }
                break;

            case 'commune':
                $entite = \App\Models\Commune::with(['departement'])->find($pv->niveau_id);

                if ($entite) {
                    $data['commune'] = $entite->nom;
                    $data['departement'] = $entite->departement?->nom;
                }
                break;
        }

        return $data;
    }

    /**
     * Formater les lignes en groupant par ENTITÉ POLITIQUE
     */
    private function formatterLignes(ProcesVerbal $pv): array
    {
        $lignesFormatees = [];

        foreach ($pv->lignes as $ligne) {
            $ligneData = [
                'id' => $ligne->id,
                'localisation' => $ligne->nom_localisation,
                'type' => $ligne->type,
                'ordre' => $ligne->ordre,
                'bulletins_nuls' => $ligne->bulletins_nuls ?? 0,
                'resultats' => [],
            ];

            // Récupérer le nom du centre de vote pour les postes
            if ($ligne->type === 'poste_vote' && $ligne->posteVote && $ligne->posteVote->centreVote) {
                $ligneData['centre_vote_nom'] = $ligne->posteVote->centreVote->nom;
            }

            // Grouper par entité politique
            foreach ($ligne->resultats as $resultat) {
                $entiteId = $resultat->candidature->entite_politique_id ?? null;
                
                if ($entiteId) {
                    if (isset($ligneData['resultats'][$entiteId])) {
                        $ligneData['resultats'][$entiteId]['nombre_voix'] += $resultat->nombre_voix ?? 0;
                    } else {
                        $ligneData['resultats'][$entiteId] = [
                            'entite_id' => $entiteId,
                            'entite_politique' => $resultat->candidature->entitePolitique->nom ?? '',
                            'sigle' => $resultat->candidature->entitePolitique->sigle ?? '',
                            'nombre_voix' => $resultat->nombre_voix ?? 0,
                        ];
                    }
                }
            }

            $lignesFormatees[] = $ligneData;
        }

        return $lignesFormatees;
    }

    /**
     * Formater les résultats globaux
     */
    private function formatterResultatsGlobaux(ProcesVerbal $pv): array
    {
        $grouped = [];
        
        foreach ($pv->resultats as $resultat) {
            $entiteId = $resultat->candidature->entite_politique_id ?? null;
            
            if ($entiteId) {
                if (isset($grouped[$entiteId])) {
                    $grouped[$entiteId]['nombre_voix'] += $resultat->nombre_voix ?? 0;
                } else {
                    $grouped[$entiteId] = [
                        'entite_id' => $entiteId,
                        'entite_politique' => $resultat->candidature->entitePolitique->nom ?? '',
                        'sigle' => $resultat->candidature->entitePolitique->sigle ?? '',
                        'nombre_voix' => $resultat->nombre_voix ?? 0,
                    ];
                }
            }
        }

        return array_values($grouped);
    }

    /**
     * Récupérer les entités politiques distinctes avec ordre personnalisé
     */
    private function getEntitesPolitiques(ProcesVerbal $pv): array
    {
        $candidatures = DB::table('candidatures as c')
            ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
            ->where('c.election_id', $pv->election_id)
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
        
        $codeElection = $pv->election->code ?? '';
        $ordrePersonnalise = null;
        
        if (strpos($codeElection, 'LEG') !== false) {
            $ordrePersonnalise = ['FCBE', 'LD', 'BR', 'MOELE-BENIN', 'UP'];
        } elseif (strpos($codeElection, 'COM') !== false) {
            $ordrePersonnalise = ['FCBE', 'UP', 'BR'];
        } else {
            usort($entitesUniques, function($a, $b) {
                return $a['numero_liste'] <=> $b['numero_liste'];
            });
            return $entitesUniques;
        }
        
        usort($entitesUniques, function($a, $b) use ($ordrePersonnalise) {
            $posA = array_search($a['sigle'], $ordrePersonnalise);
            $posB = array_search($b['sigle'], $ordrePersonnalise);
            
            if ($posA === false) $posA = 999;
            if ($posB === false) $posB = 999;
            
            return $posA <=> $posB;
        });
        
        return $entitesUniques;
    }

    /**
     * Déterminer le nom du template selon le niveau
     */
    private function getTemplateName(string $niveau): string
    {
        switch ($niveau) {
            case 'village_quartier': return 'village_quartier';
            case 'arrondissement': return 'arrondissement';
            case 'commune': return 'commune';
            case 'national': return 'national';
            default: return 'village_quartier';
        }
    }

    /**
     * Générer le nom du fichier
     */
    private function generateFilename(ProcesVerbal $pv): string
    {
        $niveau = strtoupper($pv->niveau);
        $code = str_replace(['/', ' '], ['_', '_'], $pv->code);
        $date = now()->format('Ymd_His');

        return "PV_{$niveau}_{$code}_{$date}.pdf";
    }
}