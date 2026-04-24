<?php

namespace App\Models;

use App\Support\AccessControlBootstrapper;
use App\Support\ClientFeatureAccess;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected ?Collection $resolvedPermissions = null;
    protected ?ClientSetting $resolvedClientSettings = null;
    protected ?int $actingClientId = null;
    protected ?int $actingBranchId = null;
    protected bool $accessControlEnsured = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'client_id',
        'branch_id',
        'is_active',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    protected function clientId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->actingClientId ?? $value,
        );
    }

    protected function branchId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->actingBranchId ?? $value,
        );
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function homeClientId(): ?int
    {
        return $this->getRawOriginal('client_id') ?: ($this->attributes['client_id'] ?? null);
    }

    public function homeBranchId(): ?int
    {
        return $this->getRawOriginal('branch_id') ?: ($this->attributes['branch_id'] ?? null);
    }

    public function hasActiveHomeClient(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $clientId = (int) $this->homeClientId();

        if ($clientId <= 0) {
            return false;
        }

        return Client::query()
            ->whereKey($clientId)
            ->where('is_active', true)
            ->exists();
    }

    public function enterOwnerWorkspace(): self
    {
        return $this->setActingContext(0, 0, null, null);
    }

    public function hasSelectedActingContext(): bool
    {
        return (int) ($this->actingClientId ?? 0) > 0
            && (int) ($this->actingBranchId ?? 0) > 0;
    }

    public function setActingContext(?int $clientId, ?int $branchId, ?Client $client = null, ?Branch $branch = null): self
    {
        $this->actingClientId = $clientId;
        $this->actingBranchId = $branchId;
        $this->resolvedClientSettings = null;

        if ($client) {
            $this->setRelation('client', $client);
        } else {
            $this->unsetRelation('client');
        }

        if ($branch) {
            $this->setRelation('branch', $branch);
        } else {
            $this->unsetRelation('branch');
        }

        return $this;
    }

    public function clearActingContext(): self
    {
        $this->actingClientId = null;
        $this->actingBranchId = null;
        $this->resolvedClientSettings = null;

        return $this;
    }

    public function hasPermission(string $permissionKey): bool
    {
        if (!$this->exists || !$permissionKey) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->ensureAccessControlIsReady();

        if (!$this->permissionCollection()->contains($permissionKey)) {
            return false;
        }

        return $this->featureEnabledForPermission($permissionKey);
    }

    public function hasAnyPermission(array $permissionKeys): bool
    {
        if (!$this->exists) {
            return false;
        }

        if ($permissionKeys === []) {
            return true;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->ensureAccessControlIsReady();

        $permissions = $this->permissionCollection();

        foreach ($permissionKeys as $permissionKey) {
            if ($permissions->contains($permissionKey) && $this->featureEnabledForPermission($permissionKey)) {
                return true;
            }
        }

        return false;
    }

    public function clientSettingsModel(): ?ClientSetting
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        if ($this->resolvedClientSettings instanceof ClientSetting) {
            return $this->resolvedClientSettings;
        }

        $clientId = (int) $this->client_id;

        if ($clientId <= 0) {
            return null;
        }

        $defaults = ['business_mode' => $this->client?->business_mode ?? 'both'] + ClientFeatureAccess::defaultSettingValues();

        $this->resolvedClientSettings = ClientSetting::query()->firstOrCreate(
            ['client_id' => $clientId],
            $defaults
        );

        return $this->resolvedClientSettings;
    }

    public function featureEnabledForPermission(string $permissionKey): bool
    {
        return ClientFeatureAccess::permissionEnabled($this->clientSettingsModel(), $permissionKey);
    }

    protected function permissionCollection(): Collection
    {
        if ($this->resolvedPermissions instanceof Collection) {
            return $this->resolvedPermissions;
        }

        $this->loadMissing('roles.permissions');

        $this->resolvedPermissions = $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('permission_key'))
            ->filter()
            ->unique()
            ->values();

        return $this->resolvedPermissions;
    }

    protected function ensureAccessControlIsReady(): void
    {
        if ($this->accessControlEnsured) {
            return;
        }

        app(AccessControlBootstrapper::class)->ensureForUser($this);

        $this->resolvedPermissions = null;
        $this->unsetRelation('roles');
        $this->accessControlEnsured = true;
    }
}
