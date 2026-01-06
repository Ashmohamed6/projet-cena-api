<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidature extends Model
{
    protected $table = 'candidatures';

    protected $fillable = [
        'code',
        'election_id',
        'entite_politique_id',
        'circonscription_id',
        'numero_liste',
        'tete_liste',
        'statut',
        'motif_rejet',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    // Relations
    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    public function entitePolitique()
    {
        return $this->belongsTo(EntitePolitique::class);
    }

    public function circonscription()
    {
        return $this->belongsTo(CirconscriptionElectorale::class, 'circonscription_id');
    }

    public function resultats()
    {
        return $this->hasMany(Resultat::class);
    }

    public function agregations()
    {
        return $this->hasMany(AgregationCalcul::class);
    }

    // Scopes
    public function scopeValidees($query)
    {
        return $query->where('statut', 'validee');
    }
}