<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Dashboard\LegislativeDashboardController;
use App\Http\Controllers\Api\Dashboard\CommunaleDashboardController;
use App\Http\Controllers\Api\Dashboard\PresidentielleDashboardController;

Route::prefix('dashboard')->middleware(['auth:sanctum', 'election.access'])->group(function () {

    // =======================
    // LEGISLATIVE
    // =======================
    Route::prefix('legislative')->group(function () {

        // ✅ Routes principales (appellées par le frontend)
        Route::get('/', [LegislativeDashboardController::class, 'stats']);
        Route::get('/stats', [LegislativeDashboardController::class, 'stats']);
        Route::get('/resultats', [LegislativeDashboardController::class, 'resultats']);
        
        // ✅ NOUVEAU: Endpoint /partis manquant
        Route::get('/partis', [LegislativeDashboardController::class, 'partis']);
        Route::get('/partis/{id}', [LegislativeDashboardController::class, 'parti']);
        
        // ✅ Participation (avec alias)
        Route::get('/participation', [LegislativeDashboardController::class, 'participation']);
        Route::get('/taux-participation', [LegislativeDashboardController::class, 'tauxParticipation']);
        
        // Progression et monitoring
        Route::get('/progression', [LegislativeDashboardController::class, 'progression']);
        Route::get('/incidents', [LegislativeDashboardController::class, 'incidents']);
        Route::get('/audit', [LegislativeDashboardController::class, 'audit']);
        Route::get('/historique', [LegislativeDashboardController::class, 'historique']);
        Route::get('/cartographie', [LegislativeDashboardController::class, 'cartographie']);

        // Circonscriptions
        Route::get('/circonscriptions', [LegislativeDashboardController::class, 'circonscriptions']);
        Route::get('/circonscriptions/{id}', [LegislativeDashboardController::class, 'circonscription']);
        
        // Répartition des sièges
        Route::get('/repartition-sieges', [LegislativeDashboardController::class, 'repartitionSieges']);

        // ✅ Alias pour compatibilité
        Route::get('/results', [LegislativeDashboardController::class, 'resultats']);
    });

    // =======================
    // COMMUNALE
    // =======================
    Route::prefix('communale')->group(function () {

        Route::get('/', [CommunaleDashboardController::class, 'stats']);
        Route::get('/stats', [CommunaleDashboardController::class, 'stats']);
        Route::get('/resultats', [CommunaleDashboardController::class, 'resultats']);
        
        // ✅ Participation (avec alias)
        Route::get('/participation', [CommunaleDashboardController::class, 'participation']);
        Route::get('/taux-participation', [CommunaleDashboardController::class, 'tauxParticipation']);
        
        Route::get('/progression', [CommunaleDashboardController::class, 'progression']);
        Route::get('/incidents', [CommunaleDashboardController::class, 'incidents']);
        Route::get('/audit', [CommunaleDashboardController::class, 'audit']);
        Route::get('/historique', [CommunaleDashboardController::class, 'historique']);
        Route::get('/cartographie', [CommunaleDashboardController::class, 'cartographie']);

        // Communes
        Route::get('/communes/{id}', [CommunaleDashboardController::class, 'resultatCommune']);

        // ✅ Alias pour compatibilité
        Route::get('/results', [CommunaleDashboardController::class, 'resultats']);
    });

    // =======================
    // PRESIDENTIELLE
    // =======================
    Route::prefix('presidentielle')->group(function () {

        Route::get('/', [PresidentielleDashboardController::class, 'stats']);
        Route::get('/stats', [PresidentielleDashboardController::class, 'stats']);
        Route::get('/resultats', [PresidentielleDashboardController::class, 'resultats']);
        
        // ✅ Participation (avec alias)
        Route::get('/participation', [PresidentielleDashboardController::class, 'participation']);
        Route::get('/taux-participation', [PresidentielleDashboardController::class, 'tauxParticipation']);
        
        Route::get('/progression', [PresidentielleDashboardController::class, 'progression']);
        Route::get('/incidents', [PresidentielleDashboardController::class, 'incidents']);
        Route::get('/audit', [PresidentielleDashboardController::class, 'audit']);
        Route::get('/historique', [PresidentielleDashboardController::class, 'historique']);
        Route::get('/cartographie', [PresidentielleDashboardController::class, 'cartographie']);

        // Départements
        Route::get('/departements/{id}', [PresidentielleDashboardController::class, 'resultatDepartement']);

        // ✅ Alias pour compatibilité
        Route::get('/results', [PresidentielleDashboardController::class, 'resultats']);
    });

});