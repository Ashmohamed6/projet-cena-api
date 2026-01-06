<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resultat extends Model
{
    protected $table = 'resultats';

    protected $fillable = [
        'proces_verbal_id',
        'candidature_id',
        'nombre_voix',
        'version',
        'operateur_user_id',
        'date_saisie',
    ];

    protected $casts = [
        'date_saisie' => 'datetime',
    ];

    // Relations
    public function procesVerbal()
    {
        return $this->belongsTo(ProcesVerbal::class);
    }

    public function candidature()
    {
        return $this->belongsTo(Candidature::class);
    }

    public function operateur()
    {
        return $this->belongsTo(User::class, 'operateur_user_id');
    }

    public function historique()
    {
        return $this->hasMany(ResultatHistorique::class);
    }

    // Events pour audit trail
    protected static function booted()
    {
        static::created(function ($resultat) {
            $resultat->historique()->create([
                'action' => 'creation',
                'nouveau_nombre_voix' => $resultat->nombre_voix,
                'user_id' => $resultat->operateur_user_id,
            ]);
        });

        static::updated(function ($resultat) {
            if ($resultat->isDirty('nombre_voix')) {
                $resultat->historique()->create([
                    'action' => 'modification',
                    'ancien_nombre_voix' => $resultat->getOriginal('nombre_voix'),
                    'nouveau_nombre_voix' => $resultat->nombre_voix,
                    'user_id' => auth()->id(),
                ]);
            }
        });
    }
}