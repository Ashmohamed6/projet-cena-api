<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observateur extends Model
{
    protected $table = 'observateurs';

    protected $fillable = [
        'code',
        'nom',
        'prenom',
        'organisation',
        'type',
        'numero_accreditation',
        'niveau_acces',
        'niveau_id',
        'elections_affectees',
        'actif',
    ];

    protected $casts = [
        'elections_affectees' => 'array',
        'actif' => 'boolean',
    ];
}