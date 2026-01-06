<?php

namespace App\Services\Electoral;

use App\Contracts\Electoral\CalculationStrategyInterface;
use App\Models\{Election, CirconscriptionElectorale, AgregationCalcul};
use App\Services\Electoral\Strategies\StandardLegislativeCalculator;
use Illuminate\Support\Facades\{DB, Log};

/**
 * CalculElectoralService
 * 
 * Service orchestrateur pour tous les calculs électoraux.
 * Utilise le Design Pattern Strategy pour permettre l'injection
 * de différents moteurs de calcul.
 * 
 * @package App\Services\Electoral
 */
class CalculElectoralService
{
    /**
     * Stratégie de calcul injectée
     */
    private CalculationStrategyInterface $calculationStrategy;

    /**
     * Constructeur avec injection de dépendance
     *
     * @param CalculationStrategyInterface|null $calculationStrategy
     */
    public function __construct(?CalculationStrategyInterface $calculationStrategy = null)
    {
        // Si aucune stratégie fournie, utiliser la stratégie par défaut
        $this->calculationStrategy = $calculationStrategy ?? new StandardLegislativeCalculator();
    }

    /**
     * Définir la stratégie de calcul à utiliser
     * 
     * Permet de changer dynamiquement la stratégie
     *
     * @param CalculationStrategyInterface $strategy
     * @return self
     */
    public function setStrategy(CalculationStrategyInterface $strategy): self
    {
        $this->calculationStrategy = $strategy;
        return $this;
    }

    /**
     * Obtenir la stratégie courante
     *
     * @return CalculationStrategyInterface
     */
    public function getStrategy(): CalculationStrategyInterface
    {
        return $this->calculationStrategy;
    }

