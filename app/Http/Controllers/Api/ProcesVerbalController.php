<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{ProcesVerbal, PVLigne, PVLigneResultat, Election, Candidature};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Validator, Auth};
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 * name="ProcesVerbal",
 * description="Gestion des Procès-Verbaux électoraux"
 * )
 */
class ProcesVerbalController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/v1/pv",
     * operationId="getProcesVerbauxList",
     * tags={"ProcesVerbal"},
     * summary="Liste des PV avec filtres et pagination",
     * description="Récupère la liste des procès-verbaux",
     * @OA\Parameter(name="election_id", in="query", description="ID de l'élection", required=false, @OA\Schema(type="integer")),
     * @OA\Parameter(name="statut", in="query", description="Statut du PV (brouillon, valide, publie, rejete)", required=false, @OA\Schema(type="string")),
     * @OA\Parameter(name="niveau", in="query", description="Niveau de compilation", required=false, @OA\Schema(type="string", enum={"arrondissement", "village_quartier", "commune"})),
     * @OA\Parameter(name="search", in="query", description="Recherche par code ou numéro PV", required=false, @OA\Schema(type="string")),
     * @OA\Response(
     * response=200,
     * description="Succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="array", @OA\Items(type="object")),
     * @OA\Property(property="pagination", type="object")
     * )
     * ),
     * @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ProcesVerbal::with(['election', 'saisiPar']);

            // Filtrer par statut
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            // Filtrer par niveau
            if ($request->has('niveau')) {
                $query->where('niveau', $request->niveau);
            }

            // Filtrer par élection
            if ($request->has('election_id')) {
                $query->where('election_id', $request->election_id);
            }

            $pvs = $query->orderBy('created_at', 'desc')->get();

            // ✅ Formater les données avec gestion d'erreurs
            $data = $pvs->map(function ($pv) {
                try {
                    // Variables pour stocker les données géographiques
                    $entite = null;
                    $departement = null;
                    $commune = null;
                    $arrondissement = null;
                    $villageQuartier = null;
                    $posteVote = null;

                    // ✅ Charger l'entité selon le niveau en utilisant niveau_id
                    if ($pv->niveau_id) {
                        switch ($pv->niveau) {
                            case 'bureau':
                                $entite = \App\Models\PosteVote::with([
                                    'centreVote.villageQuartier.arrondissement.commune.departement'
                                ])->find($pv->niveau_id);
                                
                                if ($entite) {
                                    $posteVote = $entite->nom ?? $entite->code;
                                    $villageQuartier = $entite->centreVote?->villageQuartier?->nom;
                                    $arrondissement = $entite->centreVote?->villageQuartier?->arrondissement?->nom;
                                    $commune = $entite->centreVote?->villageQuartier?->arrondissement?->commune?->nom;
                                    $departement = $entite->centreVote?->villageQuartier?->arrondissement?->commune?->departement?->nom;
                                }
                                break;

                            case 'village_quartier':
                                $entite = \App\Models\VillageQuartier::with([
                                    'arrondissement.commune.departement'
                                ])->find($pv->niveau_id);
                                
                                if ($entite) {
                                    $villageQuartier = $entite->nom;
                                    $arrondissement = $entite->arrondissement?->nom;
                                    $commune = $entite->arrondissement?->commune?->nom;
                                    $departement = $entite->arrondissement?->commune?->departement?->nom;
                                }
                                break;

                            case 'arrondissement':
                                $entite = \App\Models\Arrondissement::with([
                                    'commune.departement'
                                ])->find($pv->niveau_id);
                                
                                if ($entite) {
                                    $arrondissement = $entite->nom;
                                    $commune = $entite->commune?->nom;
                                    $departement = $entite->commune?->departement?->nom;
                                }
                                break;

                            case 'commune':
                                $entite = \App\Models\Commune::with(['departement'])->find($pv->niveau_id);
                                
                                if ($entite) {
                                    $commune = $entite->nom;
                                    $departement = $entite->departement?->nom;
                                }
                                break;

                            case 'circonscription':
                            case 'national':
                                // Pas de localisation spécifique pour ces niveaux
                                break;
                        }
                    }

                    return [
                        'id' => $pv->id,
                        'code' => $pv->code,
                        'numero_pv' => $pv->numero_pv,
                        
                        // ✅ Election en tant qu'objet complet
                        'election' => $pv->election ? [
                            'id' => $pv->election->id,
                            'nom' => $pv->election->nom,
                            'code' => $pv->election->code,
                            'type' => $pv->election->type ?? null,
                            'date' => $pv->election->date_scrutin ?? null,
                        ] : null,
                        
                        // ✅ Données géographiques détaillées
                        'niveau' => $pv->niveau,
                        'niveau_id' => $pv->niveau_id,
                        'departement' => $departement,
                        'commune' => $commune,
                        'arrondissement' => $arrondissement,
                        'village_quartier' => $villageQuartier,
                        'poste_vote' => $posteVote,
                        
                        // Données du PV
                        'coordonnateur' => $pv->coordonnateur,
                        'nombre_inscrits' => $pv->nombre_inscrits,
                        'nombre_votants' => $pv->nombre_votants,
                        'nombre_suffrages_exprimes' => $pv->nombre_suffrages_exprimes,
                        'nombre_bulletins_nuls' => $pv->nombre_bulletins_nuls,
                        'nombre_bulletins_blancs' => $pv->nombre_bulletins_blancs ?? 0,
                        
                        // Statistiques
                        'taux_participation' => $pv->taux_participation,
                        
                        // Dates et auteur
                        'statut' => $pv->statut,
                        'saisi_par' => $pv->saisiPar ? [
                            'id' => $pv->saisiPar->id,
                            'nom' => $pv->saisiPar->nom,
                            'prenom' => $pv->saisiPar->prenom,
                        ] : null,
                        'created_at' => $pv->created_at,
                        'updated_at' => $pv->updated_at,
                    ];

                } catch (\Exception $e) {
                    // Si une erreur survient sur un PV spécifique, on log et on continue
                    \Log::warning("Erreur lors du formatage du PV {$pv->id}: {$e->getMessage()}");
                    
                    // Retourner une version minimale du PV
                    return [
                        'id' => $pv->id,
                        'code' => $pv->code,
                        'numero_pv' => $pv->numero_pv,
                        'election' => $pv->election ? [
                            'id' => $pv->election->id,
                            'nom' => $pv->election->nom,
                        ] : null,
                        'niveau' => $pv->niveau,
                        'niveau_id' => $pv->niveau_id,
                        'statut' => $pv->statut,
                        'created_at' => $pv->created_at,
                        'error' => 'Données partielles disponibles',
                    ];
                }
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur ProcesVerbalController@index:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des PV',
                'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     * path="/api/v1/pv",
     * operationId="storeProcesVerbal",
     * tags={"ProcesVerbal"},
     * summary="Créer un nouveau PV",
     * description="Enregistre un nouveau procès-verbal et ses lignes de résultats",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"election_id", "niveau", "niveau_id", "lignes"},
     * @OA\Property(property="election_id", type="integer", example=1),
     * @OA\Property(property="niveau", type="string", enum={"arrondissement", "village_quartier", "commune"}),
     * @OA\Property(property="niveau_id", type="integer", example=10),
     * @OA\Property(property="lignes", type="array", @OA\Items(
     * required={"type", "ordre", "resultats"},
     * @OA\Property(property="type", type="string", enum={"village_quartier", "poste_vote", "arrondissement", "centre_vote"}),
     * @OA\Property(property="arrondissement_id", type="integer"),
     * @OA\Property(property="village_quartier_id", type="integer"),
     * @OA\Property(property="poste_vote_id", type="integer"),
     * @OA\Property(property="ordre", type="integer", example=1),
     * @OA\Property(property="bulletins_nuls", type="integer", example=5),
     * @OA\Property(property="resultats", type="array", @OA\Items(
     * required={"candidature_id", "nombre_voix"},
     * @OA\Property(property="candidature_id", type="integer", example=1),
     * @OA\Property(property="nombre_voix", type="integer", example=150)
     * ))))
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="PV créé avec succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="PV créé avec succès"),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     * @OA\Response(response=422, description="Erreur de validation"),
     * @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        // ✅ Validation avec tous les IDs possibles
        $validator = Validator::make($request->all(), [
            'election_id' => 'required|exists:elections,id',
            'niveau' => ['required', Rule::in(['arrondissement', 'village_quartier', 'commune'])],
            'niveau_id' => 'required|integer',
            'lignes' => 'required|array|min:1',
            'lignes.*.type' => 'required|in:village_quartier,poste_vote,arrondissement,centre_vote',
            'lignes.*.arrondissement_id' => 'nullable|integer|exists:arrondissements,id',
            'lignes.*.village_quartier_id' => 'nullable|integer|exists:villages_quartiers,id',
            'lignes.*.poste_vote_id' => 'nullable|integer|exists:postes_vote,id',
            'lignes.*.centre_vote_id' => 'nullable|integer|exists:centres_vote,id',
            'lignes.*.ordre' => 'required|integer|min:1',
            'lignes.*.bulletins_nuls' => 'nullable|integer|min:0',
            'lignes.*.resultats' => 'required|array',
            'lignes.*.resultats.*.candidature_id' => 'required|exists:candidatures,id',
            'lignes.*.resultats.*.nombre_voix' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Générer le code PV
            $code = $this->genererCodePV($request->election_id, $request->niveau, $request->niveau_id);

            // Créer le PV
            $pv = ProcesVerbal::create([
                'code' => $code,
                'numero_pv' => $code,
                'election_id' => $request->election_id,
                'niveau' => $request->niveau,
                'niveau_id' => $request->niveau_id,
                'statut' => 'brouillon',
                'date_compilation' => now(),
                'saisi_par_user_id' => Auth::id(),
                'nombre_inscrits' => 0,
                'nombre_votants' => 0,
                'nombre_bulletins_nuls' => 0,
                'nombre_suffrages_exprimes' => 0,
            ]);

            // Créer les lignes
            $totalInscrits = 0;
            $totalVotants = 0;
            $totalBulletinsNuls = 0;
            $totalSuffragesExprimes = 0;

            foreach ($request->lignes as $ligneData) {
                // ✅ Créer la ligne avec tous les IDs
                $ligne = PVLigne::create([
                    'proces_verbal_id' => $pv->id,
                    'arrondissement_id' => $ligneData['arrondissement_id'] ?? null,
                    'village_quartier_id' => $ligneData['village_quartier_id'] ?? null,
                    'poste_vote_id' => $ligneData['poste_vote_id'] ?? null,
                    'centre_vote_id' => $ligneData['centre_vote_id'] ?? null,
                    'ordre' => $ligneData['ordre'],
                    'bulletins_nuls' => $ligneData['bulletins_nuls'] ?? 0,
                    'operateur_user_id' => Auth::id(),
                    'date_saisie' => now(),
                ]);

                // Créer les résultats de la ligne
                $totalVoixLigne = 0;
                foreach ($ligneData['resultats'] as $resultatData) {
                    PVLigneResultat::create([
                        'pv_ligne_id' => $ligne->id,
                        'candidature_id' => $resultatData['candidature_id'],
                        'nombre_voix' => $resultatData['nombre_voix'],
                        'operateur_user_id' => Auth::id(),
                        'date_saisie' => now(),
                    ]);

                    $totalVoixLigne += $resultatData['nombre_voix'];
                }

                // ✅ Récupérer le nombre d'inscrits via la méthode corrigée
                $inscritsLigne = $this->getInscritsForEntity($ligneData['type'], $this->getEntityId($ligneData));
                $totalInscrits += $inscritsLigne;

                // Calculer les totaux
                $nulsLigne = $ligneData['bulletins_nuls'] ?? 0;
                $totalBulletinsNuls += $nulsLigne;
                $totalSuffragesExprimes += $totalVoixLigne;
                $totalVotants += ($totalVoixLigne + $nulsLigne);
            }

            // Mettre à jour les totaux du PV
            $pv->update([
                'nombre_inscrits' => $totalInscrits,
                'nombre_votants' => $totalVotants,
                'nombre_bulletins_nuls' => $totalBulletinsNuls,
                'nombre_suffrages_exprimes' => $totalSuffragesExprimes,
            ]);

            // Calculer les résultats globaux
            $this->calculerResultatsGlobaux($pv->id);

            DB::commit();

            // Recharger le PV
            $pv->load(['lignes.resultats.candidature', 'election', 'saisiPar']);

            return response()->json([
                'success' => true,
                'message' => 'PV créé avec succès',
                'data' => $pv,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Erreur ProcesVerbalController@store:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du PV',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     * path="/api/v1/pv/{id}",
     * operationId="getProcesVerbalById",
     * tags={"ProcesVerbal"},
     * summary="Afficher un PV",
     * description="Récupère les détails d'un PV spécifique",
     * @OA\Parameter(name="id", in="path", required=true, description="ID du PV", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="Détails du PV",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     * @OA\Response(response=404, description="PV non trouvé")
     * )
     */
  public function show(string $id): JsonResponse
{
    try {
        // ✅ Cherche par ID OU par CODE (évite les "PV introuvable" si on navigue avec le code)
        $pv = ProcesVerbal::query()
            ->with([
                'election',
                'saisiPar:id,nom,prenom',
                'validePar:id,nom,prenom',

                // lignes + geo
                'lignes.villageQuartier.arrondissement.commune.departement',
                'lignes.arrondissement.commune.departement',
                'lignes.centreVote.villageQuartier.arrondissement.commune.departement',
                'lignes.posteVote.centreVote.villageQuartier.arrondissement.commune.departement',

                // résultats par ligne
                'lignes.resultats.candidature.entitePolitique',
            ])
            ->where('id', $id)
            ->orWhere('code', $id)
            ->first();

        if (!$pv) {
            return response()->json([
                'success' => false,
                'message' => 'PV non trouvé',
                'data' => null,
            ], 404);
        }

        // ✅ Lignes (format compatible avec ta page détail actuelle)
        $lignes = $pv->lignes->map(function ($ligne) {
            $localisation =
                $ligne->villageQuartier?->nom
                ?? $ligne->posteVote?->nom
                ?? $ligne->centreVote?->nom
                ?? $ligne->arrondissement?->nom
                ?? ($ligne->nom_localisation ?? 'N/A');

            $type =
                $ligne->village_quartier_id ? 'village_quartier' :
                ($ligne->poste_vote_id ? 'poste_vote' :
                ($ligne->centre_vote_id ? 'centre_vote' :
                ($ligne->arrondissement_id ? 'arrondissement' : '')));

            $totalVoix = (int) $ligne->resultats->sum('nombre_voix');
            $bulletinsNuls = (int) ($ligne->bulletins_nuls ?? 0);

            $resultats = $ligne->resultats->map(function ($r) {
                $ep = $r->candidature?->entitePolitique;

                return [
                    'id' => (int) $r->id,
                    'candidature_id' => (int) $r->candidature_id,
                    'entite_politique' => $ep?->nom ?? 'N/A',
                    'sigle' => $ep?->sigle ?? '',
                    'numero_liste' => $ep?->numero_ordre ?? null,
                    'nombre_voix' => (int) ($r->nombre_voix ?? 0),
                ];
            })->values();

            return [
                'id' => (int) $ligne->id,
                'localisation' => $localisation,
                'type' => $type,
                'ordre' => (int) ($ligne->ordre ?? 0),
                'bulletins_nuls' => $bulletinsNuls,
                'total_voix' => $totalVoix,
                'total_votants' => $totalVoix + $bulletinsNuls,
                'nombre_inscrits' => null, // optionnel si tu veux plus tard
                'resultats' => $resultats,
            ];
        })->values();

        // ✅ Résultats globaux (agrégation depuis pv_ligne_resultats = robuste)
        $globaux = PVLigneResultat::query()
            ->whereHas('ligne', function ($q) use ($pv) {
                $q->where('proces_verbal_id', $pv->id);
            })
            ->select('candidature_id', DB::raw('SUM(nombre_voix) as nombre_voix'))
            ->groupBy('candidature_id')
            ->with(['candidature.entitePolitique'])
            ->get()
            ->map(function ($row) {
                $ep = $row->candidature?->entitePolitique;

                return [
                    'id' => (int) $row->candidature_id,
                    'candidature_id' => (int) $row->candidature_id,
                    'entite_politique' => $ep?->nom ?? 'N/A',
                    'sigle' => $ep?->sigle ?? '',
                    'numero_liste' => $ep?->numero_ordre ?? null,
                    'nombre_voix' => (int) $row->nombre_voix,
                ];
            })
            ->sortByDesc('nombre_voix')
            ->values();

        // ✅ Localisation (on utilise l’accessor du modèle)
        $localisation = (string) ($pv->localisation_complete ?? '');

        return response()->json([
            'success' => true,
            'message' => 'Détails du PV récupérés',
            'data' => [
                'pv' => [
                    'id' => (int) $pv->id,
                    'code' => $pv->code,
                    'numero_pv' => $pv->numero_pv,
                    'niveau' => $pv->niveau,
                    'niveau_id' => (int) $pv->niveau_id,
                    'localisation' => $localisation,
                    'election' => $pv->election?->nom ?? '',
                    'coordonnateur' => $pv->coordonnateur,
                    'date_compilation' => $pv->date_compilation,
                    'statut' => $pv->statut,
                    'nombre_inscrits' => $pv->nombre_inscrits,
                    'nombre_votants' => $pv->nombre_votants,
                    'nombre_bulletins_nuls' => $pv->nombre_bulletins_nuls,
                    'nombre_suffrages_exprimes' => $pv->nombre_suffrages_exprimes,
                    'taux_participation' => $pv->taux_participation,
                    'est_coherent' => $pv->est_coherent,
                    'observations' => $pv->observations,
                    'saisi_par' => $pv->saisiPar ? trim($pv->saisiPar->nom . ' ' . $pv->saisiPar->prenom) : null,
                    'valide_par' => $pv->validePar ? trim($pv->validePar->nom . ' ' . $pv->validePar->prenom) : null,
                    'date_validation' => $pv->date_validation,
                    'created_at' => $pv->created_at,
                    'updated_at' => $pv->updated_at,
                ],
                'lignes' => $lignes,
                'resultats_globaux' => $globaux,
                'signatures' => [], // (tu pourras brancher plus tard si besoin)
            ],
        ]);

    } catch (\Throwable $e) {
        \Log::error('Erreur ProcesVerbalController@show', [
            'id' => $id,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération du PV',
            'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue',
        ], 500);
    }
}



    /**
     * @OA\Put(
     * path="/api/v1/pv/{id}",
     * operationId="updateProcesVerbal",
     * tags={"ProcesVerbal"},
     * summary="Modifier un PV",
     * description="Met à jour un PV existant (si non validé)",
     * @OA\Parameter(name="id", in="path", required=true, description="ID du PV", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"lignes"},
     * @OA\Property(property="lignes", type="array", @OA\Items(
     * required={"type", "ordre", "resultats"},
     * @OA\Property(property="id", type="integer", description="ID de la ligne si update"),
     * @OA\Property(property="type", type="string", enum={"village_quartier", "poste_vote", "arrondissement", "centre_vote"}),
     * @OA\Property(property="arrondissement_id", type="integer"),
     * @OA\Property(property="village_quartier_id", type="integer"),
     * @OA\Property(property="poste_vote_id", type="integer"),
     * @OA\Property(property="ordre", type="integer"),
     * @OA\Property(property="bulletins_nuls", type="integer"),
     * @OA\Property(property="resultats", type="array", @OA\Items(
     * required={"candidature_id", "nombre_voix"},
     * @OA\Property(property="candidature_id", type="integer"),
     * @OA\Property(property="nombre_voix", type="integer")
     * ))))
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="PV modifié avec succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="PV modifié avec succès"),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     * @OA\Response(response=403, description="Interdit (PV déjà validé)"),
     * @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // ✅ Validation avec tous les IDs possibles
        $validator = Validator::make($request->all(), [
            'lignes' => 'required|array|min:1',
            'lignes.*.id' => 'nullable|exists:pv_lignes,id',
            'lignes.*.type' => 'required|in:village_quartier,poste_vote,arrondissement,centre_vote',
            'lignes.*.arrondissement_id' => 'nullable|integer|exists:arrondissements,id',
            'lignes.*.village_quartier_id' => 'nullable|integer|exists:villages_quartiers,id',
            'lignes.*.poste_vote_id' => 'nullable|integer|exists:postes_vote,id',
            'lignes.*.centre_vote_id' => 'nullable|integer|exists:centres_vote,id',
            'lignes.*.ordre' => 'required|integer|min:1',
            'lignes.*.bulletins_nuls' => 'nullable|integer|min:0',
            'lignes.*.resultats' => 'required|array',
            'lignes.*.resultats.*.candidature_id' => 'required|exists:candidatures,id',
            'lignes.*.resultats.*.nombre_voix' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $pv = ProcesVerbal::findOrFail($id);

            // Vérifier que le PV est modifiable
            if (in_array($pv->statut, ['valide', 'publie'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce PV ne peut plus être modifié (statut: ' . $pv->statut . ')',
                ], 403);
            }

            // Réinitialiser les totaux
            $totalInscrits = 0;
            $totalVotants = 0;
            $totalBulletinsNuls = 0;
            $totalSuffragesExprimes = 0;

            // IDs des lignes reçues
            $lignesRecuesIds = collect($request->lignes)->pluck('id')->filter()->toArray();

            // Supprimer les lignes qui ne sont plus présentes
            PVLigne::where('proces_verbal_id', $pv->id)
                ->whereNotIn('id', $lignesRecuesIds)
                ->delete();

            // Traiter chaque ligne
            foreach ($request->lignes as $ligneData) {
                // ✅ Mettre à jour ou créer la ligne avec tous les IDs
                if (isset($ligneData['id'])) {
                    $ligne = PVLigne::findOrFail($ligneData['id']);
                    $ligne->update([
                        'ordre' => $ligneData['ordre'],
                        'bulletins_nuls' => $ligneData['bulletins_nuls'] ?? 0,
                        'operateur_user_id' => Auth::id(),
                    ]);
                } else {
                    $ligne = PVLigne::create([
                        'proces_verbal_id' => $pv->id,
                        'arrondissement_id' => $ligneData['arrondissement_id'] ?? null,
                        'village_quartier_id' => $ligneData['village_quartier_id'] ?? null,
                        'poste_vote_id' => $ligneData['poste_vote_id'] ?? null,
                        'centre_vote_id' => $ligneData['centre_vote_id'] ?? null,
                        'ordre' => $ligneData['ordre'],
                        'bulletins_nuls' => $ligneData['bulletins_nuls'] ?? 0,
                        'operateur_user_id' => Auth::id(),
                        'date_saisie' => now(),
                    ]);
                }

                // Supprimer les anciens résultats
                PVLigneResultat::where('pv_ligne_id', $ligne->id)->delete();

                // Créer les nouveaux résultats
                $totalVoixLigne = 0;
                foreach ($ligneData['resultats'] as $resultatData) {
                    PVLigneResultat::create([
                        'pv_ligne_id' => $ligne->id,
                        'candidature_id' => $resultatData['candidature_id'],
                        'nombre_voix' => $resultatData['nombre_voix'],
                        'operateur_user_id' => Auth::id(),
                        'date_saisie' => now(),
                    ]);

                    $totalVoixLigne += $resultatData['nombre_voix'];
                }

                // ✅ Calculer les totaux via la méthode corrigée
                $inscritsLigne = $this->getInscritsForEntity($ligneData['type'], $this->getEntityId($ligneData));
                $totalInscrits += $inscritsLigne;
                
                $nulsLigne = $ligneData['bulletins_nuls'] ?? 0;
                $totalBulletinsNuls += $nulsLigne;
                $totalSuffragesExprimes += $totalVoixLigne;
                $totalVotants += ($totalVoixLigne + $nulsLigne);
            }

            // Mettre à jour les totaux du PV
            $pv->update([
                'nombre_inscrits' => $totalInscrits,
                'nombre_votants' => $totalVotants,
                'nombre_bulletins_nuls' => $totalBulletinsNuls,
                'nombre_suffrages_exprimes' => $totalSuffragesExprimes,
            ]);

            // Recalculer les résultats globaux
            $this->calculerResultatsGlobaux($pv->id);

            DB::commit();

            // Recharger le PV
            $pv->load(['lignes.resultats.candidature', 'election', 'saisiPar']);

            return response()->json([
                'success' => true,
                'message' => 'PV modifié avec succès',
                'data' => $pv,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Erreur ProcesVerbalController@update:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification du PV',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     * path="/api/v1/pv/{id}",
     * operationId="deleteProcesVerbal",
     * tags={"ProcesVerbal"},
     * summary="Supprimer un PV",
     * description="Supprime un PV (si non validé)",
     * @OA\Parameter(name="id", in="path", required=true, description="ID du PV", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="PV supprimé avec succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="PV supprimé avec succès")
     * )
     * ),
     * @OA\Response(response=403, description="Interdit (PV déjà validé)"),
     * @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $pv = ProcesVerbal::findOrFail($id);

            if ($pv->statut === 'valide') {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer un PV validé',
                ], 403);
            }

            $pv->delete();

            return response()->json([
                'success' => true,
                'message' => 'PV supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     * path="/api/v1/pv/{id}/valider",
     * operationId="validerProcesVerbal",
     * tags={"ProcesVerbal"},
     * summary="Valider un PV",
     * description="Valide un PV si les données sont cohérentes",
     * @OA\Parameter(name="id", in="path", required=true, description="ID du PV", @OA\Schema(type="integer")),
     * @OA\Response(
     * response=200,
     * description="PV validé avec succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="PV validé avec succès")
     * )
     * ),
     * @OA\Response(response=422, description="PV incohérent"),
     * @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function valider(int $id): JsonResponse
    {
        try {
            $pv = ProcesVerbal::findOrFail($id);

            // Vérifier la cohérence
            if (!$pv->est_coherent) {
                $erreurs = $pv->verifierCoherence();
                return response()->json([
                    'success' => false,
                    'message' => 'Le PV contient des incohérences',
                    'erreurs' => $erreurs,
                ], 422);
            }

            $pv->update([
                'statut' => 'valide',
                'date_validation' => now(),
                'valide_par_user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PV validé avec succès',
                'data' => $pv->fresh(['validePar']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la validation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     * path="/api/v1/pv/{id}/rejeter",
     * operationId="rejeterProcesVerbal",
     * tags={"ProcesVerbal"},
     * summary="Rejeter un PV",
     * description="Rejeter un PV avec un motif",
     * @OA\Parameter(name="id", in="path", required=true, description="ID du PV", @OA\Schema(type="integer")),
     * @OA\RequestBody(required=true, @OA\JsonContent(required={"motif"}, @OA\Property(property="motif", type="string", example="Incohérence des chiffres"))),
     * @OA\Response(
     * response=200,
     * description="PV rejeté avec succès",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="PV rejeté avec succès")
     * )
     * ),
     * @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function rejeter(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'motif' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $pv = ProcesVerbal::findOrFail($id);

            $pv->update([
                'statut' => 'rejete',
                'observations' => $request->motif,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PV rejeté avec succès',
                'data' => $pv,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rejet',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    private function genererCodePV(int $electionId, string $niveau, int $niveauId): string
    {
        $election = Election::findOrFail($electionId);
        $typeCode = strtoupper(substr($election->typeElection->code ?? 'GEN', 0, 3));
        $annee = date('Y');
        
        $geoCodes = $this->getGeographicCodes($niveau, $niveauId);
        $timestamp = now()->format('YmdHis');
        
        return "PV-{$typeCode}{$annee}-{$geoCodes}-{$timestamp}";
    }

    private function getGeographicCodes(string $niveau, int $niveauId): string
    {
        switch ($niveau) {
            case 'arrondissement':
                $arr = DB::table('arrondissements as a')
                    ->join('communes as c', 'a.commune_id', '=', 'c.id')
                    ->join('departements as d', 'c.departement_id', '=', 'd.id')
                    ->where('a.id', $niveauId)
                    ->select('d.code as dep', 'c.code as com', 'a.code as arr')
                    ->first();
                return $arr ? "{$arr->dep}-{$arr->com}-{$arr->arr}" : "UNK";

            case 'village_quartier':
                $vq = DB::table('villages_quartiers as v')
                    ->join('arrondissements as a', 'v.arrondissement_id', '=', 'a.id')
                    ->join('communes as c', 'a.commune_id', '=', 'c.id')
                    ->join('departements as d', 'c.departement_id', '=', 'd.id')
                    ->where('v.id', $niveauId)
                    ->select('d.code as dep', 'c.code as com', 'v.code as vq')
                    ->first();
                return $vq ? "{$vq->dep}-{$vq->com}-{$vq->vq}" : "UNK";

            case 'commune':
                $com = DB::table('communes as c')
                    ->join('departements as d', 'c.departement_id', '=', 'd.id')
                    ->where('c.id', $niveauId)
                    ->select('d.code as dep', 'c.code as com')
                    ->first();
                return $com ? "{$com->dep}-{$com->com}" : "UNK";

            default:
                return "UNK";
        }
    }

    /**
     * ✅ Extraire l'ID de l'entité selon le type
     */
    private function getEntityId(array $ligneData): int
    {
        if (isset($ligneData['arrondissement_id'])) {
            return $ligneData['arrondissement_id'];
        }
        if (isset($ligneData['village_quartier_id'])) {
            return $ligneData['village_quartier_id'];
        }
        if (isset($ligneData['poste_vote_id'])) {
            return $ligneData['poste_vote_id'];
        }
        if (isset($ligneData['centre_vote_id'])) {
            return $ligneData['centre_vote_id'];
        }

        throw new \Exception("Aucun ID d'entité trouvé pour le type {$ligneData['type']}");
    }

    /**
     * Obtenir le nombre d'inscrits pour une entité géographique 
     * en calculant la somme des inscrits dans la table postes_vote
     */
    private function getInscritsForEntity(string $type, int $id): int
    {
        switch ($type) {
            case 'village_quartier':
                // ✅ Calculer depuis postes_vote
                return DB::table('postes_vote')
                    ->where('village_quartier_id', $id)
                    ->sum('electeurs_inscrits') ?? 0;

            case 'poste_vote':
                // Direct depuis le poste
                return DB::table('postes_vote')
                    ->where('id', $id)
                    ->value('electeurs_inscrits') ?? 0;

            case 'arrondissement':
                // ✅ Calculer depuis postes_vote via villages_quartiers
                return DB::table('postes_vote as pv')
                    ->join('villages_quartiers as vq', 'pv.village_quartier_id', '=', 'vq.id')
                    ->where('vq.arrondissement_id', $id)
                    ->sum('pv.electeurs_inscrits') ?? 0;

            case 'centre_vote':
                // Somme des postes du centre
                return DB::table('postes_vote')
                    ->where('centre_vote_id', $id)
                    ->sum('electeurs_inscrits') ?? 0;

            case 'commune':
                // ✅ Calculer depuis postes_vote via villages_quartiers via arrondissements
                return DB::table('postes_vote as pv')
                    ->join('villages_quartiers as vq', 'pv.village_quartier_id', '=', 'vq.id')
                    ->join('arrondissements as a', 'vq.arrondissement_id', '=', 'a.id')
                    ->where('a.commune_id', $id)
                    ->sum('pv.electeurs_inscrits') ?? 0;

            default:
                return 0;
        }
    }

    private function calculerResultatsGlobaux(int $pvId): void
    {
        DB::table('resultats')->where('proces_verbal_id', $pvId)->delete();

        $resultats = DB::table('pv_ligne_resultats as r')
            ->join('pv_lignes as l', 'r.pv_ligne_id', '=', 'l.id')
            ->where('l.proces_verbal_id', $pvId)
            ->select('r.candidature_id', DB::raw('SUM(r.nombre_voix) as total_voix'))
            ->groupBy('r.candidature_id')
            ->get();

        foreach ($resultats as $resultat) {
            DB::table('resultats')->insert([
                'proces_verbal_id' => $pvId,
                'candidature_id' => $resultat->candidature_id,
                'nombre_voix' => $resultat->total_voix,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function getLocalisationComplete(string $niveau, int $niveauId): string
    {
        switch ($niveau) {
            case 'arrondissement':
                $data = DB::table('arrondissements as a')
                    ->join('communes as c', 'a.commune_id', '=', 'c.id')
                    ->join('departements as d', 'c.departement_id', '=', 'd.id')
                    ->where('a.id', $niveauId)
                    ->select('d.nom as departement', 'c.nom as commune', 'a.nom as arrondissement')
                    ->first();
                return $data ? "{$data->departement} → {$data->commune} → {$data->arrondissement}" : "";

            case 'village_quartier':
                $data = DB::table('villages_quartiers as v')
                    ->join('arrondissements as a', 'v.arrondissement_id', '=', 'a.id')
                    ->join('communes as c', 'a.commune_id', '=', 'c.id')
                    ->join('departements as d', 'c.departement_id', '=', 'd.id')
                    ->where('v.id', $niveauId)
                    ->select('d.nom as departement', 'c.nom as commune', 'a.nom as arrondissement', 'v.nom as village_quartier')
                    ->first();
                return $data ? "{$data->departement} → {$data->commune} → {$data->arrondissement} → {$data->village_quartier}" : "";

            case 'commune':
                $data = DB::table('communes as c')
                    ->join('departements as d', 'c.departement_id', '=', 'd.id')
                    ->where('c.id', $niveauId)
                    ->select('d.nom as departement', 'c.nom as commune')
                    ->first();
                return $data ? "{$data->departement} → {$data->commune}" : "";

            default:
                return "";
        }
    }

    private function getLocalisationPrincipale(ProcesVerbal $pv): array
{
    $niveau = $pv->niveau;
    $id = (int) $pv->niveau_id;

    $loc = [
        'departement' => null,
        'commune' => null,
        'arrondissement' => null,
        'village_quartier' => null,
        'centre_vote' => null,
        'poste_vote' => null,
        'texte' => '',
    ];

    if ($id <= 0) return $loc;

    switch ($niveau) {
        case 'commune':
            $d = DB::table('communes as c')
                ->join('departements as d', 'c.departement_id', '=', 'd.id')
                ->where('c.id', $id)
                ->select('d.nom as departement', 'c.nom as commune')
                ->first();
            if ($d) {
                $loc['departement'] = $d->departement;
                $loc['commune'] = $d->commune;
            }
            break;

        case 'arrondissement':
            $d = DB::table('arrondissements as a')
                ->join('communes as c', 'a.commune_id', '=', 'c.id')
                ->join('departements as d', 'c.departement_id', '=', 'd.id')
                ->where('a.id', $id)
                ->select('d.nom as departement', 'c.nom as commune', 'a.nom as arrondissement')
                ->first();
            if ($d) {
                $loc['departement'] = $d->departement;
                $loc['commune'] = $d->commune;
                $loc['arrondissement'] = $d->arrondissement;
            }
            break;

        case 'village_quartier':
            $d = DB::table('villages_quartiers as v')
                ->join('arrondissements as a', 'v.arrondissement_id', '=', 'a.id')
                ->join('communes as c', 'a.commune_id', '=', 'c.id')
                ->join('departements as d', 'c.departement_id', '=', 'd.id')
                ->where('v.id', $id)
                ->select('d.nom as departement', 'c.nom as commune', 'a.nom as arrondissement', 'v.nom as village_quartier')
                ->first();
            if ($d) {
                $loc['departement'] = $d->departement;
                $loc['commune'] = $d->commune;
                $loc['arrondissement'] = $d->arrondissement;
                $loc['village_quartier'] = $d->village_quartier;
            }
            break;

        case 'bureau': // niveau PV "bureau" => poste de vote
            $d = DB::table('postes_vote as p')
                ->leftJoin('centres_vote as cv', 'p.centre_vote_id', '=', 'cv.id')
                ->leftJoin('villages_quartiers as v', 'cv.village_quartier_id', '=', 'v.id')
                ->leftJoin('arrondissements as a', 'v.arrondissement_id', '=', 'a.id')
                ->leftJoin('communes as c', 'a.commune_id', '=', 'c.id')
                ->leftJoin('departements as d', 'c.departement_id', '=', 'd.id')
                ->where('p.id', $id)
                ->select(
                    'd.nom as departement',
                    'c.nom as commune',
                    'a.nom as arrondissement',
                    'v.nom as village_quartier',
                    'cv.nom as centre_vote',
                    DB::raw("COALESCE(p.nom, p.code) as poste_vote")
                )
                ->first();
            if ($d) {
                $loc['departement'] = $d->departement;
                $loc['commune'] = $d->commune;
                $loc['arrondissement'] = $d->arrondissement;
                $loc['village_quartier'] = $d->village_quartier;
                $loc['centre_vote'] = $d->centre_vote;
                $loc['poste_vote'] = $d->poste_vote;
            }
            break;
    }

    $parts = array_filter([
        $loc['departement'],
        $loc['commune'],
        $loc['arrondissement'],
        $loc['village_quartier'],
        $loc['centre_vote'],
        $loc['poste_vote'],
    ]);

    $loc['texte'] = implode(' → ', $parts);

    return $loc;
}

}