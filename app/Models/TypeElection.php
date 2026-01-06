<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeElection extends Model
{
    protected $table = 'types_election';

    protected $fillable = [
        'code',
        'nom',
        'description',
        'regles',
        'actif',
    ];

    protected $casts = [
        'regles' => 'array', // JSON
        'actif' => 'boolean',
    ];

    public function elections()
    {
        return $this->hasMany(Election::class, 'type_election_id');
    }
}
