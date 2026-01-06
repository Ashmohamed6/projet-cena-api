<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartiPolitique extends Model
{
    protected $table = 'entites_politiques';

    protected $fillable = [
        'nom',
        'sigle',
        'numero_ordre',
        'type',
    ];
}
