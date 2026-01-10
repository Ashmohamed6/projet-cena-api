<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController,
    CalculElectoralController,
    GeographieController,
    ElectionController,
    CandidatureController,
    ProcesVerbalController,
    ResultatController,
    AgregationController,
    IncidentController,
    ExportController,
    PVValidationController,
    PVVerificationController
};

/*
|--------------------------------------------------------------------------
| API Routes - CENA Bénin 2026
|--------------------------------------------------------------------------
| Version : 1.0
| API Version : v1
*/

// ✅ Health global (hors v1)
Route::get('health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'API CENA Bénin 2026',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::prefix('v1')->group(function () {

    // =========================================================
    // ROUTES PUBLIQUES
    // =========================================================

    Route::post('login', [AuthController::class, 'login'])
        ->name('auth.login')
        ->middleware('throttle:20,1');

    Route::get('health', function () {
        return response()->json(['status' => 'OK', 'version' => 'v1']);
    })->name('v1.health');

    // =========================================================
    // ROUTES PROTÉGÉES (Sanctum)
    // =========================================================
    Route::middleware('auth:sanctum')->group(function () {

        // --- AUTH ---
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::get('me/elections', [AuthController::class, 'elections'])->name('auth.me.elections');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('auth.update-profile');
        Route::get('permissions', [AuthController::class, 'permissions'])->name('auth.permissions');
        Route::post('change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
        Route::get('roles', [AuthController::class, 'getRoles'])->name('auth.roles');

        // --- GÉOGRAPHIE ---
        Route::prefix('geographie')->name('geographie.')->group(function () {
            // Listes
            Route::get('departements', [GeographieController::class, 'departements'])->name('departements');
            Route::get('circonscriptions', [GeographieController::class, 'circonscriptions'])->name('circonscriptions');
            Route::get('communes', [GeographieController::class, 'communes'])->name('communes');
            Route::get('arrondissements', [GeographieController::class, 'arrondissements'])->name('arrondissements');
            Route::get('villages-quartiers', [GeographieController::class, 'villagesQuartiers'])->name('villages-quartiers');
            
            // Détails (ID)
            Route::get('arrondissements/{id}', [GeographieController::class, 'arrondissement'])->name('arrondissements.show');
            Route::get('villages-quartiers/{id}', [GeographieController::class, 'villageQuartier'])->name('villages-quartiers.show');

            // Hiérarchie
            Route::get('hierarchie', [GeographieController::class, 'hierarchie'])->name('hierarchie');
            Route::get('hierarchie/{departementId}', [GeographieController::class, 'hierarchieParDepartement'])->name('hierarchie.departement');
       
            Route::get('arrondissements/{id}/inscrits', [PVValidationController::class, 'getInscritsArrondissement']);
            Route::get('villages-quartiers/{id}/inscrits', [PVValidationController::class, 'getInscritsVillageQuartier']);
            // La route communes/{id}/inscrits existait déjà, on la garde
            Route::get('communes/{id}/inscrits', [PVValidationController::class, 'getInscritsCommune']);


            // Centres & Postes
            Route::get('centres-vote', [GeographieController::class, 'centresVote'])->name('centres-vote');
            Route::post('centres-vote', [GeographieController::class, 'createCentreVote'])->name('create-centre-vote');
            Route::get('postes-vote', [GeographieController::class, 'postesVote'])->name('postes-vote');
            Route::post('postes-vote', [GeographieController::class, 'createPosteVote'])->name('create-poste-vote');
            Route::get('postes-vote/{id}', [GeographieController::class, 'posteVote'])->name('postes-vote.show');

            // Coordonnateurs
            Route::get('coordonnateurs', [GeographieController::class, 'coordonnateurs'])->name('coordonnateurs');
        });

        // --- CALCULS ÉLECTORAUX ---
        Route::prefix('calculs')->name('calculs.')->group(function () {
            Route::post('repartition-sieges', [CalculElectoralController::class, 'calculerRepartitionSieges'])->name('repartition-sieges');
            Route::post('repartition-nationale', [CalculElectoralController::class, 'calculerRepartitionNationale'])->name('repartition-nationale');
            Route::get('verifier-seuils/{candidatureId}', [CalculElectoralController::class, 'verifierSeuils'])->name('verifier-seuils');
            Route::get('strategy-info', [CalculElectoralController::class, 'getStrategyInfo'])->name('strategy-info');
            Route::post('change-strategy', [CalculElectoralController::class, 'changeStrategy'])->name('change-strategy');
            Route::post('comparer-strategies', [CalculElectoralController::class, 'comparerStrategies'])->name('comparer-strategies');
        });

        // --- ÉLECTIONS ---
        // 1. Routes spécifiques
        Route::get('elections/types', [ElectionController::class, 'types'])->name('elections.types');
        Route::get('elections/{id}/entites-politiques', [ElectionController::class, 'entitesPolitiques'])->name('elections.entites-politiques');
        Route::get('elections/{id}/candidatures', [ElectionController::class, 'candidatures'])->name('elections.candidatures');
        Route::get('elections/{id}/resultats', [ElectionController::class, 'resultats'])->name('elections.resultats');
        Route::get('elections/{id}/statistiques', [ElectionController::class, 'statistiques'])->name('elections.statistiques');
        
        // 2. CRUD
        Route::apiResource('elections', ElectionController::class);

        // --- CANDIDATURES ---
        Route::post('candidatures/{id}/valider', [CandidatureController::class, 'valider'])->name('candidatures.valider');
        Route::post('candidatures/{id}/rejeter', [CandidatureController::class, 'rejeter'])->name('candidatures.rejeter');
        Route::apiResource('candidatures', CandidatureController::class);

        // --- PROCÈS-VERBAUX (PV) ---
Route::prefix('pv')->name('pv.')->group(function () {

    // ✅ 1. VÉRIFICATION
    Route::get('verification/existe', [PVVerificationController::class, 'verifierExistence'])->name('verification.existe');
    Route::get('verification/entites-utilisees', [PVVerificationController::class, 'getEntitesUtilisees'])->name('verification.entites-utilisees');

    // 2. VALIDATION (inscrits)
    Route::get('validation/inscrits/arrondissement/{id}', [PVValidationController::class, 'getInscritsArrondissement'])->name('validation.inscrits.arrondissement');
    Route::get('validation/inscrits/village-quartier/{id}', [PVValidationController::class, 'getInscritsVillageQuartier'])->name('validation.inscrits.village-quartier');
    Route::get('validation/inscrits/commune/{id}', [PVValidationController::class, 'getInscritsCommune'])->name('validation.inscrits.commune');
    Route::get('validation/inscrits/centre-vote/{id}', [PVValidationController::class, 'getInscritsCentreVote'])->name('validation.inscrits.centre-vote');
    Route::get('validation/check-existant', [PVValidationController::class, 'checkExistant'])->name('validation.check-existant');

    // 3. Upload
    Route::post('upload-scan', [ProcesVerbalController::class, 'uploadScan'])->name('upload-scan');

    // 4. Actions spécifiques sur un PV ({id})
    Route::post('{id}/valider', [ProcesVerbalController::class, 'valider'])->whereNumber('id')->name('valider');
    Route::post('{id}/rejeter', [ProcesVerbalController::class, 'rejeter'])->whereNumber('id')->name('rejeter');
    Route::post('{id}/marquer-litigieux', [ProcesVerbalController::class, 'marquerLitigieux'])->whereNumber('id')->name('marquer-litigieux');
    Route::get('{id}/verification', [ProcesVerbalController::class, 'verification'])->whereNumber('id')->name('verification-detail');

    // 5. CRUD Standard
    Route::get('/', [ProcesVerbalController::class, 'index'])->name('index');
    Route::post('/', [ProcesVerbalController::class, 'store'])->name('store');
    Route::get('{id}', [ProcesVerbalController::class, 'show'])->whereNumber('id')->name('show');
    Route::put('{id}', [ProcesVerbalController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('{id}', [ProcesVerbalController::class, 'destroy'])->whereNumber('id')->name('destroy');
});


        // --- RÉSULTATS ---
        /* Route::prefix('resultats')->name('resultats.')->group(function () {
            Route::post('saisie', [ResultatController::class, 'saisie'])->name('saisie');
            Route::post('saisie-multiple', [ResultatController::class, 'saisieMultiple'])->name('saisie-multiple');
            Route::get('comparaison/{pvId}', [ResultatController::class, 'comparaison'])->name('comparaison');
            Route::post('validation/{pvId}', [ResultatController::class, 'validation'])->name('validation');
            Route::get('historique/{resultatId}', [ResultatController::class, 'historique'])->name('historique');
        }); */

        Route::prefix('resultats')->group(function () {
    Route::get('legislative/donnees-eligibilite', [ResultatController::class, 'donneesEligibilite']);
    Route::post('legislative/calculer-eligibilite', [ResultatController::class, 'calculerEligibilite']);
    Route::post('legislative/repartir-sieges', [ResultatController::class, 'repartirSieges']);
});

        // --- AGRÉGATIONS ---
        Route::prefix('agregations')->name('agregations.')->group(function () {
            Route::post('calculer/{electionId}', [AgregationController::class, 'calculer'])->name('calculer');
            Route::get('election/{electionId}/national', [AgregationController::class, 'national'])->name('national');
            Route::get('election/{electionId}/circonscription/{circonscriptionId}', [AgregationController::class, 'parCirconscription'])->name('circonscription');
            Route::get('election/{electionId}/statistiques', [AgregationController::class, 'statistiques'])->name('statistiques');
            Route::post('election/{electionId}/definir', [AgregationController::class, 'definir'])->name('definir');
            // Route générique en dernier
            Route::get('{niveau}/{niveauId}', [AgregationController::class, 'parNiveau'])->name('par-niveau');
        });

        // --- INCIDENTS ---
        Route::post('incidents/{id}/traiter', [IncidentController::class, 'traiter'])->name('incidents.traiter');
        Route::post('incidents/{id}/resoudre', [IncidentController::class, 'resoudre'])->name('incidents.resoudre');
        Route::apiResource('incidents', IncidentController::class);

        // --- EXPORT PDF ---
        Route::get('export/pv/{id}/pdf', [ExportController::class, 'exportPdf'])->name('export.pv.pdf');

        // ✅ DASHBOARD
        if (file_exists(__DIR__ . '/dashboard.php')) {
            require __DIR__ . '/dashboard.php';
        }

    }); // ✅ FIN DU GROUPE auth:sanctum

}); // ✅ FIN DU GROUPE v1

// ✅ Fallback JSON
Route::fallback(function () {
    return response()->json(['message' => 'Route API introuvable'], 404);
});