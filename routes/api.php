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
    PVValidationController
};

/*
|--------------------------------------------------------------------------
| API Routes - CENA Bénin 2026
|--------------------------------------------------------------------------
| Version : 1.0
| API Version : v1
|
| ⚠️ ATTENTION À L'ORDRE DES ROUTES :
| - Routes fixes AVANT routes paramétrées {id}
| - ex: "check-existant" AVANT "{id}"
|--------------------------------------------------------------------------
*/

// ✅ Health global (hors v1) - utile pour tests rapides
Route::get('health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'API CENA Bénin 2026',
        'version' => '1.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::prefix('v1')->group(function () {

    // =========================================================
    // ROUTES PUBLIQUES (Sans authentification)
    // =========================================================

    // Auth
    Route::post('login', [AuthController::class, 'login'])
        ->name('auth.login')
        ->middleware('throttle:20,1'); // anti brute-force simple

    // Health v1
    Route::get('health', function () {
        return response()->json([
            'status' => 'OK',
            'message' => 'API CENA Bénin 2026 (v1)',
            'version' => '1.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    })->name('v1.health');

    // =========================================================
    // ROUTES PROTÉGÉES (Sanctum)
    // =========================================================
    Route::middleware('auth:sanctum')->group(function () {

        // ------------------------------------------
        // AUTH - Utilisateur connecté
        // ------------------------------------------
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');

        // ✅ IMPORTANT : évite conflit avec ElectionController@index
        // (anciennement GET elections)
        Route::get('me/elections', [AuthController::class, 'elections'])->name('auth.me.elections');

        Route::put('profile', [AuthController::class, 'updateProfile'])->name('auth.update-profile');
        Route::get('permissions', [AuthController::class, 'permissions'])->name('auth.permissions');
        Route::post('change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');
        Route::get('roles', [AuthController::class, 'getRoles'])->name('auth.roles');

        // ------------------------------------------
        // GÉOGRAPHIE
        // ------------------------------------------
        Route::prefix('geographie')->group(function () {

            // Listes
            Route::get('departements', [GeographieController::class, 'departements'])->name('geographie.departements');
            Route::get('circonscriptions', [GeographieController::class, 'circonscriptions'])->name('geographie.circonscriptions');
            Route::get('communes', [GeographieController::class, 'communes'])->name('geographie.communes');
            Route::get('arrondissements', [GeographieController::class, 'arrondissements'])->name('geographie.arrondissements');
            Route::get('villages-quartiers', [GeographieController::class, 'villagesQuartiers'])->name('geographie.villages-quartiers');

            // Hiérarchie
            Route::get('hierarchie', [GeographieController::class, 'hierarchie'])->name('geographie.hierarchie');
            Route::get('hierarchie/{departementId}', [GeographieController::class, 'hierarchieParDepartement'])->name('geographie.hierarchie.departement');

            // Centres de vote
            Route::get('centres-vote', [GeographieController::class, 'centresVote'])->name('geographie.centres-vote');
            Route::post('centres-vote', [GeographieController::class, 'createCentreVote'])->name('geographie.create-centre-vote');

            // Postes de vote
            Route::get('postes-vote', [GeographieController::class, 'postesVote'])->name('geographie.postes-vote');
            Route::post('postes-vote', [GeographieController::class, 'createPosteVote'])->name('geographie.create-poste-vote');

            // Création villages/quartiers
            Route::post('villages-quartiers', [GeographieController::class, 'createVillageQuartier'])->name('geographie.create-village-quartier');

            // Coordonnateurs (filtré par arrondissement)
            Route::get('coordonnateurs', [GeographieController::class, 'coordonnateurs'])->name('geographie.coordonnateurs');

            // Inscrits (⚠️ à laisser AVANT d'éventuelles routes {id})
            Route::get('arrondissements/{id}/inscrits', [PVValidationController::class, 'getInscritsArrondissement'])
                ->name('geographie.arrondissements.inscrits');

            Route::get('villages-quartiers/{id}/inscrits', [PVValidationController::class, 'getInscritsVillageQuartier'])
                ->name('geographie.villages-quartiers.inscrits');
        });

        // ------------------------------------------
        // CALCULS ÉLECTORAUX
        // ------------------------------------------
        Route::prefix('calculs')->group(function () {
            Route::post('repartition-sieges', [CalculElectoralController::class, 'calculerRepartitionSieges'])->name('calculs.repartition-sieges');
            Route::post('repartition-nationale', [CalculElectoralController::class, 'calculerRepartitionNationale'])->name('calculs.repartition-nationale');
            Route::get('verifier-seuils/{candidatureId}', [CalculElectoralController::class, 'verifierSeuils'])->name('calculs.verifier-seuils');
            Route::get('strategy-info', [CalculElectoralController::class, 'getStrategyInfo'])->name('calculs.strategy-info');
            Route::post('change-strategy', [CalculElectoralController::class, 'changeStrategy'])->name('calculs.change-strategy');
            Route::post('comparer-strategies', [CalculElectoralController::class, 'comparerStrategies'])->name('calculs.comparer-strategies');
        });

        // ------------------------------------------
        // ÉLECTIONS  (⚠️ ORDRE CRITIQUE)
        // ------------------------------------------
        // 1) Collection
        Route::get('elections', [ElectionController::class, 'index'])->name('elections.index');
        Route::post('elections', [ElectionController::class, 'store'])->name('elections.store');

        // 2) Mots-clés fixes
        Route::get('elections/types', [ElectionController::class, 'types'])->name('elections.types');

        // 3) ID + suffixes
        Route::get('elections/{id}/entites-politiques', [ElectionController::class, 'entitesPolitiques'])->name('elections.entites-politiques');
        Route::get('elections/{id}/candidatures', [ElectionController::class, 'candidatures'])->name('elections.candidatures');
        Route::get('elections/{id}/resultats', [ElectionController::class, 'resultats'])->name('elections.resultats');
        Route::get('elections/{id}/statistiques', [ElectionController::class, 'statistiques'])->name('elections.statistiques');

        // 4) CRUD générique
        Route::get('elections/{id}', [ElectionController::class, 'show'])->name('elections.show');
        Route::put('elections/{id}', [ElectionController::class, 'update'])->name('elections.update');
        Route::delete('elections/{id}', [ElectionController::class, 'destroy'])->name('elections.destroy');

        // ------------------------------------------
        // CANDIDATURES
        // ------------------------------------------
        Route::get('candidatures', [CandidatureController::class, 'index'])->name('candidatures.index');
        Route::post('candidatures', [CandidatureController::class, 'store'])->name('candidatures.store');
        Route::get('candidatures/{id}', [CandidatureController::class, 'show'])->name('candidatures.show');
        Route::put('candidatures/{id}', [CandidatureController::class, 'update'])->name('candidatures.update');
        Route::delete('candidatures/{id}', [CandidatureController::class, 'destroy'])->name('candidatures.destroy');

        Route::post('candidatures/{id}/valider', [CandidatureController::class, 'valider'])->name('candidatures.valider');
        Route::post('candidatures/{id}/rejeter', [CandidatureController::class, 'rejeter'])->name('candidatures.rejeter');

        // ------------------------------------------
        // PROCÈS-VERBAUX (PV) (⚠️ ORDRE CRITIQUE)
        // ------------------------------------------
        Route::prefix('pv')->group(function () {

            // Collection
            Route::get('/', [ProcesVerbalController::class, 'index'])->name('pv.index');
            Route::post('/', [ProcesVerbalController::class, 'store'])->name('pv.store');

            // ✅ Spécifique AVANT {id}
            Route::get('check-existant', [PVValidationController::class, 'checkExistant'])->name('pv.check-existant');

            // CRUD {id}
            Route::get('{id}', [ProcesVerbalController::class, 'show'])->name('pv.show');
            Route::put('{id}', [ProcesVerbalController::class, 'update'])->name('pv.update');
            Route::delete('{id}', [ProcesVerbalController::class, 'destroy'])->name('pv.destroy');

            // Actions
            Route::post('{id}/valider', [ProcesVerbalController::class, 'valider'])->name('pv.valider');
            Route::post('{id}/marquer-litigieux', [ProcesVerbalController::class, 'marquerLitigieux'])->name('pv.marquer-litigieux');
            Route::get('{id}/verification', [ProcesVerbalController::class, 'verification'])->name('pv.verification');

            // Upload scan
            Route::post('upload-scan', [ProcesVerbalController::class, 'uploadScan'])->name('pv.upload-scan');
        });

        // ------------------------------------------
        // RÉSULTATS
        // ------------------------------------------
        Route::prefix('resultats')->group(function () {
            Route::post('saisie', [ResultatController::class, 'saisie'])->name('resultats.saisie');
            Route::post('saisie-multiple', [ResultatController::class, 'saisieMultiple'])->name('resultats.saisie-multiple');
            Route::get('comparaison/{pvId}', [ResultatController::class, 'comparaison'])->name('resultats.comparaison');
            Route::post('validation/{pvId}', [ResultatController::class, 'validation'])->name('resultats.validation');
            Route::get('historique/{resultatId}', [ResultatController::class, 'historique'])->name('resultats.historique');
        });

        // ------------------------------------------
        // AGRÉGATIONS
        // ------------------------------------------
        Route::prefix('agregations')->group(function () {
            Route::get('{niveau}/{niveauId}', [AgregationController::class, 'parNiveau'])->name('agregations.par-niveau');
            Route::post('calculer/{electionId}', [AgregationController::class, 'calculer'])->name('agregations.calculer');
            Route::get('election/{electionId}/national', [AgregationController::class, 'national'])->name('agregations.national');
            Route::get('election/{electionId}/circonscription/{circonscriptionId}', [AgregationController::class, 'parCirconscription'])->name('agregations.circonscription');
            Route::get('election/{electionId}/statistiques', [AgregationController::class, 'statistiques'])->name('agregations.statistiques');
            Route::post('election/{electionId}/definir', [AgregationController::class, 'definir'])->name('agregations.definir');
        });

        // ------------------------------------------
        // INCIDENTS
        // ------------------------------------------
        Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
        Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
        Route::get('incidents/{id}', [IncidentController::class, 'show'])->name('incidents.show');
        Route::put('incidents/{id}', [IncidentController::class, 'update'])->name('incidents.update');
        Route::delete('incidents/{id}', [IncidentController::class, 'destroy'])->name('incidents.destroy');

        Route::post('incidents/{id}/traiter', [IncidentController::class, 'traiter'])->name('incidents.traiter');
        Route::post('incidents/{id}/resoudre', [IncidentController::class, 'resoudre'])->name('incidents.resoudre');

        // ------------------------------------------
        // EXPORT PDF
        // ------------------------------------------
        Route::get('export/pv/{id}/pdf', [ExportController::class, 'exportPdf'])->name('export.pv.pdf');
    });

    // Dashboard optionnel
    if (file_exists(__DIR__ . '/dashboard.php')) {
        require __DIR__ . '/dashboard.php';
    }
});

// ✅ Fallback JSON propre
Route::fallback(function () {
    return response()->json(['message' => 'Route API introuvable'], 404);
});
