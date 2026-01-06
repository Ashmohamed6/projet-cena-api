<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modèle Departement
 * 
 * @property int $id
 * @property string|null $code
 * @property string $nom
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Departement extends Model
{
    use HasFactory;

    protected $table = 'departements';

    protected $fillable = [
        'code',
        'nom',
    ];

    /**
     * Communes de ce département
     */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class, 'departement_id');
    }

    /**
     * Circonscriptions de ce département
     */
    public function circonscriptions(): HasMany
    {
        return $this->hasMany(Circonscription::class, 'departement_id');
    }

    /**
     * Centres de vote de ce département
     */
    public function centresVote(): HasMany
    {
        return $this->hasMany(CentreVote::class, 'departement_id');
    }

    /**
     * PVs de ce département (relation polymorphique)
     */
    public function procesVerbaux(): MorphMany
    {
        return $this->morphMany(ProcesVerbal::class, 'niveau');
    }
}