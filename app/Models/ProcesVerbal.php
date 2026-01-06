<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle ProcesVerbal
 * 
 * Représente un procès-verbal de dépouillement électoral.
 * 
 * @property int $id
 * @property string $code
 * @property int $election_id
 * @property string $niveau
 * @property int $niveau_id
 * @property string|null $coordonnateur
 * @property \Carbon\Carbon|null $date_compilation
 * @property string $statut
 * @property string|null $numero_pv
 * @property string|null $fichier_scan
 * @property string|null $checksum
 * @property int|null $nombre_inscrits
 * @property int|null $nombre_votants
 * @property int|null $nombre_bulletins_nuls
 * @property int|null $nombre_suffrages_exprimes
 * @property string|null $observations
 * @property int|null $saisi_par_user_id
 * @property int|null $valide_par_user_id
 * @property \Carbon\Carbon|null $date_validation
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * 
 * @property-read Election $election
 * @property-read User|null $saisiPar
 * @property-read User|null $validePar
 * @property-read \Illuminate\Database\Eloquent\Collection|PVLigne[] $lignes
 * @property-read \Illuminate\Database\Eloquent\Collection|Resultat[] $resultats
 * @property-read \Illuminate\Database\Eloquent\Collection|SignaturePV[] $signatures
 */
class ProcesVerbal extends Model
{
    use HasFactory;

    protected $table = 'proces_verbaux';

    protected $fillable = [
        'code',
        'election_id',
        'niveau',
        'niveau_id',
        'coordonnateur',              // ← NOUVEAU
        'date_compilation',            // ← NOUVEAU
        'numero_pv',
        'statut',
        'fichier_scan',
        'checksum',
        'nombre_inscrits',
        'nombre_votants',
        'nombre_bulletins_nuls',
        'nombre_suffrages_exprimes',
        'observations',
        'saisi_par_user_id',
        'valide_par_user_id',
        'date_validation',
    ];

    protected $casts = [
        'nombre_inscrits' => 'integer',
        'nombre_votants' => 'integer',
        'nombre_bulletins_nuls' => 'integer',
        'nombre_suffrages_exprimes' => 'integer',
        'date_compilation' => 'datetime',     // ← NOUVEAU
        'date_validation' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'statut' => 'brouillon',
        'nombre_bulletins_nuls' => 0,
    ];

    /**
     * Accessors ajoutés automatiquement au tableau/JSON
     */
    protected $appends = [
        'taux_participation',
        'est_coherent',
    ];

    // ==================== RELATIONS ====================

