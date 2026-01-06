<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Coordonnateur
 * 
 * Représente un coordonnateur d'arrondissement pour les élections
 * 
 * @property int $id
 * @property string $nom
 * @property string|null $telephone
 * @property string|null $email
 * @property bool $actif
 * @property int|null $arrondissement_id
 * @property string|null $arrondissement_zone
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Coordonnateur extends Model
{
    use HasFactory;

    /**
     * Table associée au modèle
     *
     * @var string
     */
    protected $table = 'coordonnateurs';

    /**
     * Attributs mass assignable
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nom',
        'telephone',
        'email',
        'actif',
        'arrondissement_id',
        'arrondissement_zone',
    ];

    /**
     * Attributs castés
     *
     * @var array<string, string>
     */
    protected $casts = [
        'actif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation avec l'arrondissement
     */
    public function arrondissement()
    {
        return $this->belongsTo(Arrondissement::class, 'arrondissement_id');
    }

    /**
     * Relation avec les PV
     */
    public function procesVerbaux()
    {
        return $this->hasMany(ProcesVerbal::class, 'coordonnateur', 'nom');
    }

    /**
     * Scope pour les coordonnateurs actifs
     */
    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope pour filtrer par arrondissement
     */
    public function scopeParArrondissement($query, int $arrondissementId)
    {
        return $query->where('arrondissement_id', $arrondissementId);
    }
}