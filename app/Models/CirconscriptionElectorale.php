<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * CirconscriptionElectorale Model
 * 
 * Représente une circonscription électorale pour les élections législatives.
 * Une circonscription peut couvrir plusieurs communes/arrondissements
 * et se voit attribuer un nombre de sièges à pourvoir.
 */
class CirconscriptionElectorale extends Model
{
    /**
     * Table associée au modèle
     */
    protected $table = 'circonscriptions_electorales';

    /**
     * Attributs assignables en masse
     */
    protected $fillable = [
        'code',
        'nom',
        'numero',
        'departement_id',
        'nombre_sieges_total',
        'nombre_sieges_femmes',
        // nombre_sieges_homme est calculé automatiquement (colonne générée)
    ];

    /**
     * Attributs castés
     */
    protected $casts = [
        'numero' => 'integer',
        'departement_id' => 'integer',
        'nombre_sieges_total' => 'integer',
        'nombre_sieges_femmes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation : Une circonscription appartient à un département
     */
    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    /**
     * Relation : Une circonscription contient plusieurs arrondissements
     */
    public function arrondissements(): HasMany
    {
        return $this->hasMany(Arrondissement::class, 'circonscription_id');
    }

    /**
     * Relation : Une circonscription contient plusieurs communes
     */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class, 'circonscription_id');
    }

    /**
     * Relation : Une circonscription a plusieurs centres de vote
     */
    public function centresVote(): HasMany
    {
        return $this->hasMany(CentreVote::class, 'circonscription_id');
    }

    /**
     * Relation : Une circonscription a plusieurs candidatures
     */
    public function candidatures(): HasMany
    {
        return $this->hasMany(Candidature::class, 'circonscription_id');
    }

    /**
     * Relation : Une circonscription a plusieurs agrégations de calculs
     */
    public function agregationsCalculs(): HasMany
    {
        return $this->hasMany(AgregationCalcul::class, 'niveau_id')
            ->where('niveau', 'circonscription');
    }

    /**
     * ✅ AJOUTÉ : PVs de cette circonscription (relation polymorphique)
     */
    public function procesVerbaux(): MorphMany
    {
        return $this->morphMany(ProcesVerbal::class, 'niveau');
    }

    /**
     * Scope : Circonscriptions avec sièges
     */
    public function scopeAvecSieges($query)
    {
        return $query->where('nombre_sieges_total', '>', 0);
    }

    /**
     * Scope : Par département
     */
    public function scopeParDepartement($query, int $departementId)
    {
        return $query->where('departement_id', $departementId);
    }

    /**
     * Scope : Ordonner par numéro
     */
    public function scopeOrdreNumero($query)
    {
        return $query->orderBy('numero');
    }

    /**
     * Accessor : Nombre de sièges hommes
     * (calculé automatiquement en BDD mais accessible via attribut)
     */
    public function getNombreSiegesHommeAttribute(): int
    {
        return $this->nombre_sieges_total - $this->nombre_sieges_femmes;
    }

    /**
     * Accessor : Code formaté
     */
    public function getCodeFormate(): string
    {
        return sprintf('CIRC_%02d', $this->numero);
    }

    /**
     * Méthode helper : Vérifier si la circonscription a un quota femmes
     */
    public function hasQuotaFemmes(): bool
    {
        return $this->nombre_sieges_femmes > 0;
    }

    /**
     * Méthode helper : Obtenir le pourcentage de sièges femmes
     */
    public function getPourcentageSiegesFemmes(): float
    {
        if ($this->nombre_sieges_total === 0) {
            return 0;
        }

        return round(($this->nombre_sieges_femmes / $this->nombre_sieges_total) * 100, 2);
    }
}