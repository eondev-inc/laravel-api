<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_active', 'uuid'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * HasUuids auto-asigna UUID solo a estas columnas (no a la PK 'id').
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Route-model binding por uuid en lugar de id numérico.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** @var array<string, bool> Cache request-scoped de roles */
    private array $roleCache = [];

    /** @var array<string, bool> Cache request-scoped de permisos */
    private array $permissionCache = [];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $role): bool
    {
        if (! array_key_exists($role, $this->roleCache)) {
            $this->roleCache[$role] = $this->roles()->where('name', $role)->exists();
        }

        return $this->roleCache[$role];
    }

    public function hasPermission(string $permission): bool
    {
        if (! array_key_exists($permission, $this->permissionCache)) {
            $this->permissionCache[$permission] = $this->roles()
                ->whereHas('permissions', fn ($q) => $q->where('permissions.name', $permission))
                ->exists();
        }

        return $this->permissionCache[$permission];
    }

    /**
     * Retorna true si el usuario tiene AL MENOS UNO de los roles dados (OR logic).
     *
     * @param  array<string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna true si el usuario tiene AL MENOS UNO de los permisos dados (OR logic).
     *
     * @param  array<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