    /**
     * Calculer et sauvegarder la répartition des sièges pour une circonscription
     *
     * @param int $electionId
     * @param int $circonscriptionId
     * @return array
     * @throws \Exception
     */
    public function calculerRepartitionSieges(int $electionId, int $circonscriptionId): array
    {
        DB::beginTransaction();

        try {
            // 1. Charger l'élection et la circonscription
            $election = Election::with('typeElection')->findOrFail($electionId);
            $circonscription = CirconscriptionElectorale::findOrFail($circonscriptionId);

            // 2. Vérifier que la stratégie peut s'appliquer
            if (!$this->calculationStrategy->canApply($election)) {
                throw new \Exception(
                    "La stratégie {$this->calculationStrategy->getName()} " .
                    "ne peut pas s'appliquer à cette élection"
                );
            }

            Log::info('Début calcul répartition sièges', [
                'election' => $election->code,
                'circonscription' => $circonscription->nom,
                'strategy' => $this->calculationStrategy->getName(),
            ]);

            // 3. Récupérer les résultats agrégés de la circonscription
            $resultats = $this->getResultatsCirconscription($electionId, $circonscriptionId);

            // 4. Appliquer la stratégie de calcul
            $resultatCalcul = $this->calculationStrategy->calculateSeats(
                $election,
                $circonscription,
                $resultats
            );

            // 5. Sauvegarder les résultats en base
            $this->sauvegarderRepartition(
                $election,
                $circonscription,
                $resultatCalcul['repartition'],
                $resultatCalcul['details']
            );

            DB::commit();

            Log::info('Calcul répartition terminé avec succès', [
                'election' => $election->code,
                'circonscription' => $circonscription->nom,
            ]);

            return [
                'success' => true,
                'election' => $election->code,
                'circonscription' => $circonscription->nom,
                'repartition' => $resultatCalcul['repartition'],
                'details' => $resultatCalcul['details'],
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur calcul répartition', [
                'election_id' => $electionId,
                'circonscription_id' => $circonscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculer la répartition pour toutes les circonscriptions d'une élection
     *
     * @param int $electionId
     * @return array
     */
    public function calculerRepartitionNationale(int $electionId): array
    {
        $election = Election::with('typeElection')->findOrFail($electionId);
        $circonscriptions = CirconscriptionElectorale::where('departement_id', '>', 0)->get();

        $resultats = [];
        $errors = [];

        foreach ($circonscriptions as $circonscription) {
            try {
                $resultat = $this->calculerRepartitionSieges($electionId, $circonscription->id);
                $resultats[$circonscription->id] = $resultat;
            } catch (\Exception $e) {
                $errors[$circonscription->id] = [
                    'circonscription' => $circonscription->nom,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => empty($errors),
            'resultats' => $resultats,
            'errors' => $errors,
            'total_circonscriptions' => $circonscriptions->count(),
            'circonscriptions_calculees' => count($resultats),
            'circonscriptions_en_erreur' => count($errors),
        ];
    }

    /**
     * Vérifier si une candidature passe les seuils requis
     *
     * @param int $candidatureId
     * @param int $electionId
     * @return array
     */
    public function verifierSeuils(int $candidatureId, int $electionId): array
    {
        // Récupérer les résultats nationaux
        $resultatsNational = $this->getResultatsNationaux($electionId, $candidatureId);
        
        // Récupérer les résultats par circonscription
        $resultatsCirconscriptions = $this->getResultatsParCirconscription($electionId, $candidatureId);

        $passesNational = $this->calculationStrategy->passesNationalThreshold(
            $candidatureId,
            $resultatsNational
        );

        $passesCirconscriptions = [];
        foreach ($resultatsCirconscriptions as $circonscriptionId => $resultats) {
            $passesCirconscriptions[$circonscriptionId] = $this->calculationStrategy->passesCirconscriptionThreshold(
                $candidatureId,
                $circonscriptionId,
                $resultats
            );
        }

        return [
            'candidature_id' => $candidatureId,
            'passe_seuil_national' => $passesNational,
            'resultats_national' => $resultatsNational,
            'passe_seuil_circonscriptions' => $passesCirconscriptions,
            'resultats_circonscriptions' => $resultatsCirconscriptions,
        ];
    }

    /**
     * Récupérer les résultats agrégés d'une circonscription
     *
     * @param int $electionId
     * @param int $circonscriptionId
     * @return \Illuminate\Support\Collection
     */
    protected function getResultatsCirconscription(int $electionId, int $circonscriptionId)
    {
        return AgregationCalcul::where('election_id', $electionId)
            ->where('niveau', 'circonscription')
            ->where('niveau_id', $circonscriptionId)
            ->with('candidature')
            ->get()
            ->map(function ($agregation) {
                return [
                    'candidature_id' => $agregation->candidature_id,
                    'total_voix' => $agregation->total_voix,
                    'total_suffrages_exprimes' => $agregation->total_suffrages_exprimes,
                ];
            });
    }

    /**
     * Récupérer les résultats nationaux d'une candidature
     *
     * @param int $electionId
     * @param int $candidatureId
     * @return array
     */
    protected function getResultatsNationaux(int $electionId, int $candidatureId): array
    {
        $agregation = AgregationCalcul::where('election_id', $electionId)
            ->where('candidature_id', $candidatureId)
            ->where('niveau', 'national')
            ->first();

        if (!$agregation) {
            return [
                'total_voix' => 0,
                'total_suffrages_exprimes' => 0,
            ];
        }

        return [
            'total_voix' => $agregation->total_voix,
            'total_suffrages_exprimes' => $agregation->total_suffrages_exprimes,
        ];
    }

    /**
     * Récupérer les résultats par circonscription d'une candidature
     *
     * @param int $electionId
     * @param int $candidatureId
     * @return array
     */
    protected function getResultatsParCirconscription(int $electionId, int $candidatureId): array
    {
        $agregations = AgregationCalcul::where('election_id', $electionId)
            ->where('candidature_id', $candidatureId)
            ->where('niveau', 'circonscription')
            ->get();

        $resultats = [];
        foreach ($agregations as $agregation) {
            $resultats[$agregation->niveau_id] = [
                'total_voix' => $agregation->total_voix,
                'total_suffrages_exprimes' => $agregation->total_suffrages_exprimes,
            ];
        }

        return $resultats;
    }

    /**
     * Sauvegarder la répartition calculée
     *
     * @param Election $election
     * @param CirconscriptionElectorale $circonscription
     * @param array $repartition
     * @param array $details
     */
    protected function sauvegarderRepartition(
        Election $election,
        CirconscriptionElectorale $circonscription,
        array $repartition,
        array $details
    ): void {
        foreach ($repartition as $candidatureId => $data) {
            AgregationCalcul::updateOrCreate(
                [
                    'election_id' => $election->id,
                    'candidature_id' => $candidatureId,
                    'niveau' => 'circonscription',
                    'niveau_id' => $circonscription->id,
                ],
                [
                    'sieges_obtenus' => $data['sieges'],
                    'total_voix' => $data['voix'],
                    'metadata' => array_merge($details, [
                        'reste' => $data['reste'] ?? null,
                    ]),
                    'statut' => 'provisoire',
                    'calcule_a' => now(),
                ]
            );
        }
    }

    /**
     * Obtenir les informations sur la stratégie utilisée
     *
     * @return array
     */
    public function getStrategyInfo(): array
    {
        return $this->calculationStrategy->getMetadata();
    }
}
