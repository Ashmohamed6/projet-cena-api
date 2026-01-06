<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modèle VillageQuartier
 * 
 * @property int $id
 * @property string|null $code
 * @property string $nom
 * @property int|null $arrondissement_id
 * @property string|null $type_entite
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class VillageQuartier extends Model
{
    use HasFactory;

    protected $table = 'villages_quartiers';

    protected $fillable = [
        'code',
        'nom',
        'arrondissement_id',
        'type_entite',
    ];

    /**
     * Arrondissement auquel appartient ce village/quartier
     */
    public function arrondissement(): BelongsTo
    {
        return $this->belongsTo(Arrondissement::class, 'arrondissement_id');
    }

    /**
     * Centres de vote de ce village/quartier
     */
    public function centresVote(): HasMany
    {
        return $this->hasMany(CentreVote::class, 'village_quartier_id');
    }

    /**
     * Postes de vote de ce village/quartier
     */
    public function postesVote(): HasMany
    {
        return $this->hasMany(PosteVote::class, 'village_quartier_id');
    }

    /**
     * PVs de ce village/quartier (relation polymorphique)
     */
    public function procesVerbaux(): MorphMany
    {
        return $this->morphMany(ProcesVerbal::class, 'niveau');
    }

    /**
     * Lignes PV de ce village/quartier
     */
    public function lignesPV()
    {
        return $this->hasMany(PVLigne::class, 'village_quartier_id');
    }
}