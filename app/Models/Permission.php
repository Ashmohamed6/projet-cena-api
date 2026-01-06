<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;


/**
 * Modèle Permission
 */
class Permission extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'module',
    ];

    // Relations
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions',
            'permission_id',
            'role_id'
        );
    }

    // Scopes
    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }
}
