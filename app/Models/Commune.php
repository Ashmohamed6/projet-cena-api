<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modèle Commune
 * 
 * @property int $id
 * @property string|null $code
 * @property string $nom
 * @property int|null $circonscription_id
 * @property int|null $departement_id
 * @property string|null $statut
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Commune extends Model
{
    use HasFactory;

    protected $table = 'communes';

    protected $fillable = [
        'code',
        'nom',
        'circonscription_id',
        'departement_id',
        'statut',
    ];

    /**
     * Département auquel appartient cette commune
     */
    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class, 'departement_id');
    }

    /**
     * Circonscription
     */
    public function circonscription(): BelongsTo
    {
        return $this->belongsTo(Circonscription::class, 'circonscription_id');
    }

    /**
     * Arrondissements de cette commune
     */
    public function arrondissements(): HasMany
    {
        return $this->hasMany(Arrondissement::class, 'commune_id');
    }

    /**
     * Centres de vote de cette commune
     */
    public function centresVote(): HasMany
    {
        return $this->hasMany(CentreVote::class, 'commune_id');
    }

    /**
     * PVs de cette commune (relation polymorphique)
     */
    public function procesVerbaux(): MorphMany
    {
        return $this->morphMany(ProcesVerbal::class, 'niveau');
    }
}