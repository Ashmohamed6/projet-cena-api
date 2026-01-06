<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultatHistorique extends Model
{
    protected $table = 'resultats_historique';

    public $timestamps = false;

    protected $fillable = [
        'resultat_id',
        'action',
        'ancien_nombre_voix',
        'nouveau_nombre_voix',
        'user_id',
        'motif',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // Relations
    public function resultat()
    {
        return $this->belongsTo(Resultat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}