<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Election extends Model
{
    protected $table = 'elections';

    protected $fillable = [
        'code',
        'nom',
        'type_election_id',
        'date_scrutin',
        'statut',
        'description',
    ];

    protected $casts = [
        'date_scrutin' => 'date',
    ];

    // Relations
    public function typeElection()
    {
        return $this->belongsTo(TypeElection::class, 'type_election_id');
    }

    public function candidatures()
    {
        return $this->hasMany(Candidature::class);
    }

    public function procesVerbaux()
    {
        return $this->hasMany(ProcesVerbal::class);
    }

    public function agregations()
    {
        return $this->hasMany(AgregationCalcul::class);
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }

    // Scopes
    public function scopeEnCours($query)
    {
        return $query->where('statut', 'en_cours');
    }

    public function scopePubliees($query)
    {
        return $query->where('statut', 'publie');
    }
}