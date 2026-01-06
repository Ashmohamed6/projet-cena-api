<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $table = 'incidents';

    protected $fillable = [
        'code',
        'election_id',
        'proces_verbal_id',
        'type',
        'gravite',
        'niveau',
        'niveau_id',
        'description',
        'pieces_jointes',
        'rapporte_par_user_id',
        'traite_par_user_id',
        'statut',
        'resolution',
        'date_resolution',
    ];

    protected $casts = [
        'pieces_jointes' => 'array',
        'date_resolution' => 'datetime',
    ];

    // Relations
    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    public function procesVerbal()
    {
        return $this->belongsTo(ProcesVerbal::class);
    }

    public function rapportePar()
    {
        return $this->belongsTo(User::class, 'rapporte_par_user_id');
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par_user_id');
    }
}