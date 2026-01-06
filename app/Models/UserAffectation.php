<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserAffectation Model - STRUCTURE RÉELLE
 *
 * ✅ Table: user_affectations
 * ✅ Utilise le champ "actif" (boolean)
 */
class UserAffectation extends Model
{
    protected $table = 'user_affectations';

    protected $fillable = [
        'user_id',
        'role_id',
        'election_id',
        'parti_politique_id',
        'niveau_affectation',
        'niveau_affectation_id',
        'actif', // ✅ NOM CORRECT
        'date_debut',
        'date_fin',
    ];

    protected $casts = [
        'actif' => 'boolean', // ✅ Cast en boolean
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    // ============================================
    // RELATIONS
    // ============================================

    /**
     * L'affectation appartient à un utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * L'affectation appartient à un rôle
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * L'affectation appartient à une élection
     */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /**
     * L'affectation appartient à un parti politique (optionnel)
     */
    public function partiPolitique(): BelongsTo
    {
        return $this->belongsTo(PartiPolitique::class, 'parti_politique_id');
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope pour les affectations actives
     */
    public function scopeActive($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Scope pour une élection spécifique
     */
    public function scopeForElection($query, int $electionId)
    {
        return $query->where('election_id', $electionId);
    }
}
