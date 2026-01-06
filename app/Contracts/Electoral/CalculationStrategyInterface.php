<?php

namespace App\Contracts\Electoral;

use App\Models\{Election, CirconscriptionElectorale};
use Illuminate\Support\Collection;

/**
 * Interface CalculationStrategyInterface
 * 
 * Définit le contrat pour tous les moteurs de calcul électoral.
 * Permet l'injection de différentes stratégies selon les besoins.
 * 
 * @package App\Contracts\Electoral
 */
interface CalculationStrategyInterface
{
    /**
     * Calculer la répartition des sièges pour une circonscription
     *
     * @param Election $election
     * @param CirconscriptionElectorale $circonscription
     * @param Collection $resultats Collection de résultats par candidature
     * @return array ['candidature_id' => ['sieges_obtenus' => int, 'details' => array]]
     */
    public function calculateSeats(
        Election $election,
        CirconscriptionElectorale $circonscription,
        Collection $resultats
    ): array;

    /**
     * Vérifier si une candidature atteint le seuil national
     *
     * @param int $candidatureId
     * @param array $resultatNational ['total_voix' => int, 'total_suffrages_exprimes' => int]
     * @return bool
     */
    public function passesNationalThreshold(int $candidatureId, array $resultatNational): bool;

    /**
     * Vérifier si une candidature atteint le seuil de circonscription
     *
     * @param int $candidatureId
     * @param int $circonscriptionId
     * @param array $resultatCirconscription ['total_voix' => int, 'total_suffrages_exprimes' => int]
     * @return bool
     */
    public function passesCirconscriptionThreshold(
        int $candidatureId,
        int $circonscriptionId,
        array $resultatCirconscription
    ): bool;

    /**
     * Calculer le quotient électoral
     *
     * @param int $totalSuffragesExprimes
     * @param int $nombreSieges
     * @return float
     */
    public function calculateQuotient(int $totalSuffragesExprimes, int $nombreSieges): float;

    /**
     * Répartir les sièges réservés aux femmes
     *
     * @param Election $election
     * @param array $repartitionInitiale Répartition avant application du quota femmes
     * @return array Répartition finale avec sièges femmes
     */
    public function applySiegesFemmesQuota(Election $election, array $repartitionInitiale): array;

    /**
     * Obtenir le nom de la stratégie
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Obtenir la version de la stratégie
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Obtenir les métadonnées de la stratégie (pour audit)
     *
     * @return array
     */
    public function getMetadata(): array;

    /**
     * Valider que la stratégie peut s'appliquer à cette élection
     *
     * @param Election $election
     * @return bool
     * @throws \Exception Si la stratégie n'est pas compatible
     */
    public function canApply(Election $election): bool;
}
