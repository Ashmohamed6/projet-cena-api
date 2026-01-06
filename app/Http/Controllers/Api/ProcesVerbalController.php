<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{ProcesVerbal, PVLigne, PVLigneResultat, SignaturePV};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Validation\ValidationException;


/**
 * ProcesVerbalController
 * 
 * Gestion des procès-verbaux avec système de niveaux géographiques
 * et support des lignes détaillées (villages/postes)
 */
class ProcesVerbalController extends Controller
{
    /**
     * Liste des PV
     * 
     * GET /api/v1/pv
     * 
     * @OA\Get(
     *     path="/pv",
     *     tags={"Procès-Verbaux"},
     *     summary="Liste des procès-verbaux",
     *     description="Retourne la liste des PV avec possibilité de filtrage par statut, niveau et élection",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="statut",
     *         in="query",
     *         description="Filtrer par statut",
     *         required=false,
     *         @OA\Schema(type="string", enum={"brouillon", "en_verification", "valide", "litigieux", "annule"}, example="valide")
     *     ),
     *     @OA\Parameter(
     *         name="niveau",
     *         in="query",
     *         description="Filtrer par niveau géographique",
     *         required=false,
     *         @OA\Schema(type="string", enum={"bureau", "arrondissement", "commune", "village_quartier", "circonscription", "national"}, example="bureau")
     *     ),
     *     @OA\Parameter(
     *         name="election_id",
     *         in="query",
     *         description="Filtrer par élection",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des procès-verbaux",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="PV-LEG2026-BV001"),
     *                     @OA\Property(property="statut", type="string", example="valide"),
     *                     @OA\Property(property="niveau", type="string", example="bureau"),
     *                     @OA\Property(property="niveau_id", type="integer", example=1),
     *                     @OA\Property(property="numero_pv", type="string", example="PV001"),
     *                     @OA\Property(property="election", type="string", example="Élections Législatives 2026"),
     *                     @OA\Property(property="localisation", type="string"),
     *                     @OA\Property(property="taux_participation", type="number", format="float", example=80.5)
     *                 )
     *             ),
     *             @OA\Property(property="count", type="integer", example=15)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): JsonResponse
    {
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

        // ✅ Formater les données avec chargement dynamique des entités géographiques
        // ✅ CORRECTION : Utiliser niveau_id au lieu de colonnes séparées
        $data = $pvs->map(function ($pv) {
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
                    'date' => $pv->election->date ?? null,
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
                
                // Statistiques
                'taux_participation' => $pv->taux_participation,
                'est_coherent' => $pv->est_coherent,
                
                // Métadonnées
                'statut' => $pv->statut,
                'created_at' => $pv->created_at,
                'updated_at' => $pv->updated_at,
                'saisi_par' => $pv->saisiPar?->name,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => $data->count(),
        ]);
    }

    /**
     * Créer un PV avec lignes et résultats détaillés
     * 
     * POST /api/v1/pv
     * 
     * @OA\Post(
     *     path="/pv",
     *     tags={"Procès-Verbaux"},
     *     summary="Créer un procès-verbal",
     *     description="Crée un nouveau PV avec lignes détaillées et résultats par ligne",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"election_id","code","niveau","niveau_id","lignes"},
     *             @OA\Property(property="election_id", type="integer", example=1),
     *             @OA\Property(property="code", type="string", maxLength=50, example="PV-LEG2026-ATL-COT-123456"),
     *             @OA\Property(property="niveau", type="string", enum={"bureau", "arrondissement", "commune", "village_quartier", "circonscription", "national"}, example="arrondissement"),
     *             @OA\Property(property="niveau_id", type="integer", example=45),
     *             @OA\Property(property="coordonnateur", type="string", example="JEAN BAPTISTE KPOGO"),
     *             @OA\Property(property="date_compilation", type="string", format="date-time", example="2026-01-15T14:30:00Z"),
     *             @OA\Property(property="numero_pv", type="string", example="PV001"),
     *             @OA\Property(property="nombre_inscrits", type="integer", example=500),
     *             @OA\Property(
     *                 property="lignes",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="village_quartier_id", type="integer", example=234),
     *                     @OA\Property(property="poste_vote_id", type="integer", example=null),
     *                     @OA\Property(property="centre_vote_id", type="integer", example=null),
     *                     @OA\Property(property="ordre", type="integer", example=1),
     *                     @OA\Property(property="bulletins_nuls", type="integer", example=6),
     *                     @OA\Property(
     *                         property="resultats",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="candidature_id", type="integer", example=1),
     *                             @OA\Property(property="nombre_voix", type="integer", example=10)
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="delegues",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="parti_politique_id", type="integer", example=1),
     *                     @OA\Property(property="nom_signataire", type="string", example="JEAN DOE"),
     *                     @OA\Property(property="a_signe", type="boolean", example=true),
     *                     @OA\Property(property="motif_absence", type="string", example=null),
     *                     @OA\Property(property="ordre", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="PV créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PV créé avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Erreur de validation"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
   public function store(Request $request): JsonResponse
{
    // ✅ Normalisation : si le front envoie entite_politique_id au lieu de parti_politique_id
    $payload = $request->all();
    if (isset($payload['delegues']) && is_array($payload['delegues'])) {
        foreach ($payload['delegues'] as $i => $d) {
            if (isset($d['entite_politique_id']) && !isset($d['parti_politique_id'])) {
                $payload['delegues'][$i]['parti_politique_id'] = $d['entite_politique_id'];
            }
        }
        $request->replace($payload);
    }

      // ✅ Normalisation : candidature_id
    $payload = $request->all();
    
    if (isset($payload['lignes']) && is_array($payload['lignes'])) {
        foreach ($payload['lignes'] as $i => $ligne) {
            if (isset($ligne['resultats']) && is_array($ligne['resultats'])) {
                foreach ($ligne['resultats'] as $j => $resultat) {
                    // Si pas de candidature_id mais entite_politique_id, on l'utilise
                    if (!isset($resultat['candidature_id']) && isset($resultat['entite_politique_id'])) {
                        $payload['lignes'][$i]['resultats'][$j]['candidature_id'] = $resultat['entite_politique_id'];
                    }
                }
            }
        }
        $request->replace($payload);
    }

    // Validation
    try {
        $validated = $request->validate([
            'election_id' => 'required|integer|exists:elections,id',
            'code' => 'required|string|max:50|unique:proces_verbaux,code',
            'niveau' => 'required|string|in:bureau,arrondissement,commune,village_quartier,circonscription,national',
            'niveau_id' => 'required|integer',
            'coordonnateur' => 'nullable|string|max:255',
            'date_compilation' => 'nullable|date',
            'numero_pv' => 'nullable|string|max:100',
            'nombre_inscrits' => 'nullable|integer|min:0',
            'observations' => 'nullable|string',

            'lignes' => 'required|array|min:1',
            'lignes.*.village_quartier_id' => 'nullable|integer|exists:villages_quartiers,id',
            'lignes.*.poste_vote_id' => 'nullable|integer|exists:postes_vote,id',
            'lignes.*.centre_vote_id' => 'nullable|integer|exists:centres_vote,id',
            'lignes.*.ordre' => 'nullable|integer',
            'lignes.*.bulletins_nuls' => 'required|integer|min:0',
            'lignes.*.president_nom' => 'nullable|string|max:255',
            'lignes.*.president_signature_image' => 'nullable|string|max:255',
            'lignes.*.presidents_postes_text' => 'nullable|string',

            'lignes.*.resultats' => 'required|array|min:1',
            'lignes.*.resultats.*.candidature_id' => 'required|integer',
            'lignes.*.resultats.*.nombre_voix' => 'required|integer|min:0',

            'delegues' => 'nullable|array',
            'delegues.*.parti_politique_id' => 'required|integer|exists:entites_politiques,id',
            'delegues.*.nom_signataire' => 'required|string|max:200',
            'delegues.*.a_signe' => 'required|boolean',
            'delegues.*.motif_absence' => 'nullable|string',
            'delegues.*.ordre' => 'nullable|integer',
        ]);
    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation échouée',
            'errors' => $e->errors(),
        ], 422);
    }

    DB::beginTransaction();

    try {
        $pv = ProcesVerbal::create([
            'code' => $validated['code'],
            'election_id' => $validated['election_id'],
            'niveau' => $validated['niveau'],
            'niveau_id' => $validated['niveau_id'],
            'coordonnateur' => $validated['coordonnateur'] ?? null,
            'date_compilation' => $validated['date_compilation'] ?? null,
            'numero_pv' => $validated['numero_pv'] ?? null,
            'nombre_inscrits' => $validated['nombre_inscrits'] ?? null,
            'observations' => $validated['observations'] ?? null,
            'statut' => 'brouillon',
            'saisi_par_user_id' => auth()->id(),
        ]);

        foreach ($validated['lignes'] as $index => $ligneData) {
            $ligne = $pv->lignes()->create([
                'village_quartier_id' => $ligneData['village_quartier_id'] ?? null,
                'poste_vote_id' => $ligneData['poste_vote_id'] ?? null,
                'centre_vote_id' => $ligneData['centre_vote_id'] ?? null,
                'ordre' => $ligneData['ordre'] ?? ($index + 1),
                'bulletins_nuls' => $ligneData['bulletins_nuls'],
                'president_nom' => $ligneData['president_nom'] ?? null,
                'president_signature_image' => $ligneData['president_signature_image'] ?? null,
                'presidents_postes_text' => $ligneData['presidents_postes_text'] ?? null,
                'operateur_user_id' => auth()->id(),
            ]);

            foreach ($ligneData['resultats'] as $resultatData) {
                $ligne->resultats()->create([
                    'candidature_id' => $resultatData['candidature_id'],
                    'nombre_voix' => $resultatData['nombre_voix'],
                    'operateur_user_id' => auth()->id(),
                ]);
            }
        }

        if (!empty($validated['delegues'])) {
            foreach ($validated['delegues'] as $index => $delegueData) {
                $pv->signatures()->create([
                    'parti_politique_id' => $delegueData['parti_politique_id'],
                    'type_signataire' => 'delegue_parti',
                    'nom_signataire' => $delegueData['nom_signataire'],
                    'a_signe' => $delegueData['a_signe'],
                    'motif_absence' => $delegueData['motif_absence'] ?? null,
                    'ordre' => $delegueData['ordre'] ?? ($index + 1),
                ]);
            }
        }

        $pv->calculerTotaux();

        DB::commit();

        // ✅ chargement relations sans casser le succès si une relation plante
        try {
            $pv->load([
                'election',
                'lignes.villageQuartier',
                'lignes.posteVote',
                'lignes.resultats.candidature.entitePolitique',
                'signatures.partiPolitique',
            ]);
        } catch (\Throwable $e) {
            $pv = $pv->fresh();
        }

        return response()->json([
            'success' => true,
            'message' => 'PV créé avec succès',
            'data' => $pv,
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création du PV',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    /**
     * Détail d'un PV
     * 
     * GET /api/v1/pv/{id}
     * 
     * @OA\Get(
     *     path="/pv/{id}",
     *     tags={"Procès-Verbaux"},
     *     summary="Détail d'un procès-verbal",
     *     description="Retourne les informations complètes d'un PV avec ses lignes et résultats détaillés",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du procès-verbal",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du PV",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="pv", type="object"),
     *                 @OA\Property(property="lignes", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="resultats_globaux", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="signatures", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="PV non trouvé"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    /**
     * Détails d’un PV (édition / affichage)
     * - Ajout: pv.election_id, pv.geo (IDs + libellés)
     * - Ajout: lignes.* IDs (village_quartier_id, centre_vote_id, poste_vote_id) + libellés centre/poste
     * - Ajout: signatures.* parti_politique_id (pour remapper côté frontend)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $pv = ProcesVerbal::with([
                'election',
                'lignes.villageQuartier',
                'lignes.centreVote',
                'lignes.posteVote',
                'lignes.resultats.candidature.entitePolitique',
                'signatures.partiPolitique',
                'saisiPar',
                'validePar'
            ])->findOrFail($id);

            // ===== Localisation + Geo =====
            $localisation = $pv->localisation;
            $geo = [
                'departement_id' => null,
                'departement_nom' => null,
                'commune_id' => null,
                'commune_nom' => null,
                'arrondissement_id' => null,
                'arrondissement_nom' => null,
                'village_quartier_id' => null,
                'village_quartier_nom' => null,
            ];

            try {
                if ($pv->niveau === 'arrondissement') {
                    $arr = \App\Models\Arrondissement::with('commune.departement')->find($pv->niveau_id);
                    if ($arr) {
                        $localisation = $arr->nom;
                        $geo['arrondissement_id'] = $arr->id;
                        $geo['arrondissement_nom'] = $arr->nom;
                        $geo['commune_id'] = $arr->commune?->id;
                        $geo['commune_nom'] = $arr->commune?->nom;
                        $geo['departement_id'] = $arr->commune?->departement?->id;
                        $geo['departement_nom'] = $arr->commune?->departement?->nom;
                    }
                } elseif ($pv->niveau === 'village_quartier') {
                    $vq = \App\Models\VillageQuartier::with('arrondissement.commune.departement')->find($pv->niveau_id);
                    if ($vq) {
                        $localisation = $vq->nom;
                        $geo['village_quartier_id'] = $vq->id;
                        $geo['village_quartier_nom'] = $vq->nom;
                        $geo['arrondissement_id'] = $vq->arrondissement?->id;
                        $geo['arrondissement_nom'] = $vq->arrondissement?->nom;
                        $geo['commune_id'] = $vq->arrondissement?->commune?->id;
                        $geo['commune_nom'] = $vq->arrondissement?->commune?->nom;
                        $geo['departement_id'] = $vq->arrondissement?->commune?->departement?->id;
                        $geo['departement_nom'] = $vq->arrondissement?->commune?->departement?->nom;
                    }
                } else {
                    $niveau = $pv->niveauGeographique;
                    if ($niveau && !$localisation) {
                        $localisation = $niveau->nom;
                    }
                }
            } catch (\Throwable $e) {
                // Ne pas bloquer
            }

            // ===== ✅ Lignes avec POURCENTAGES =====
            $lignesFormatees = $pv->lignes->sortBy('ordre')->map(function ($ligne) {
                $localisationLigne = null;

                if ($ligne->village_quartier_id && $ligne->villageQuartier) {
                    $localisationLigne = $ligne->villageQuartier->nom;
                } elseif ($ligne->poste_vote_id && $ligne->posteVote) {
                    $localisationLigne = $ligne->posteVote->nom;
                } else {
                    $localisationLigne = "Ligne #{$ligne->id}";
                }

                // ✅ Calculer le total des voix de la ligne pour les pourcentages
                $totalVoixLigne = (int) $ligne->total_voix;

                $resultatsFormates = $ligne->resultats->map(function ($resultat) use ($totalVoixLigne) {
                    $nbVoix = (int) ($resultat->nombre_voix ?? 0);
                    
                    // ✅ AJOUT : Calcul du pourcentage par rapport au total de la ligne
                    $pourcentage = $totalVoixLigne > 0 
                        ? round(($nbVoix / $totalVoixLigne) * 100, 2) 
                        : 0;

                    return [
                        'id' => $resultat->id,
                        'candidature_id' => $resultat->candidature_id,
                        'entite_politique_id' => $resultat->candidature?->entitePolitique?->id,
                        'entite_politique' => $resultat->candidature?->entitePolitique?->nom ?? 'N/A',
                        'sigle' => $resultat->candidature?->entitePolitique?->sigle ?? 'N/A',
                        'nombre_voix' => $nbVoix,
                        'pourcentage' => $pourcentage,  // ✅ AJOUTÉ
                    ];
                });

                return [
                    'id' => $ligne->id,
                    'localisation' => $localisationLigne,
                    'type' => $ligne->type,
                    'ordre' => $ligne->ordre,
                    'bulletins_nuls' => $ligne->bulletins_nuls,
                    'total_voix' => $totalVoixLigne,
                    'total_votants' => $ligne->total_votants,
                    'village_quartier_id' => $ligne->village_quartier_id,
                    'centre_vote_id' => $ligne->centre_vote_id,
                    'centre_vote_nom' => $ligne->centreVote?->nom,
                    'poste_vote_id' => $ligne->poste_vote_id,
                    'poste_vote_nom' => $ligne->posteVote?->nom,
                    'resultats' => $resultatsFormates
                ];
            })->values();

            // ===== Résultats globaux =====
            $resultatsGlobauxMap = [];

            foreach (($pv->lignes ?? []) as $ligne) {
                foreach (($ligne->resultats ?? []) as $r) {
                    $cid = $r->candidature_id;
                    if (!$cid) continue;

                    if (!isset($resultatsGlobauxMap[$cid])) {
                        $entitePolitique = $r->candidature?->entitePolitique;
                        
                        $resultatsGlobauxMap[$cid] = [
                            'candidature_id' => (int) $cid,
                            'entite_politique' => $entitePolitique?->nom ?? 'N/A',
                            'sigle' => $entitePolitique?->sigle ?? 'N/A',
                            'nombre_voix' => 0,
                            'pourcentage' => 0,
                        ];
                    }

                    $resultatsGlobauxMap[$cid]['nombre_voix'] += (int) ($r->nombre_voix ?? 0);
                }
            }

            $denom = (int) ($pv->nombre_suffrages_exprimes ?? 0);
            if ($denom > 0) {
                foreach ($resultatsGlobauxMap as $cid => $row) {
                    $resultatsGlobauxMap[$cid]['pourcentage'] = round(($row['nombre_voix'] / $denom) * 100, 2);
                }
            }

            $resultatsGlobaux = array_values($resultatsGlobauxMap);
            
            usort($resultatsGlobaux, function ($a, $b) {
                return strcmp((string) ($a['sigle'] ?? ''), (string) ($b['sigle'] ?? ''))
                    ?: ((int) ($a['candidature_id'] ?? 0) <=> (int) ($b['candidature_id'] ?? 0));
            });

            // ===== Signatures =====
            $signaturesFormatees = $pv->signatures->sortBy('ordre')->map(function ($signature) {
                return [
                    'id' => $signature->id,
                    'parti_politique_id' => $signature->parti_politique_id,
                    'parti' => $signature->partiPolitique?->sigle ?? 'N/A',
                    'nom_signataire' => $signature->nom_signataire,
                    'a_signe' => $signature->a_signe,
                    'motif_absence' => $signature->motif_absence,
                    'ordre' => $signature->ordre
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'PV récupéré avec succès',
                'data' => [
                    'pv' => [
                        'id' => $pv->id,
                        'code' => $pv->code,
                        'election_id' => $pv->election_id,
                        'election' => $pv->election?->nom ?? 'N/A',
                        'niveau' => $pv->niveau,
                        'niveau_id' => $pv->niveau_id,
                        'localisation' => $localisation,
                        'geo' => $geo,
                        'coordonnateur' => $pv->coordonnateur,
                        'date_compilation' => $pv->date_compilation,
                        'numero_pv' => $pv->numero_pv,
                        'nombre_inscrits' => $pv->nombre_inscrits,
                        'nombre_votants' => $pv->nombre_votants,
                        'nombre_suffrages_exprimes' => $pv->nombre_suffrages_exprimes,
                        'nombre_bulletins_nuls' => $pv->nombre_bulletins_nuls,
                        'taux_participation' => $pv->taux_participation,
                        'est_coherent' => $pv->est_coherent,
                        'motif_incoherence' => $pv->motif_incoherence,
                        'statut' => $pv->statut,
                        'observations' => $pv->observations,
                        'saisi_par' => $pv->saisiPar?->name,
                        'valide_par' => $pv->validePar?->name,
                        'created_at' => $pv->created_at,
                        'updated_at' => $pv->updated_at
                    ],
                    'lignes' => $lignesFormatees,
                    'resultats_globaux' => $resultatsGlobaux,
                    'signatures' => $signaturesFormatees
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'PV introuvable',
                'error' => 'PV avec ID ' . $id . ' n\'existe pas'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du PV',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mise à jour complète d’un PV (édition)
     * - Met à jour: coordonnateur, observations, lignes (avec résultats) et signatures
     * - Recalcule: totaux, taux de participation, cohérence
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $pv = ProcesVerbal::with(['lignes.resultats', 'signatures'])->findOrFail($id);

            // Validation
            $validated = $request->validate([
                'code' => 'sometimes|string|max:50|unique:proces_verbaux,code,' . $pv->id,
                'numero_pv' => 'nullable|string|max:50',
                'date_compilation' => 'nullable|date',
                'coordonnateur' => 'nullable|string|max:255',
                'observations' => 'nullable|string',
                'nombre_inscrits' => 'required|integer|min:0',

                'lignes' => 'required|array|min:1',
                'lignes.*.village_quartier_id' => 'nullable|integer|exists:villages_quartiers,id',
                'lignes.*.poste_vote_id' => 'nullable|integer|exists:postes_vote,id',
                'lignes.*.centre_vote_id' => 'nullable|integer|exists:centres_vote,id',
                'lignes.*.ordre' => 'required|integer|min:1',
                'lignes.*.bulletins_nuls' => 'required|integer|min:0',
                'lignes.*.president_nom' => 'nullable|string|max:255',
                'lignes.*.president_signature_image' => 'nullable|string',
                'lignes.*.presidents_postes_text' => 'nullable|string',
                'lignes.*.resultats' => 'required|array|min:1',
                'lignes.*.resultats.*.candidature_id' => 'required|integer',
                'lignes.*.resultats.*.nombre_voix' => 'required|integer|min:0',

                'delegues' => 'nullable|array',
                'delegues.*.parti_politique_id' => 'required|integer|exists:partis_politiques,id',
                'delegues.*.nom_signataire' => 'required|string|max:255',
                'delegues.*.a_signe' => 'required|boolean',
                'delegues.*.motif_absence' => 'nullable|string|max:255',
                'delegues.*.ordre' => 'required|integer|min:1',
            ]);

            // Contrôles niveau
            $niveau = $pv->niveau;
            foreach ($validated['lignes'] as $i => $ligne) {
                if ($niveau === 'arrondissement') {
                    if (empty($ligne['village_quartier_id'])) {
                        throw ValidationException::withMessages([
                            "lignes.$i.village_quartier_id" => "Requis pour un PV d'arrondissement."
                        ]);
                    }
                } elseif ($niveau === 'village_quartier') {
                    if (empty($ligne['poste_vote_id']) || empty($ligne['centre_vote_id'])) {
                        throw ValidationException::withMessages([
                            "lignes.$i.poste_vote_id" => "Requis pour un PV village/quartier.",
                            "lignes.$i.centre_vote_id" => "Requis pour un PV village/quartier."
                        ]);
                    }
                }
            }

            DB::beginTransaction();

            // ===== CALCUL DES TOTAUX =====
            $totalSuffrages = 0;
            $totalNuls = 0;

            foreach ($validated['lignes'] as $ligne) {
                $nuls = (int) ($ligne['bulletins_nuls'] ?? 0);
                $totalNuls += $nuls;

                $voixLigne = 0;
                foreach (($ligne['resultats'] ?? []) as $res) {
                    $voixLigne += (int) ($res['nombre_voix'] ?? 0);
                }
                $totalSuffrages += $voixLigne;
            }

            $totalVotants = $totalSuffrages + $totalNuls;
            $inscrits = (int) $validated['nombre_inscrits'];

            // ===== ✅ COHÉRENCE SIMPLIFIÉE =====
            $estCoherent = true;
            $erreurs = [];

            // LOG pour debug
            Log::info("PV UPDATE - Calculs", [
                'pv_id' => $pv->id,
                'inscrits' => $inscrits,
                'total_votants' => $totalVotants,
                'total_suffrages' => $totalSuffrages,
                'total_nuls' => $totalNuls,
            ]);

            // ✅ RÈGLE 1 : Votants = Suffrages + Nuls (TOUJOURS VRAI par construction)
            if (($totalSuffrages + $totalNuls) !== $totalVotants) {
                Log::warning("PV UPDATE - Incohérence interne détectée et corrigée");
                $totalVotants = $totalSuffrages + $totalNuls;
            }

            // ✅ RÈGLE 2 : Votants <= Inscrits (UNIQUEMENT si dépassement)
            // On accepte TOUS les cas où votants <= inscrits (même 1.3% de participation)
            if ($inscrits > 0 && $totalVotants > $inscrits) {
                $depassement = $totalVotants - $inscrits;
                $pourcentageDepassement = ($depassement / $inscrits) * 100;
                
                Log::warning("PV UPDATE - Dépassement détecté", [
                    'depassement' => $depassement,
                    'pourcentage' => $pourcentageDepassement
                ]);
                
                // ✅ Tolérance de 5% (très permissif pour erreurs de saisie mineures)
                if ($pourcentageDepassement > 5.0) {
                    $estCoherent = false;
                    $erreurs[] = sprintf(
                        "Dépassement : %d votants pour %d inscrits (+%.2f%%).",
                        $totalVotants,
                        $inscrits,
                        $pourcentageDepassement
                    );
                } else {
                    // Dépassement < 5% : on accepte avec warning
                    Log::info("PV UPDATE - Dépassement accepté (< 5%)", [
                        'pourcentage' => $pourcentageDepassement
                    ]);
                }
            }

            // ✅ RÈGLE 3 : Si pas d'inscrits, marquer comme incohérent
            if ($inscrits === 0) {
                Log::warning("PV UPDATE - Aucun inscrit saisi");
                $estCoherent = false;
                $erreurs[] = "Nombre d'inscrits manquant ou égal à zéro.";
            }

            // ✅ Calcul taux de participation
            $tauxParticipation = $inscrits > 0 ? round(($totalVotants / $inscrits) * 100, 2) : 0;

            // LOG final
            Log::info("PV UPDATE - Validation", [
                'est_coherent' => $estCoherent,
                'taux_participation' => $tauxParticipation,
                'erreurs' => $erreurs
            ]);

            // Mise à jour PV
            $pv->update([
                'code' => $validated['code'] ?? $pv->code,
                'numero_pv' => $validated['numero_pv'] ?? ($validated['code'] ?? $pv->code),
                'date_compilation' => $validated['date_compilation'] ?? $pv->date_compilation,
                'coordonnateur' => $validated['coordonnateur'] ?? $pv->coordonnateur,
                'observations' => $validated['observations'] ?? null,

                'nombre_inscrits' => $inscrits,
                'nombre_votants' => $totalVotants,
                'nombre_suffrages_exprimes' => $totalSuffrages,
                'nombre_bulletins_nuls' => $totalNuls,
                'taux_participation' => $tauxParticipation,
                'est_coherent' => $estCoherent,
                'motif_incoherence' => $estCoherent ? null : implode(' | ', $erreurs),
            ]);

            // Remplacement lignes
            foreach ($pv->lignes as $l) {
                $l->resultats()->delete();
            }
            $pv->lignes()->delete();

            foreach ($validated['lignes'] as $ligneData) {
                $ligne = $pv->lignes()->create([
                    'type' => $niveau,
                    'village_quartier_id' => $ligneData['village_quartier_id'] ?? null,
                    'poste_vote_id' => $ligneData['poste_vote_id'] ?? null,
                    'centre_vote_id' => $ligneData['centre_vote_id'] ?? null,
                    'ordre' => (int) $ligneData['ordre'],
                    'bulletins_nuls' => (int) $ligneData['bulletins_nuls'],
                    'president_nom' => $ligneData['president_nom'] ?? null,
                    'president_signature_image' => $ligneData['president_signature_image'] ?? null,
                    'presidents_postes_text' => $ligneData['presidents_postes_text'] ?? null,
                ]);

                foreach (($ligneData['resultats'] ?? []) as $res) {
                    $ligne->resultats()->create([
                        'candidature_id' => (int) $res['candidature_id'],
                        'nombre_voix' => (int) $res['nombre_voix'],
                    ]);
                }

                // Recalcul ligne
                $ligne->recalculerTotaux();
            }

            // Remplacement signatures
            $pv->signatures()->delete();
            foreach (($validated['delegues'] ?? []) as $sig) {
                $pv->signatures()->create([
                    'parti_politique_id' => (int) $sig['parti_politique_id'],
                    'nom_signataire' => $sig['nom_signataire'],
                    'a_signe' => (bool) $sig['a_signe'],
                    'motif_absence' => $sig['motif_absence'] ?? null,
                    'ordre' => (int) $sig['ordre'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $estCoherent ? 'PV mis à jour avec succès' : 'PV mis à jour (incohérences détectées)',
                'data' => [
                    'pv_id' => $pv->id,
                    'est_coherent' => $estCoherent,
                    'erreurs' => $erreurs,
                    'totaux' => [
                        'inscrits' => $inscrits,
                        'votants' => $totalVotants,
                        'suffrages_exprimes' => $totalSuffrages,
                        'bulletins_nuls' => $totalNuls,
                        'taux_participation' => $tauxParticipation,
                    ],
                    'debug' => [
                        'calcul_ok' => ($totalSuffrages + $totalNuls) === $totalVotants,
                        'depassement' => $totalVotants > $inscrits ? ($totalVotants - $inscrits) : 0,
                    ]
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'PV introuvable',
                'error' => 'PV avec ID ' . $id . ' n\'existe pas'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("PV UPDATE - Erreur", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du PV',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $pv = ProcesVerbal::find($id);

        if (!$pv) {
            return response()->json([
                'success' => false,
                'message' => 'PV non trouvé',
            ], 404);
        }

        // Les lignes, résultats et signatures seront supprimés automatiquement (CASCADE)
        $pv->delete();

        return response()->json([
            'success' => true,
            'message' => 'PV supprimé avec succès',
        ]);
    }

    /**
     * Valider un PV
     * 
     * POST /api/v1/pv/{id}/valider
     * 
     * @OA\Post(
     *     path="/pv/{id}/valider",
     *     tags={"Procès-Verbaux"},
     *     summary="Valider un PV",
     *     description="Change le statut d'un PV à 'valide' après vérification de cohérence",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du procès-verbal",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="valide_par_user_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PV validé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PV validé avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="PV incohérent, impossible de valider"),
     *     @OA\Response(response=404, description="PV non trouvé"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function valider(Request $request, int $id): JsonResponse
    {
        $pv = ProcesVerbal::find($id);

        if (!$pv) {
            return response()->json([
                'success' => false,
                'message' => 'PV non trouvé',
            ], 404);
        }

        $validated = $request->validate([
            'valide_par_user_id' => 'sometimes|integer|exists:users,id',
        ]);

        // Utiliser la méthode valider() du modèle
        $validePar = $validated['valide_par_user_id'] ?? auth()->id();
        
        if (!$pv->valider($validePar)) {
            $erreurs = $pv->verifierCoherence();
            
            return response()->json([
                'success' => false,
                'message' => 'PV incohérent, impossible de valider',
                'erreurs' => $erreurs,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'PV validé avec succès',
            'data' => $pv->fresh(),
        ]);
    }

    /**
     * Marquer un PV comme litigieux
     * 
     * POST /api/v1/pv/{id}/marquer-litigieux
     * 
     * @OA\Post(
     *     path="/pv/{id}/marquer-litigieux",
     *     tags={"Procès-Verbaux"},
     *     summary="Marquer un PV comme litigieux",
     *     description="Change le statut d'un PV à 'litigieux' avec observations obligatoires",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du procès-verbal",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"observations"},
     *             @OA\Property(property="observations", type="string", maxLength=1000, example="Incohérence détectée")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PV marqué comme litigieux",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="PV marqué comme litigieux"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="PV non trouvé"),
     *     @OA\Response(response=422, description="Observations requises"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function marquerLitigieux(Request $request, int $id): JsonResponse
    {
        $pv = ProcesVerbal::find($id);

        if (!$pv) {
            return response()->json([
                'success' => false,
                'message' => 'PV non trouvé',
            ], 404);
        }

        $validated = $request->validate([
            'observations' => 'required|string|max:1000',
        ]);

        // Utiliser la méthode marquerLitigieux() du modèle
        $pv->marquerLitigieux($validated['observations']);

        return response()->json([
            'success' => true,
            'message' => 'PV marqué comme litigieux',
            'data' => $pv->fresh(),
        ]);
    }

    /**
     * Upload d'un scan de PV
     * 
     * POST /api/v1/pv/upload-scan
     * 
     * @OA\Post(
     *     path="/pv/upload-scan",
     *     tags={"Procès-Verbaux"},
     *     summary="Upload d'un scan de PV",
     *     description="Upload et stockage d'un fichier scanné du procès-verbal",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"proces_verbal_id","fichier"},
     *                 @OA\Property(property="proces_verbal_id", type="integer", example=1),
     *                 @OA\Property(property="fichier", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Scan uploadé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Scan uploadé avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="PV non trouvé"),
     *     @OA\Response(response=422, description="Fichier invalide"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function uploadScan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'proces_verbal_id' => 'required|integer|exists:proces_verbaux,id',
            'fichier' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $pv = ProcesVerbal::findOrFail($validated['proces_verbal_id']);

        // Stocker le fichier
        $fichier = $request->file('fichier');
        $filename = 'pv_' . $pv->code . '_' . time() . '.' . $fichier->getClientOriginalExtension();
        $path = $fichier->storeAs('pv_scans', $filename, 'public');

        // Calculer le checksum
        $checksum = hash_file('sha256', $fichier->getRealPath());

        // Mettre à jour le PV
        $pv->update([
            'fichier_scan' => $path,
            'checksum' => $checksum,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Scan uploadé avec succès',
            'data' => [
                'pv' => $pv->fresh(),
                'fichier' => $path,
                'checksum' => $checksum,
            ],
        ]);
    }

    /**
     * Vérifier la cohérence d'un PV
     * 
     * GET /api/v1/pv/{id}/verification
     * 
     * @OA\Get(
     *     path="/pv/{id}/verification",
     *     tags={"Procès-Verbaux"},
     *     summary="Vérifier la cohérence d'un PV",
     *     description="Vérifie la cohérence mathématique du PV",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du procès-verbal",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Résultat de la vérification",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="PV non trouvé"),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function verification(int $id): JsonResponse
    {
        $pv = ProcesVerbal::find($id);

        if (!$pv) {
            return response()->json([
                'success' => false,
                'message' => 'PV non trouvé',
            ], 404);
        }

        // Utiliser la méthode verifierCoherence() du modèle
        $erreurs = $pv->verifierCoherence();

        return response()->json([
            'success' => true,
            'data' => [
                'coherent' => count($erreurs) === 0,
                'erreurs' => $erreurs,
                'statistiques' => [
                    'inscrits' => $pv->nombre_inscrits,
                    'votants' => $pv->nombre_votants,
                    'bulletins_nuls' => $pv->nombre_bulletins_nuls,
                    'suffrages_exprimes' => $pv->nombre_suffrages_exprimes,
                    'taux_participation' => $pv->taux_participation,
                ],
            ],
        ]);
    }

    /**
 * Vérifier quels villages/quartiers ont déjà un PV pour un arrondissement
 */
public function checkVillagesEnregistres(Request $request): JsonResponse
{
    $arrondissementId = $request->input('arrondissement_id');
    
    if (!$arrondissementId) {
        return response()->json([
            'success' => false,
            'message' => 'arrondissement_id requis'
        ], 400);
    }
    
    // Récupérer tous les PV de type village_quartier pour cet arrondissement
    $pvsExistants = ProcesVerbal::where('niveau', 'village_quartier')
        ->whereHas('niveauGeographique', function ($query) use ($arrondissementId) {
            $query->where('arrondissement_id', $arrondissementId);
        })
        ->get(['niveau_id as village_quartier_id']);
    
    return response()->json([
        'success' => true,
        'data' => $pvsExistants,
    ]);
}
}