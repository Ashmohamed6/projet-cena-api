<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Electoral\CalculationStrategyInterface;
use App\Services\Electoral\{CalculElectoralService};
use App\Services\Electoral\Strategies\{
    StandardLegislativeCalculator,
    CenaOfficialCalculator
};

/**
 * ElectoralServiceProvider
 * 
 * Service Provider pour l'injection des stratégies de calcul électoral.
 * 
 * Permet de configurer quelle stratégie utiliser via la configuration
 * sans modifier le code de l'application.
 * 
 * @package App\Providers
 */
class ElectoralServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Enregistrer les stratégies individuelles
        $this->app->bind('electoral.strategy.standard', function ($app) {
            return new StandardLegislativeCalculator();
        });

        $this->app->bind('electoral.strategy.cena_official', function ($app) {
            return new CenaOfficialCalculator();
        });

        // Enregistrer la stratégie par défaut selon la configuration
        $this->app->bind(CalculationStrategyInterface::class, function ($app) {
            $strategyName = config('electoral.default_strategy', 'standard');
            
            return match ($strategyName) {
                'cena_official' => $app->make('electoral.strategy.cena_official'),
                'standard' => $app->make('electoral.strategy.standard'),
                default => $app->make('electoral.strategy.standard'),
            };
        });

        // Enregistrer le service orchestrateur avec injection automatique
        $this->app->singleton(CalculElectoralService::class, function ($app) {
            $strategy = $app->make(CalculationStrategyInterface::class);
            return new CalculElectoralService($strategy);
        });

        // Alias pour faciliter l'utilisation
        $this->app->alias(CalculElectoralService::class, 'electoral.calculator');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publier la configuration
        $this->publishes([
            __DIR__.'/../../config/electoral.php' => config_path('electoral.php'),
        ], 'electoral-config');

        // Logger quelle stratégie est utilisée au démarrage
        if ($this->app->runningInConsole() || $this->app->runningUnitTests()) {
            $strategy = $this->app->make(CalculationStrategyInterface::class);
            \Log::channel('electoral')->info('Electoral calculation strategy loaded', [
                'strategy' => $strategy->getName(),
                'version' => $strategy->getVersion(),
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            CalculationStrategyInterface::class,
            CalculElectoralService::class,
            'electoral.calculator',
            'electoral.strategy.standard',
            'electoral.strategy.cena_official',
        ];
    }
}
