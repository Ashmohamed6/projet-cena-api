<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;

/**
 * Modèle Role
 */
class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'niveau_hierarchique',
        'restriction_geographique',
    ];

    protected $casts = [
        'restriction_geographique' => 'boolean',
    ];

    // Relations
    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id'
        );
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'user_affectations',
            'role_id',
            'user_id'
        )->wherePivot('actif', true);
    }

    public function affectations()
    {
        return $this->hasMany(UserAffectation::class);
    }

    // Méthodes
    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('slug', $permission)->exists();
    }

    public function givePermission(Permission $permission): void
    {
        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    public function revokePermission(Permission $permission): void
    {
        $this->permissions()->detach($permission->id);
    }

    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }
}
