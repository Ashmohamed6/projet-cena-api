<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modèle PosteVote
 * 
 * @property int $id
 * @property string|null $code
 * @property string|null $nom
 * @property int|null $village_quartier_id
 * @property int|null $electeurs_inscrits
 * @property string|null $adresse
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $actif
 * @property int|null $centre_vote_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class PosteVote extends Model
{
    use HasFactory;

    protected $table = 'postes_vote';

    protected $fillable = [
        'code',
        'nom',
        'village_quartier_id',
        'electeurs_inscrits',
        'adresse',
        'latitude',
        'longitude',
        'actif',
        'centre_vote_id',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'electeurs_inscrits' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Centre de vote auquel appartient ce poste
     */
    public function centreVote(): BelongsTo
    {
        return $this->belongsTo(CentreVote::class, 'centre_vote_id');
    }

    /**
     * Village/Quartier
     */
    public function villageQuartier(): BelongsTo
    {
        return $this->belongsTo(VillageQuartier::class, 'village_quartier_id');
    }

    /**
     * PVs de ce poste de vote (relation polymorphique)
     */
    public function procesVerbaux(): MorphMany
    {
        return $this->morphMany(ProcesVerbal::class, 'niveau');
    }

    /**
     * Lignes PV de ce poste
     */
    public function lignesPV()
    {
        return $this->hasMany(PVLigne::class, 'poste_vote_id');
    }
}