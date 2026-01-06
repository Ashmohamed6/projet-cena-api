<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle PVLigneResultat
 * 
 * Représente les votes d'une candidature pour une ligne spécifique du PV.
 * 
 * @property int $id
 * @property int $pv_ligne_id
 * @property int $candidature_id
 * @property int $nombre_voix
 * @property int $version
 * @property int|null $operateur_user_id
 * @property \Carbon\Carbon|null $date_saisie
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * 
 * @property-read PVLigne $ligne
 * @property-read Candidature $candidature
 * @property-read User|null $operateur
 */
class PVLigneResultat extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pv_ligne_resultats';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'pv_ligne_id',
        'candidature_id',
        'nombre_voix',
        'version',
        'operateur_user_id',
        'date_saisie',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'nombre_voix' => 'integer',
        'version' => 'integer',
        'date_saisie' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'nombre_voix' => 0,
        'version' => 1,
    ];

    /**
     * Boot method to auto-increment version on update
     */
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($resultat) {
            $resultat->version++;
            $resultat->date_saisie = now();
        });

        static::creating(function ($resultat) {
            if (!$resultat->date_saisie) {
                $resultat->date_saisie = now();
            }
        });
    }

    // ==================== RELATIONS ====================

    /**
     * Ligne du PV parent
     */
    public function ligne(): BelongsTo
    {
        return $this->belongsTo(PVLigne::class, 'pv_ligne_id');
    }

    /**
     * Candidature concernée
     */
    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class, 'candidature_id');
    }

    /**
     * Opérateur ayant saisi ce résultat
     */
    public function operateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operateur_user_id');
    }

    // NOTE: Pour accéder au ProcesVerbal, utiliser : $resultat->ligne->procesVerbal

    // ==================== SCOPES ====================

    /**
     * Scope : Résultats pour une candidature spécifique
     */
    public function scopePourCandidature($query, int $candidatureId)
    {
        return $query->where('candidature_id', $candidatureId);
    }

    /**
     * Scope : Résultats pour un procès-verbal spécifique
     */
    public function scopePourPV($query, int $procesVerbalId)
    {
        return $query->whereHas('ligne', function ($q) use ($procesVerbalId) {
            $q->where('proces_verbal_id', $procesVerbalId);
        });
    }

    /**
     * Scope : Trier par nombre de voix (décroissant)
     */
    public function scopeOrdered($query)
    {
        return $query->orderByDesc('nombre_voix');
    }

    // ==================== ACCESSORS ====================

    /**
     * Obtenir le nom de l'entité politique
     */
    public function getEntitePolitiqueNomAttribute(): ?string
    {
        return $this->candidature?->entitePolitique?->nom;
    }

    /**
     * Obtenir le sigle de l'entité politique
     */
    public function getEntitePolitiqueSigleAttribute(): ?string
    {
        return $this->candidature?->entitePolitique?->sigle;
    }

    /**
     * Obtenir le numéro de liste
     */
    public function getNumeroListeAttribute(): ?int
    {
        return $this->candidature?->numero_liste;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Vérifier si le résultat a été modifié après création
     */
    public function aEteModifie(): bool
    {
        return $this->version > 1;
    }

    /**
     * Obtenir l'historique des modifications
     * (nécessite une table d'audit séparée si implémentée)
     */
    public function getHistoriqueModifications(): array
    {
        // TODO: Implémenter avec package d'audit si nécessaire
        return [
            'version_actuelle' => $this->version,
            'derniere_modification' => $this->date_saisie,
            'operateur' => $this->operateur?->name,
        ];
    }

    /**
     * Calculer le pourcentage par rapport au total de la ligne
     */
    public function getPourcentageLigne(): float
    {
        $totalVoix = $this->ligne->total_voix;
        
        if ($totalVoix === 0) {
            return 0.0;
        }

        return round(($this->nombre_voix / $totalVoix) * 100, 2);
    }
}