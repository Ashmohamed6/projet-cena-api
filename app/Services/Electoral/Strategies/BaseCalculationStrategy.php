<?php

namespace App\Services\Electoral\Strategies;

use App\Contracts\Electoral\CalculationStrategyInterface;
use App\Models\{Election, CirconscriptionElectorale, Candidature};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Classe abstraite BaseCalculationStrategy
 * 
 * Fournit des méthodes communes à toutes les stratégies de calcul.
 * Les classes concrètes héritent de cette base et implémentent
 * les méthodes spécifiques.
 * 
 * @package App\Services\Electoral\Strategies
 */
abstract class BaseCalculationStrategy implements CalculationStrategyInterface
{
    /**
     * Version de la stratégie
     */
    protected string $version = '1.0.0';

    /**
     * Nom de la stratégie
     */
    protected string $name;

    /**
     * Métadonnées
     */
    protected array $metadata = [];

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name ?? class_basename(static::class);
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(): array
    {
        return array_merge([
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'class' => static::class,
            'timestamp' => now()->toIso8601String(),
        ], $this->metadata);
    }

    /**
     * {@inheritDoc}
     */
    public function canApply(Election $election): bool
    {
        // Par défaut, applicable à toutes les élections
        // Les classes concrètes peuvent surcharger
        return true;
    }

    /**
     * Logger les étapes de calcul (pour audit et debug)
     *
     * @param string $step
     * @param array $data
     */
    protected function logCalculationStep(string $step, array $data): void
    {
        Log::channel('electoral')->info("[{$this->getName()}] $step", $data);
    }

    /**
     * Récupérer les candidatures éligibles (qui ont passé les seuils)
     *
     * @param Collection $candidatures
     * @param array $resultatsNationaux
     * @param array $resultatsCirconscription
     * @return Collection
     */
    protected function getEligibleCandidatures(
        Collection $candidatures,
        array $resultatsNationaux,
        array $resultatsCirconscription
    ): Collection {
        return $candidatures->filter(function ($candidature) use ($resultatsNationaux, $resultatsCirconscription) {
            $passesNational = $this->passesNationalThreshold(
                $candidature->id,
                $resultatsNationaux[$candidature->id] ?? ['total_voix' => 0, 'total_suffrages_exprimes' => 0]
            );

            $passesCirconscription = $this->passesCirconscriptionThreshold(
                $candidature->id,
                $candidature->circonscription_id,
                $resultatsCirconscription[$candidature->id] ?? ['total_voix' => 0, 'total_suffrages_exprimes' => 0]
            );

            return $passesNational && $passesCirconscription;
        });
    }

    /**
     * Calculer les voix restantes après attribution au quotient
     *
     * @param int $totalVoix
     * @param float $quotient
     * @param int $siegesAttribues
     * @return int
     */
    protected function calculateReste(int $totalVoix, float $quotient, int $siegesAttribues): int
    {
        return $totalVoix - ($siegesAttribues * (int)$quotient);
    }

    /**
     * Valider les données de résultats
     *
     * @param Collection $resultats
     * @throws \InvalidArgumentException
     */
    protected function validateResultats(Collection $resultats): void
    {
        if ($resultats->isEmpty()) {
            throw new \InvalidArgumentException('Les résultats ne peuvent pas être vides');
        }

        // Vérifier que chaque résultat a les champs requis
        $resultats->each(function ($resultat) {
            if (!isset($resultat['candidature_id']) || !isset($resultat['total_voix'])) {
                throw new \InvalidArgumentException('Chaque résultat doit avoir candidature_id et total_voix');
            }
        });
    }

    /**
     * Formater les résultats de calcul pour cohérence
     *
     * @param array $repartition
     * @param array $details
     * @return array
     */
    protected function formatResults(array $repartition, array $details = []): array
    {
        return [
            'repartition' => $repartition,
            'details' => array_merge([
                'strategy' => $this->getName(),
                'version' => $this->getVersion(),
                'calculated_at' => now()->toIso8601String(),
            ], $details),
        ];
    }

    /**
     * Calculer le pourcentage
     *
     * @param int $voix
     * @param int $total
     * @return float
     */
    protected function calculatePercentage(int $voix, int $total): float
    {
        if ($total === 0) {
            return 0;
        }

        return round(($voix / $total) * 100, 2);
    }

    /**
     * Trier les candidatures par plus fort reste (décroissant)
     *
     * @param array $candidaturesAvecReste [candidature_id => reste]
     * @return array
     */
    protected function sortByPlusFortReste(array $candidaturesAvecReste): array
    {
        arsort($candidaturesAvecReste);
        return $candidaturesAvecReste;
    }
}
