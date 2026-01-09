<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Cache, Log};

/**
 * ResultatController
 * 
 * Gestion des résultats électoraux législatifs
 * Implémente les 3 étapes du code électoral (Article 146)
 */
class ResultatController extends Controller
{
    /**
     * GET /api/v1/resultats/legislative/donnees-eligibilite
     * 
     * Retourne les données nécessaires pour l'étape 1 (filtre d'éligibilité)
     * - Voix par circonscription et par entité politique
     * - Total des suffrages par circonscription
     */
    public function donneesEligibilite(Request $request): JsonResponse
    {
        try {
            $electionId = $request->input('election_id');
            
            if (!$electionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'election_id requis',
                ], 400);
            }

            // 1. Récupérer toutes les circonscriptions électorales
            $circonscriptions = DB::table('circonscriptions_electorales as ce')
                ->join('departements as d', 'ce.departement_id', '=', 'd.id')
                ->select(
                    'ce.id',
                    'ce.code',
                    'ce.nom',
                    'ce.numero',
                    'ce.nombre_sieges_total',
                    'ce.nombre_sieges_femmes',
                    'ce.nombre_sieges_homme',
                    'd.id as departement_id',
                    'd.nom as departement_nom'
                )
                ->orderBy('ce.numero')
                ->get();

            // 2. Récupérer toutes les entités politiques candidates
            $entitesPolitiques = DB::table('candidatures as c')
                ->join('entites_politiques as ep', 'c.entite_politique_id', '=', 'ep.id')
                ->where('c.election_id', $electionId)
                ->where('c.statut', 'validee')
                ->select(
                    'ep.id',
                    'ep.nom',
                    'ep.sigle',
                    'ep.couleur',
                    'ep.code',
                    'ep.type'
                )
                ->distinct()
                ->orderBy('ep.sigle')
                ->get();

            // 3. Récupérer les voix par circonscription et par entité
            $voixParCircoEtEntite = [];
            $totalVotantsParCirco = [];

            foreach ($circonscriptions as $circo) {
                $voixParCircoEtEntite[$circo->id] = [];
                $totalSuffrages = 0;

                foreach ($entitesPolitiques as $entite) {
                    // Récupérer la candidature pour cette circo et cette entité
                    $candidature = DB::table('candidatures')
                        ->where('election_id', $electionId)
                        ->where('entite_politique_id', $entite->id)
                        ->where('circonscription_id', $circo->id)
                        ->where('statut', 'validee')
                        ->first();

                    if (!$candidature) {
                        $voixParCircoEtEntite[$circo->id][$entite->id] = 0;
                        continue;
                    }

                    // Agréger les voix depuis pv_ligne_resultats
                    // en passant par les PV qui appartiennent à cette circonscription
                    $voix = DB::table('pv_ligne_resultats as plr')
                        ->join('pv_lignes as pl', 'plr.pv_ligne_id', '=', 'pl.id')
                        ->join('proces_verbaux as pv', 'pl.proces_verbal_id', '=', 'pv.id')
                        ->join('communes as co', function($join) {
                            $join->on('pv.niveau', '=', DB::raw("'commune'"))
                                 ->on('pv.niveau_id', '=', 'co.id');
                        })
                        ->where('pv.election_id', $electionId)
                        ->where('pv.statut', 'valide')
                        ->where('plr.candidature_id', $candidature->id)
                        ->where('co.circonscription_id', $circo->id)
                        ->sum('plr.nombre_voix');

                    // Ajouter aussi les PV arrondissement et village/quartier
                    $voixArrond = DB::table('pv_ligne_resultats as plr')
                        ->join('pv_lignes as pl', 'plr.pv_ligne_id', '=', 'pl.id')
                        ->join('proces_verbaux as pv', 'pl.proces_verbal_id', '=', 'pv.id')
                        ->join('arrondissements as ar', function($join) {
                            $join->on('pv.niveau', '=', DB::raw("'arrondissement'"))
                                 ->on('pv.niveau_id', '=', 'ar.id');
                        })
                        ->join('communes as co', 'ar.commune_id', '=', 'co.id')
                        ->where('pv.election_id', $electionId)
                        ->where('pv.statut', 'valide')
                        ->where('plr.candidature_id', $candidature->id)
                        ->where('co.circonscription_id', $circo->id)
                        ->sum('plr.nombre_voix');

                    $voixVillage = DB::table('pv_ligne_resultats as plr')
                        ->join('pv_lignes as pl', 'plr.pv_ligne_id', '=', 'pl.id')
                        ->join('proces_verbaux as pv', 'pl.proces_verbal_id', '=', 'pv.id')
                        ->join('villages_quartiers as vq', function($join) {
                            $join->on('pv.niveau', '=', DB::raw("'village_quartier'"))
                                 ->on('pv.niveau_id', '=', 'vq.id');
                        })
                        ->join('arrondissements as ar', 'vq.arrondissement_id', '=', 'ar.id')
                        ->join('communes as co', 'ar.commune_id', '=', 'co.id')
                        ->where('pv.election_id', $electionId)
                        ->where('pv.statut', 'valide')
                        ->where('plr.candidature_id', $candidature->id)
                        ->where('co.circonscription_id', $circo->id)
                        ->sum('plr.nombre_voix');

                    $totalVoixEntite = (int)$voix + (int)$voixArrond + (int)$voixVillage;
                    $voixParCircoEtEntite[$circo->id][$entite->id] = $totalVoixEntite;
                    $totalSuffrages += $totalVoixEntite;
                }

                $totalVotantsParCirco[$circo->id] = $totalSuffrages;
            }

