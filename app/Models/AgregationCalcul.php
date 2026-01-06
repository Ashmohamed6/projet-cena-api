<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgregationCalcul extends Model
{
    protected $table = 'agregations_calculs';

    protected $fillable = [
        'election_id',
        'candidature_id',
        'niveau',
        'niveau_id',
        'total_voix',
        'total_inscrits',
        'total_votants',
        'total_bulletins_nuls',
        'total_suffrages_exprimes',
        'pourcentage_inscrits',
        'pourcentage_exprimes',
        'sieges_obtenus',
        'rang',
        'statut',
        'calcule_a',
        'metadata',
    ];

    protected $casts = [
        'pourcentage_inscrits' => 'decimal:2',
        'pourcentage_exprimes' => 'decimal:2',
        'metadata' => 'array',
        'calcule_a' => 'datetime',
    ];

    // Relations
    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    public function candidature()
    {
        return $this->belongsTo(Candidature::class);
    }
}