    /**
     * Élection concernée
     */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /**
     * Utilisateur ayant saisi le PV
     */
    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par_user_id');
    }

    /**
     * Utilisateur ayant validé le PV
     */
    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_user_id');
    }

    /**
     * Résultats globaux (totaux par candidature)
     */
    public function resultats(): HasMany
    {
        return $this->hasMany(Resultat::class);
    }

    /**
     * Lignes du tableau PV (villages ou postes) - NOUVEAU
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(PVLigne::class, 'proces_verbal_id')->orderBy('ordre');
    }

    /**
     * Signatures des délégués et autres signataires - NOUVEAU
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(SignaturePV::class, 'proces_verbal_id');
    }

    /**
     * Relation polymorphique vers l'entité géographique
     * (Arrondissement, Commune, VillageQuartier, PosteVote, etc.)
     */
    public function niveau()
    {
        $relations = [
            'bureau' => PosteVote::class,
            'arrondissement' => Arrondissement::class,
            'commune' => Commune::class,
            'village_quartier' => VillageQuartier::class,  // ← NOUVEAU
            'circonscription' => CirconscriptionElectorale::class,
        ];

        $class = $relations[$this->niveau] ?? null;
        
        return $class ? $class::find($this->niveau_id) : null;
    }

    // ==================== SCOPES ====================

    /**
     * Scope : Filtrer par statut
     */
    public function scopeStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Scope : PV en brouillon - NOUVEAU
     */
    public function scopeBrouillons($query)
    {
        return $query->where('statut', 'brouillon');
    }

    /**
     * Scope : PV validés
     */
    public function scopeValides($query)
    {
        return $query->where('statut', 'valide');
    }

    /**
     * Scope : PV litigieux
     */
    public function scopeLitigieux($query)
    {
        return $query->where('statut', 'litigieux');
    }

    /**
     * Scope : Filtrer par niveau géographique
     */
    public function scopeParNiveau($query, $niveau)
    {
        return $query->where('niveau', $niveau);
    }

    /**
     * Scope : Filtrer par élection - NOUVEAU
     */
    public function scopePourElection($query, int $electionId)
    {
        return $query->where('election_id', $electionId);
    }

    /**
     * Scope : PV récents - NOUVEAU
     */
    public function scopeRecents($query, int $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // ==================== ACCESSORS ====================

    /**
     * Calculer le taux de participation - NOUVEAU
     */
    public function getTauxParticipationAttribute(): float
    {
        if (!$this->nombre_inscrits || $this->nombre_inscrits === 0) {
            return 0.0;
        }

        return round(($this->nombre_votants / $this->nombre_inscrits) * 100, 2);
    }

    /**
     * Vérifier si le PV est cohérent mathématiquement - NOUVEAU
     */
    public function getEstCoherentAttribute(): bool
    {
        $erreurs = $this->verifierCoherence();
        return count($erreurs) === 0;
    }

    /**
     * Obtenir le nom complet de la localisation - NOUVEAU
     */
    public function getLocalisationCompleteAttribute(): string
    {
        $localisation = [];

        switch ($this->niveau) {
            case 'bureau':
                $poste = PosteVote::find($this->niveau_id);
                if ($poste) {
                    $localisation[] = $poste->nom;
                    if ($poste->centreVote) {
                        $localisation[] = $poste->centreVote->nom;
                    }
                }
                break;

            case 'village_quartier':
                $village = VillageQuartier::find($this->niveau_id);
                if ($village) {
                    $localisation[] = $village->nom;
                    if ($village->arrondissement) {
                        $localisation[] = $village->arrondissement->nom;
                    }
                }
                break;

            case 'arrondissement':
                $arrondissement = Arrondissement::find($this->niveau_id);
                if ($arrondissement) {
                    $localisation[] = $arrondissement->nom;
                    if ($arrondissement->commune) {
                        $localisation[] = $arrondissement->commune->nom;
                    }
                }
                break;

            case 'commune':
                $commune = Commune::find($this->niveau_id);
                if ($commune) {
                    $localisation[] = $commune->nom;
                    if ($commune->departement) {
                        $localisation[] = $commune->departement->nom;
                    }
                }
                break;
        }

        return implode(' - ', $localisation);
    }

    /**
     * Obtenir les signatures des délégués uniquement - NOUVEAU
     */
    public function getDeleguesAttribute()
    {
        return $this->signatures()
            ->where('type_signataire', 'delegue_parti')
            ->orderBy('ordre')
            ->get();
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Vérifier la cohérence mathématique du PV - NOUVEAU
     */
    public function verifierCoherence(): array
    {
        $erreurs = [];

        // Vérification 1: Votants <= Inscrits
        if ($this->nombre_votants && $this->nombre_inscrits && $this->nombre_votants > $this->nombre_inscrits) {
            $erreurs[] = 'Le nombre de votants dépasse le nombre d\'inscrits';
        }

        // Vérification 2: Suffrages exprimés + Bulletins nuls = Votants
        if ($this->nombre_votants && $this->nombre_suffrages_exprimes !== null && $this->nombre_bulletins_nuls !== null) {
            $somme = $this->nombre_suffrages_exprimes + $this->nombre_bulletins_nuls;
            if ($somme != $this->nombre_votants) {
                $erreurs[] = "La somme des suffrages exprimés et bulletins nuls ne correspond pas au nombre de votants";
            }
        }

        // Vérification 3: Suffrages exprimés = Total voix
        $totalVoix = $this->resultats()->sum('nombre_voix');
        if ($this->nombre_suffrages_exprimes && $this->nombre_suffrages_exprimes != $totalVoix) {
            $erreurs[] = "Les suffrages exprimés ne correspondent pas au total des voix";
        }

        // Vérification 4: Suffrages exprimés <= Votants
        if ($this->nombre_suffrages_exprimes && $this->nombre_votants && $this->nombre_suffrages_exprimes > $this->nombre_votants) {
            $erreurs[] = "Les suffrages exprimés dépassent le nombre de votants";
        }

        return $erreurs;
    }

    /**
     * Calculer automatiquement les totaux depuis les lignes détaillées - NOUVEAU
     */
    public function calculerTotaux(): void
    {
        // Calculer depuis les lignes
        $totalBulletinsNuls = $this->lignes()->sum('bulletins_nuls');
        
        // Calculer total voix depuis les résultats des lignes
        $totalVoix = PVLigneResultat::whereHas('ligne', function ($q) {
            $q->where('proces_verbal_id', $this->id);
        })->sum('nombre_voix');

        $this->update([
            'nombre_bulletins_nuls' => $totalBulletinsNuls,
            'nombre_suffrages_exprimes' => $totalVoix,
            'nombre_votants' => $totalVoix + $totalBulletinsNuls,
        ]);

        // Mettre à jour les résultats globaux
        $this->mettreAJourResultatsGlobaux();
    }

    /**
     * Mettre à jour les résultats globaux depuis les lignes détaillées - NOUVEAU
     */
    public function mettreAJourResultatsGlobaux(): void
    {
        // Agréger les résultats par candidature
        $resultatsParCandidature = PVLigneResultat::query()
            ->whereHas('ligne', function ($q) {
                $q->where('proces_verbal_id', $this->id);
            })
            ->selectRaw('candidature_id, SUM(nombre_voix) as total_voix')
            ->groupBy('candidature_id')
            ->get();

        foreach ($resultatsParCandidature as $resultat) {
            $this->resultats()->updateOrCreate(
                ['candidature_id' => $resultat->candidature_id],
                ['nombre_voix' => $resultat->total_voix]
            );
        }
    }

    /**
     * Valider le PV - NOUVEAU
     */
    public function valider(?int $validePar = null): bool
    {
        // Vérifier la cohérence avant validation
        if (!$this->est_coherent) {
            return false;
        }

        $this->update([
            'statut' => 'valide',
            'date_validation' => now(),
            'valide_par_user_id' => $validePar ?? auth()->id(),
        ]);

        return true;
    }

    /**
     * Marquer comme litigieux - NOUVEAU
     */
    public function marquerLitigieux(string $motif): void
    {
        $this->update([
            'statut' => 'litigieux',
            'observations' => $motif,
        ]);
    }
}