            // 4. Calculer les totaux nationaux
            $voixNationales = [];
            $totalNational = 0;

            foreach ($entitesPolitiques as $entite) {
                $total = 0;
                foreach ($circonscriptions as $circo) {
                    $total += $voixParCircoEtEntite[$circo->id][$entite->id] ?? 0;
                }
                $voixNationales[$entite->id] = $total;
                $totalNational += $total;
            }

            // 5. Récupérer les coalitions déposées (si la table existe)
            $coalitionsData = [];
            
            if (DB::getSchemaBuilder()->hasTable('coalitions')) {
                $coalitions = DB::table('coalitions')
                    ->where('election_id', $electionId)
                    ->where('statut', 'validee')
                    ->get();

                foreach ($coalitions as $coalition) {
                    $membres = DB::table('coalition_membres as cm')
                        ->join('entites_politiques as ep', 'cm.entite_politique_id', '=', 'ep.id')
                        ->where('cm.coalition_id', $coalition->id)
                        ->select('ep.id', 'ep.nom', 'ep.sigle')
                        ->get();

                    $coalitionsData[] = [
                        'id' => $coalition->id,
                        'nom' => $coalition->nom,
                        'code' => $coalition->code,
                        'membres' => $membres,
                        'membre_ids' => $membres->pluck('id')->toArray(),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'circonscriptions' => $circonscriptions,
                    'entites_politiques' => $entitesPolitiques,
                    'voix_par_circo_et_entite' => $voixParCircoEtEntite,
                    'total_votants_par_circo' => $totalVotantsParCirco,
                    'voix_nationales' => $voixNationales,
                    'total_national' => $totalNational,
                    'coalitions' => $coalitionsData,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('ResultatController@donneesEligibilite:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des données',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/v1/resultats/legislative/calculer-eligibilite
     * 
     * Calcule l'éligibilité des entités selon les règles de l'étape 1
     */
    public function calculerEligibilite(Request $request): JsonResponse
    {
        try {
            $electionId = $request->input('election_id');
            $voixParCircoEtEntite = $request->input('voix_par_circo_et_entite');
            $voixNationales = $request->input('voix_nationales');
            $totalNational = $request->input('total_national');
            $totalVotantsParCirco = $request->input('total_votants_par_circo');
            $coalitions = $request->input('coalitions', []);

            // Résultat : entite_id => status (eligible | non_eligible) + raison
            $eligibilite = [];

            // 1. Vérifier les entités sans coalition
            foreach ($voixNationales as $entiteId => $voixNat) {
                $pctNational = ($totalNational > 0) ? ($voixNat / $totalNational) * 100 : 0;

                // Vérifier si l'entité fait partie d'une coalition
                $estDansCoalition = false;
                foreach ($coalitions as $coal) {
                    if (in_array($entiteId, $coal['membre_ids'])) {
                        $estDansCoalition = true;
                        break;
                    }
                }

                if (!$estDansCoalition) {
                    // Règle : ≥ 20% dans chacune des 24 circonscriptions
                    $atteint20Partout = true;
                    $nbCircosOk = 0;

                    foreach ($voixParCircoEtEntite as $circoId => $voixParEntite) {
                        $voixEntite = $voixParEntite[$entiteId] ?? 0;
                        $totalCirco = $totalVotantsParCirco[$circoId] ?? 1;
                        $pctCirco = ($totalCirco > 0) ? ($voixEntite / $totalCirco) * 100 : 0;

                        if ($pctCirco >= 20) {
                            $nbCircosOk++;
                        } else {
                            $atteint20Partout = false;
                        }
                    }

                    $eligibilite[$entiteId] = [
                        'eligible' => $atteint20Partout,
                        'pct_national' => round($pctNational, 2),
                        'nb_circos_20_pct' => $nbCircosOk,
                        'status' => $atteint20Partout ? 'vert' : ($pctNational < 10 ? 'rouge' : 'jaune'),
                        'raison' => $atteint20Partout 
                            ? 'Éligible (≥20% dans toutes les circonscriptions)' 
                            : ($pctNational < 10 
                                ? 'Non éligible (<10% national)' 
                                : "Non éligible (seulement {$nbCircosOk}/24 circos ≥20%)"),
                    ];
                }
            }

            // 2. Vérifier les membres de coalitions
            foreach ($coalitions as $coalition) {
                $membresEligibles = [];
                $coalitionEligible = true;

                // Vérifier que chaque membre a ≥10% national
                foreach ($coalition['membre_ids'] as $membreId) {
                    $voixMembre = $voixNationales[$membreId] ?? 0;
                    $pctNational = ($totalNational > 0) ? ($voixMembre / $totalNational) * 100 : 0;

                    if ($pctNational < 10) {
                        $coalitionEligible = false;
                        $eligibilite[$membreId] = [
                            'eligible' => false,
                            'pct_national' => round($pctNational, 2),
                            'status' => 'rouge',
                            'raison' => "Non éligible (<10% national, membre coalition {$coalition['nom']})",
                        ];
                    } else {
                        $membresEligibles[] = $membreId;
                    }
                }

                if (!$coalitionEligible) {
                    continue;
                }

                // Vérifier que la coalition atteint ≥20% dans chacune des 24 circonscriptions
                $coalitionAtteint20Partout = true;
                $nbCircosOk = 0;

                foreach ($voixParCircoEtEntite as $circoId => $voixParEntite) {
                    $voixCoalition = 0;
                    foreach ($coalition['membre_ids'] as $membreId) {
                        $voixCoalition += $voixParEntite[$membreId] ?? 0;
                    }

                    $totalCirco = $totalVotantsParCirco[$circoId] ?? 1;
                    $pctCirco = ($totalCirco > 0) ? ($voixCoalition / $totalCirco) * 100 : 0;

                    if ($pctCirco >= 20) {
                        $nbCircosOk++;
                    } else {
                        $coalitionAtteint20Partout = false;
                    }
                }

                // Marquer les membres
                foreach ($membresEligibles as $membreId) {
                    $voixMembre = $voixNationales[$membreId] ?? 0;
                    $pctNational = ($totalNational > 0) ? ($voixMembre / $totalNational) * 100 : 0;

                    $eligibilite[$membreId] = [
                        'eligible' => $coalitionAtteint20Partout,
                        'pct_national' => round($pctNational, 2),
                        'nb_circos_20_pct' => $nbCircosOk,
                        'coalition' => $coalition['nom'],
                        'status' => $coalitionAtteint20Partout ? 'vert' : 'jaune',
                        'raison' => $coalitionAtteint20Partout 
                            ? "Éligible (coalition {$coalition['nom']} ≥20% partout)" 
                            : "Non éligible (coalition {$coalition['nom']} seulement {$nbCircosOk}/24 circos ≥20%)",
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'eligibilite' => $eligibilite,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('ResultatController@calculerEligibilite:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul d\'éligibilité',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/v1/resultats/legislative/repartir-sieges
     * 
     * Répartit les sièges selon les étapes 2 & 3
     */
    public function repartirSieges(Request $request): JsonResponse
    {
        try {
            $electionId = $request->input('election_id');
            $eligibilite = $request->input('eligibilite');
            $voixParCircoEtEntite = $request->input('voix_par_circo_et_entite');
            $totalVotantsParCirco = $request->input('total_votants_par_circo');
            $circonscriptions = $request->input('circonscriptions');

            // Filtrer les entités éligibles
            $entitesEligibles = array_filter($eligibilite, function($e) {
                return $e['eligible'] === true;
            });

            $entiteIdsEligibles = array_keys($entitesEligibles);

            // Résultats par circonscription et globaux
            $resultatsParCirco = [];
            $siegesTotauxParEntite = [];

            foreach ($entiteIdsEligibles as $entiteId) {
                $siegesTotauxParEntite[$entiteId] = [
                    'sieges_ordinaires' => 0,
                    'sieges_femmes' => 0,
                ];
            }

            // Pour chaque circonscription
            foreach ($circonscriptions as $circo) {
                $circoId = $circo['id'];
                $siegesTotal = $circo['nombre_sieges_total'];
                $siegesFemmes = $circo['nombre_sieges_femmes'];
                $siegesOrdinaires = $siegesTotal - $siegesFemmes;

                $totalVotants = $totalVotantsParCirco[$circoId] ?? 0;

                if ($totalVotants == 0 || $siegesOrdinaires == 0) {
                    continue;
                }

                // ÉTAPE 2 : Quotient électoral
                $quotientElectoral = floor($totalVotants / $siegesOrdinaires);

                // Attribution initiale par quotient
                $siegesAttribues = [];
                $restesVoix = [];

                foreach ($entiteIdsEligibles as $entiteId) {
                    $voix = $voixParCircoEtEntite[$circoId][$entiteId] ?? 0;
                    $sieges = floor($voix / $quotientElectoral);

                    $siegesAttribues[$entiteId] = $sieges;
                    $restesVoix[$entiteId] = $voix % $quotientElectoral;
                }

                $totalSiegesAttribues = array_sum($siegesAttribues);
                $siegesRestants = $siegesOrdinaires - $totalSiegesAttribues;

                // Attribution des sièges restants (plus forte moyenne)
                while ($siegesRestants > 0) {
                    $meilleureEntite = null;
                    $meilleureMoyenne = -1;

                    foreach ($entiteIdsEligibles as $entiteId) {
                        $voix = $voixParCircoEtEntite[$circoId][$entiteId] ?? 0;
                        $siegesDeja = $siegesAttribues[$entiteId];
                        $moyenne = $voix / ($siegesDeja + 1);

                        if ($moyenne > $meilleureMoyenne) {
                            $meilleureMoyenne = $moyenne;
                            $meilleureEntite = $entiteId;
                        }
                    }

                    if ($meilleureEntite) {
                        $siegesAttribues[$meilleureEntite]++;
                        $siegesRestants--;
                    } else {
                        break;
                    }
                }

                // ÉTAPE 3 : Siège femme (Winner Takes All)
                $maxVoix = 0;
                $gagnantFemme = null;

                foreach ($entiteIdsEligibles as $entiteId) {
                    $voix = $voixParCircoEtEntite[$circoId][$entiteId] ?? 0;
                    if ($voix > $maxVoix) {
                        $maxVoix = $voix;
                        $gagnantFemme = $entiteId;
                    }
                }

                $siegeFemme = [];
                if ($gagnantFemme) {
                    $siegeFemme[$gagnantFemme] = 1;
                }

                // Sauvegarder les résultats de la circonscription
                $resultatsParCirco[$circoId] = [
                    'quotient_electoral' => $quotientElectoral,
                    'sieges_ordinaires' => $siegesAttribues,
                    'siege_femme' => $siegeFemme,
                ];

                // Ajouter aux totaux globaux
                foreach ($entiteIdsEligibles as $entiteId) {
                    $siegesTotauxParEntite[$entiteId]['sieges_ordinaires'] += $siegesAttribues[$entiteId] ?? 0;
                    $siegesTotauxParEntite[$entiteId]['sieges_femmes'] += $siegeFemme[$entiteId] ?? 0;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'resultats_par_circo' => $resultatsParCirco,
                    'sieges_totaux_par_entite' => $siegesTotauxParEntite,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('ResultatController@repartirSieges:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la répartition des sièges',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}