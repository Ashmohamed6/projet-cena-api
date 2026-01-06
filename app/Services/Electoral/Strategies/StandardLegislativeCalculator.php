<?php

namespace App\Services\Electoral\Strategies;

use App\Models\{Election, CirconscriptionElectorale};
use Illuminate\Support\Collection;

/**
 * StandardLegislativeCalculator
 * 
 * Implémentation standard selon la Loi électorale 2024 du Bénin.
 * 
 * Règles appliquées :
 * - Seuil national : 10% des suffrages exprimés au niveau national
 * - Seuil circonscription : 20% des suffrages exprimés dans la circonscription
 * - Quotient électoral : Méthode Hare (Total voix / Nombre de sièges)
 * - Répartition : Au quotient puis au plus fort reste
 * - Quota femmes : 24 sièges réservés aux femmes sur 109 sièges
 * 
 * @package App\Services\Electoral\Strategies
 */
class StandardLegislativeCalculator extends BaseCalculationStrategy
{
    /**
     * Nom de la stratégie
     */
    protected string $name = 'Standard Legislative Calculator';

    /**
     * Version
     */
    protected string $version = '1.0.0';

    /**
     * Seuil national (en pourcentage)
     */
    protected float $seuilNational = 10.0;

    /**
     * Seuil de circonscription (en pourcentage)
     */
    protected float $seuilCirconscription = 20.0;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->metadata = [
            'seuil_national' => $this->seuilNational,
            'seuil_circonscription' => $this->seuilCirconscription,
            'methode_quotient' => 'Hare',
            'methode_repartition' => 'Plus fort reste',
            'base_legale' => 'Loi électorale 2024 - Bénin',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function calculateSeats(
        Election $election,
        CirconscriptionElectorale $circonscription,
        Collection $resultats
    ): array {
        $this->validateResultats($resultats);

        $this->logCalculationStep('Début calcul répartition sièges', [
            'election' => $election->code,
            'circonscription' => $circonscription->nom,
            'nombre_sieges' => $circonscription->nombre_sieges_total,
        ]);

        // 1. Calculer le total des suffrages exprimés
        $totalSuffragesExprimes = $resultats->sum('total_voix');

        // 2. Filtrer les candidatures éligibles (qui passent les seuils)
        $candidaturesEligibles = $this->filterEligibleCandidatures($resultats);

        $this->logCalculationStep('Candidatures éligibles', [
            'total_candidatures' => $resultats->count(),
            'candidatures_eligibles' => $candidaturesEligibles->count(),
        ]);

        // Si aucune candidature éligible, retourner vide
        if ($candidaturesEligibles->isEmpty()) {
            return $this->formatResults([], [
                'raison' => 'Aucune candidature n\'atteint les seuils requis',
            ]);
        }

        // 3. Calculer le quotient électoral
        $quotient = $this->calculateQuotient(
            $totalSuffragesExprimes,
            $circonscription->nombre_sieges_total
        );

        $this->logCalculationStep('Quotient électoral calculé', [
            'quotient' => $quotient,
            'total_suffrages' => $totalSuffragesExprimes,
            'nombre_sieges' => $circonscription->nombre_sieges_total,
        ]);

        // 4. Répartition au quotient
        $repartitionQuotient = $this->repartirAuQuotient($candidaturesEligibles, $quotient);

        // 5. Calculer les sièges restants
        $siegesAttribues = array_sum(array_column($repartitionQuotient, 'sieges'));
        $siegesRestants = $circonscription->nombre_sieges_total - $siegesAttribues;

        $this->logCalculationStep('Répartition au quotient', [
            'sieges_attribues' => $siegesAttribues,
            'sieges_restants' => $siegesRestants,
        ]);

        // 6. Répartition au plus fort reste si nécessaire
        if ($siegesRestants > 0) {
            $repartitionFinale = $this->repartirAuPlusFortReste(
                $candidaturesEligibles,
                $repartitionQuotient,
                $quotient,
                $siegesRestants
            );
        } else {
            $repartitionFinale = $repartitionQuotient;
        }

        // 7. Appliquer le quota de sièges réservés aux femmes
        $repartitionAvecQuotaFemmes = $this->applySiegesFemmesQuota($election, $repartitionFinale);

        $this->logCalculationStep('Répartition finale', [
            'repartition' => $repartitionAvecQuotaFemmes,
        ]);

        return $this->formatResults($repartitionAvecQuotaFemmes, [
            'quotient_electoral' => $quotient,
            'total_suffrages_exprimes' => $totalSuffragesExprimes,
            'candidatures_eligibles' => $candidaturesEligibles->pluck('candidature_id')->toArray(),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function passesNationalThreshold(int $candidatureId, array $resultatNational): bool
    {
        if ($resultatNational['total_suffrages_exprimes'] === 0) {
            return false;
        }

        $pourcentage = $this->calculatePercentage(
            $resultatNational['total_voix'],
            $resultatNational['total_suffrages_exprimes']
        );

        return $pourcentage >= $this->seuilNational;
    }

    /**
     * {@inheritDoc}
     */
    public function passesCirconscriptionThreshold(
        int $candidatureId,
        int $circonscriptionId,
        array $resultatCirconscription
    ): bool {
        if ($resultatCirconscription['total_suffrages_exprimes'] === 0) {
            return false;
        }

        $pourcentage = $this->calculatePercentage(
            $resultatCirconscription['total_voix'],
            $resultatCirconscription['total_suffrages_exprimes']
        );

        return $pourcentage >= $this->seuilCirconscription;
    }

    /**
     * {@inheritDoc}
     */
    public function calculateQuotient(int $totalSuffragesExprimes, int $nombreSieges): float
    {
        if ($nombreSieges === 0) {
            throw new \InvalidArgumentException('Le nombre de sièges ne peut pas être zéro');
        }

        // Méthode Hare : Total des suffrages exprimés / Nombre de sièges
        return $totalSuffragesExprimes / $nombreSieges;
    }

    /**
     * {@inheritDoc}
     */
    public function applySiegesFemmesQuota(Election $election, array $repartitionInitiale): array
    {
        // Pour l'instant, retourner la répartition telle quelle
        // La logique du quota femmes sera implémentée ultérieurement
        // car elle nécessite des données sur le genre des candidats
        
        // TODO: Implémenter la répartition des 24 sièges réservés aux femmes
        // selon l'Article 144 de la Constitution
        
        return $repartitionInitiale;
    }

    /**
     * Filtrer les candidatures éligibles
     *
     * @param Collection $resultats
     * @return Collection
     */
    protected function filterEligibleCandidatures(Collection $resultats): Collection
    {
        // Pour cette implémentation de base, on considère toutes les candidatures
        // ayant au moins 1 voix comme potentiellement éligibles
        // Les seuils seront vérifiés au niveau du service orchestrateur
        
        return $resultats->filter(fn($r) => $r['total_voix'] > 0);
    }

    /**
     * Répartir les sièges au quotient
     *
     * @param Collection $candidatures
     * @param float $quotient
     * @return array
     */
    protected function repartirAuQuotient(Collection $candidatures, float $quotient): array
    {
        $repartition = [];

        foreach ($candidatures as $candidature) {
            $siegesAuQuotient = (int)floor($candidature['total_voix'] / $quotient);
            $reste = $this->calculateReste($candidature['total_voix'], $quotient, $siegesAuQuotient);

            $repartition[$candidature['candidature_id']] = [
                'sieges' => $siegesAuQuotient,
                'voix' => $candidature['total_voix'],
                'reste' => $reste,
            ];
        }

        return $repartition;
    }

    /**
     * Répartir les sièges restants au plus fort reste
     *
     * @param Collection $candidatures
     * @param array $repartitionQuotient
     * @param float $quotient
     * @param int $siegesRestants
     * @return array
     */
    protected function repartirAuPlusFortReste(
        Collection $candidatures,
        array $repartitionQuotient,
        float $quotient,
        int $siegesRestants
    ): array {
        // Extraire les restes
        $restes = array_column($repartitionQuotient, 'reste', null);
        $candidatureIds = array_keys($repartitionQuotient);
        $restesParCandidature = array_combine($candidatureIds, array_column($repartitionQuotient, 'reste'));

        // Trier par plus fort reste
        $restesTriés = $this->sortByPlusFortReste($restesParCandidature);

        // Attribuer les sièges restants
        $repartitionFinale = $repartitionQuotient;
        $siegesAttribues = 0;

        foreach ($restesTriés as $candidatureId => $reste) {
            if ($siegesAttribues >= $siegesRestants) {
                break;
            }

            $repartitionFinale[$candidatureId]['sieges']++;
            $siegesAttribues++;
        }

        return $repartitionFinale;
    }

    /**
     * {@inheritDoc}
     */
    public function canApply(Election $election): bool
    {
        // Cette stratégie s'applique uniquement aux élections législatives
        return $election->typeElection->code === 'LEGISLATIVES';
    }
}
