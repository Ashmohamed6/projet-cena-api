<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // ← IMPORTANT : Ajouter ce trait
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * User Model
 * 
 * IMPORTANT : Ce modèle DOIT utiliser le trait HasApiTokens de Laravel Sanctum
 * pour que l'authentification API fonctionne correctement.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens; // ← Trait HasApiTokens ajouté

    /**
     * Table associée
     */
    protected $table = 'users';

    /**
     * Attributs assignables en masse
     */
    protected $fillable = [
        'code',
        'nom',
        'prenom',
        'email',
        'telephone',
        'password',
        'photo',
        'statut',
        'derniere_connexion',
        'ip_derniere_connexion',
        'email_verified_at',
    ];

    /**
     * Attributs cachés (ne pas exposer dans les réponses JSON)
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attributs castés
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'derniere_connexion' => 'datetime',
        'password' => 'hashed', // Laravel 11
    ];

    /**
     * Relation : Un utilisateur a plusieurs affectations
     */
    public function affectations(): HasMany
    {
        return $this->hasMany(UserAffectation::class, 'user_id');
    }

    /**
     * Relation : Un utilisateur a plusieurs sessions
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class, 'user_id');
    }

    /**
     * Relation : Un utilisateur a plusieurs logs d'activité
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    /**
     * Vérifier si l'utilisateur a un rôle spécifique
     * 
     * @param string $roleSlug
     * @return bool
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->affectations()
            ->whereHas('role', function ($query) use ($roleSlug) {
                $query->where('slug', $roleSlug);
            })
            ->exists();
    }

    /**
     * Vérifier si l'utilisateur a une permission spécifique
     * 
     * @param string $permissionSlug
     * @return bool
     */
    public function hasPermission(string $permissionSlug): bool
    {
        foreach ($this->affectations as $affectation) {
            $hasPermission = $affectation->role
                ->permissions()
                ->where('slug', $permissionSlug)
                ->exists();

            if ($hasPermission) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifier si l'utilisateur a accès à un niveau géographique
     * 
     * @param string $niveau
     * @param int $niveauId
     * @return bool
     */
    public function hasAccessToNiveau(string $niveau, int $niveauId): bool
    {
        return $this->affectations()
            ->where('niveau', $niveau)
            ->where('niveau_id', $niveauId)
            ->exists();
    }

    /**
     * Vérifier si l'utilisateur a accès à une élection
     * 
     * @param int $electionId
     * @return bool
     */
    public function hasAccessToElection(int $electionId): bool
    {
        // Si l'utilisateur a une affectation sans élection spécifique,
        // il a accès à toutes les élections
        $hasGlobalAccess = $this->affectations()
            ->whereNull('election_id')
            ->exists();

        if ($hasGlobalAccess) {
            return true;
        }

        // Sinon, vérifier l'accès spécifique à l'élection
        return $this->affectations()
            ->where('election_id', $electionId)
            ->exists();
    }

    /**
     * Obtenir tous les rôles de l'utilisateur
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getRoles()
    {
        return $this->affectations->map(fn($affectation) => $affectation->role);
    }

    /**
     * Obtenir toutes les permissions de l'utilisateur
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getPermissions()
    {
        $permissions = collect();

        foreach ($this->affectations as $affectation) {
            $rolePermissions = $affectation->role->permissions;
            $permissions = $permissions->merge($rolePermissions);
        }

        return $permissions->unique('id');
    }

    /**
     * Scope : Utilisateurs actifs uniquement
     */
    public function scopeActive($query)
    {
        return $query->where('statut', 'actif');
    }

    /**
     * Scope : Utilisateurs par statut
     */
    public function scopeByStatut($query, string $statut)
    {
        return $query->where('statut', $statut);
    }

    /**
     * Accessor : Nom complet
     */
    public function getNomCompletAttribute(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

/**
 * Récupérer l'ID de l'élection active
 */
public function getActiveElectionId(): int
{
    $election = \App\Models\Election::orderBy('id')->first();
    
    if (!$election) {
        throw new \Exception('Aucune élection disponible');
    }
    
    return $election->id;
}
}