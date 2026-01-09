<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Dashboard\{
    LegislativeDashboardController,
    CommunaleDashboardController,
    PresidentielleDashboardController
};

/**
 * ═══════════════════════════════════════════════════════════════
 * ROUTES DASHBOARD - CENA BÉNIN 2026
 * ═══════════════════════════════════════════════════════════════
 * 
 * Toutes les routes dashboard avec les nouveaux KPIs
 */

Route::prefix('dashboard')->middleware(['auth:sanctum', 'election.access'])->group(function () {

    // ═══════════════════════════════════════════════════════════════
    // DASHBOARD LÉGISLATIVES
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('legislative')->group(function () {

        // ✅ STATISTIQUES GLOBALES
        Route::get('/', [LegislativeDashboardController::class, 'stats'])->name('dashboard.legislative.stats');
        Route::get('stats', [LegislativeDashboardController::class, 'stats']);
        
        // ✅ BANDEAU SUPÉRIEUR (KPIs principaux)
        Route::get('participation', [LegislativeDashboardController::class, 'participation'])->name('dashboard.legislative.participation');
        Route::get('avancement', [LegislativeDashboardController::class, 'avancement'])->name('dashboard.legislative.avancement');
        Route::get('vitesse-saisie', [LegislativeDashboardController::class, 'vitesseSaisie'])->name('dashboard.legislative.vitesse');
        
        // ✅ EXPLORATEUR GÉOGRAPHIQUE (Drill-Down)
        Route::get('vue-nationale', [LegislativeDashboardController::class, 'vueNationale'])->name('dashboard.legislative.vue-nationale');
        Route::get('vue-departement/{departementId}', [LegislativeDashboardController::class, 'vueDepartement'])->name('dashboard.legislative.vue-departement');
        Route::get('palmares-departements', [LegislativeDashboardController::class, 'palmaresDepartements'])->name('dashboard.legislative.palmares');
        
        // ✅ KPIs LÉGISLATIVES (Nouveaux endpoints)
        Route::get('barometre-10', [LegislativeDashboardController::class, 'barometre10'])->name('dashboard.legislative.barometre-10');
        Route::get('matrice-20', [LegislativeDashboardController::class, 'matrice20'])->name('dashboard.legislative.matrice-20');
        Route::get('hemicycle', [LegislativeDashboardController::class, 'hemicycle'])->name('dashboard.legislative.hemicycle');
        
        // ✅ MODULE CONFRONTATION (Audit)
        Route::get('radar-divergence', [LegislativeDashboardController::class, 'radarDivergence'])->name('dashboard.legislative.radar');
        Route::get('flux-anomalies', [LegislativeDashboardController::class, 'fluxAnomalies'])->name('dashboard.legislative.anomalies');
        
        // ✅ ANCIENS ENDPOINTS (compatibilité)
        Route::get('resultats', [LegislativeDashboardController::class, 'resultats']);
        Route::get('partis', [LegislativeDashboardController::class, 'partis']);
        Route::get('partis/{id}', [LegislativeDashboardController::class, 'parti']);
        Route::get('taux-participation', [LegislativeDashboardController::class, 'tauxParticipation']);
        Route::get('progression', [LegislativeDashboardController::class, 'progression']);
        Route::get('incidents', [LegislativeDashboardController::class, 'incidents']);
        Route::get('audit', [LegislativeDashboardController::class, 'audit']);
        Route::get('historique', [LegislativeDashboardController::class, 'historique']);
        Route::get('cartographie', [LegislativeDashboardController::class, 'cartographie']);
        Route::get('circonscriptions', [LegislativeDashboardController::class, 'circonscriptions']);
        Route::get('circonscriptions/{id}', [LegislativeDashboardController::class, 'circonscription']);
        Route::get('repartition-sieges', [LegislativeDashboardController::class, 'repartitionSieges']);
        Route::get('results', [LegislativeDashboardController::class, 'resultats']); // Alias
    });

    // ═══════════════════════════════════════════════════════════════
    // DASHBOARD COMMUNALES
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('communale')->group(function () {

        Route::get('/', [CommunaleDashboardController::class, 'stats'])->name('dashboard.communale.stats');
        Route::get('stats', [CommunaleDashboardController::class, 'stats']);
        Route::get('resultats', [CommunaleDashboardController::class, 'resultats']);
        
        Route::get('participation', [CommunaleDashboardController::class, 'participation'])->name('dashboard.communale.participation');
        Route::get('taux-participation', [CommunaleDashboardController::class, 'participation']); // Alias
        
        Route::get('progression', [CommunaleDashboardController::class, 'progression']);
        Route::get('incidents', [CommunaleDashboardController::class, 'incidents']);
        Route::get('audit', [CommunaleDashboardController::class, 'audit']);
        Route::get('historique', [CommunaleDashboardController::class, 'historique']);
        Route::get('cartographie', [CommunaleDashboardController::class, 'cartographie']);
        Route::get('communes/{id}', [CommunaleDashboardController::class, 'resultatCommune']);
        Route::get('results', [CommunaleDashboardController::class, 'resultats']); // Alias
    });

    // ═══════════════════════════════════════════════════════════════
    // DASHBOARD PRÉSIDENTIELLE
    // ═══════════════════════════════════════════════════════════════
    Route::prefix('presidentielle')->group(function () {

        Route::get('/', [PresidentielleDashboardController::class, 'stats'])->name('dashboard.presidentielle.stats');
        Route::get('stats', [PresidentielleDashboardController::class, 'stats']);
        Route::get('resultats', [PresidentielleDashboardController::class, 'resultats']);
        
        Route::get('participation', [PresidentielleDashboardController::class, 'participation'])->name('dashboard.presidentielle.participation');
        Route::get('taux-participation', [PresidentielleDashboardController::class, 'participation']); // Alias
        
        Route::get('progression', [PresidentielleDashboardController::class, 'progression']);
        Route::get('incidents', [PresidentielleDashboardController::class, 'incidents']);
        Route::get('audit', [PresidentielleDashboardController::class, 'audit']);
        Route::get('historique', [PresidentielleDashboardController::class, 'historique']);
        Route::get('cartographie', [PresidentielleDashboardController::class, 'cartographie']);
        Route::get('departements/{id}', [PresidentielleDashboardController::class, 'resultatDepartement']);
        Route::get('results', [PresidentielleDashboardController::class, 'resultats']); // Alias
    });
});

