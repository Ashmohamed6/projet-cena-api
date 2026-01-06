<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modèle Arrondissement
 * 
 * @property int $id
 * @property string|null $code
 * @property string $nom
 * @property int|null $commune_id
 * @property int|null $circonscription_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Arrondissement extends Model
{
    use HasFactory;

    protected $table = 'arrondissements';

    protected $fillable = [
        'code',
        'nom',
        'commune_id',
        'circonscription_id',
    ];

    /**
     * Commune à laquelle appartient cet arrondissement
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
     * Villages et quartiers de cet arrondissement
     */
    public function villagesQuartiers(): HasMany
    {
        return $this->hasMany(VillageQuartier::class, 'arrondissement_id');
    }

    /**
     * Centres de vote de cet arrondissement
     */
    public function centresVote(): HasMany
    {
        return $this->hasMany(CentreVote::class, 'arrondissement_id');
    }

    /**
     * PVs de cet arrondissement (relation polymorphique)
     */
    public function procesVerbaux(): MorphMany
    {
        return $this->morphMany(ProcesVerbal::class, 'niveau');
    }
}