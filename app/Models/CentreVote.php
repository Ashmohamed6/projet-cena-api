<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modèle CentreVote
 * 
 * @property int $id
 * @property string|null $code
 * @property string $nom
 * @property int|null $village_quartier_id
 * @property int|null $arrondissement_id
 * @property int|null $commune_id
 * @property int|null $circonscription_id
 * @property int|null $departement_id
 * @property string|null $adresse
 * @property string|null $type_etablissement
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class CentreVote extends Model
{
    use HasFactory;

    protected $table = 'centres_vote';

    protected $fillable = [
        'code',
        'nom',
        'village_quartier_id',
        'arrondissement_id',
        'commune_id',
        'circonscription_id',
        'departement_id',
        'adresse',
        'type_etablissement',
    ];

    /**
     * Village/Quartier auquel appartient ce centre
     */
    public function villageQuartier(): BelongsTo
    {
        return $this->belongsTo(VillageQuartier::class, 'village_quartier_id');
    }

    /**
     * Arrondissement
     */
    public function arrondissement(): BelongsTo
    {
        return $this->belongsTo(Arrondissement::class, 'arrondissement_id');
    }

    /**
     * Commune
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'commune_id');
    }

    /**
     * Circonscription
     */
    public function circonscription(): BelongsTo
    {
        return $this->belongsTo(Circonscription::class, 'circonscription_id');
    }

    /**
     * Département
     */
    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class, 'departement_id');
    }

    /**
     * Postes de vote de ce centre
     */
    public function postes(): HasMany
    {
        return $this->hasMany(PosteVote::class, 'centre_vote_id');
    }

    /**
     * PVs de ce centre de vote (relation polymorphique)
     */
    public function procesVerbaux(): MorphMany
    {
        return $this->morphMany(ProcesVerbal::class, 'niveau');
    }

    /**
     * Lignes PV de ce centre
     */
    public function lignesPV()
    {
        return $this->hasMany(PVLigne::class, 'centre_vote_id');
    }
}