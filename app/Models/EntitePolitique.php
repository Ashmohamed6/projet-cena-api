<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntitePolitique extends Model
{
    protected $table = 'entites_politiques';

    protected $fillable = [
        'code',
        'nom',
        'sigle',
        'type',
        'logo',
        'couleur',
        'data',
        'actif',
    ];

    protected $casts = [
        'data' => 'array',
        'actif' => 'boolean',
    ];

    // Relations
    public function candidatures()
    {
        return $this->hasMany(Candidature::class);
    }

    // Scopes
    public function scopePartis($query)
    {
        return $query->where('type', 'parti');
    }

    public function scopeDuosPresidentiels($query)
    {
        return $query->where('type', 'duo_presidentiel');
    }
}