/**
 * ═══════════════════════════════════════════════════════════════
 * NOUVEAUX ENDPOINTS KPIs LÉGISLATIVES (CENA 2026)
 * ═══════════════════════════════════════════════════════════════
 */

Route::prefix('dashboard')->middleware(['auth:sanctum', 'election.access'])->group(function () {

    Route::prefix('legislative')->group(function () {

        // ✅ KPIs NOUVEAUX (ajoutés pour CENA)
        Route::get('avancement', [LegislativeDashboardController::class, 'avancement'])->name('dashboard.legislative.avancement');
        Route::get('vitesse-saisie', [LegislativeDashboardController::class, 'vitesseSaisie'])->name('dashboard.legislative.vitesse');
        Route::get('vue-nationale', [LegislativeDashboardController::class, 'vueNationale'])->name('dashboard.legislative.vue-nationale');
        Route::get('vue-departement/{departementId}', [LegislativeDashboardController::class, 'vueDepartement'])->name('dashboard.legislative.vue-departement');
        Route::get('palmares-departements', [LegislativeDashboardController::class, 'palmaresDepartements'])->name('dashboard.legislative.palmares');
        Route::get('barometre-10', [LegislativeDashboardController::class, 'barometre10'])->name('dashboard.legislative.barometre-10');
        Route::get('matrice-20', [LegislativeDashboardController::class, 'matrice20'])->name('dashboard.legislative.matrice-20');
        Route::get('hemicycle', [LegislativeDashboardController::class, 'hemicycle'])->name('dashboard.legislative.hemicycle');
        Route::get('radar-divergence', [LegislativeDashboardController::class, 'radarDivergence'])->name('dashboard.legislative.radar');
        Route::get('flux-anomalies', [LegislativeDashboardController::class, 'fluxAnomalies'])->name('dashboard.legislative.anomalies');
    });
});