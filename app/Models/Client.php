<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    public const TYPE_PAYING = 'paying';
    public const TYPE_TRIAL = 'trial';
    public const TYPE_DEMO = 'demo';
    public const TYPE_INTERNAL = 'internal';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_GRACE = 'grace';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'logo',
        'business_mode',
        'client_type',
        'subscription_status',
        'active_user_limit',
        'subscription_ends_at',
        'is_active',
        'is_platform_sandbox',
    ];

    protected function casts(): array
    {
        return [
            'active_user_limit' => 'integer',
            'subscription_ends_at' => 'date',
            'is_active' => 'boolean',
            'is_platform_sandbox' => 'boolean',
        ];
    }

    public static function clientTypeOptions(): array
    {
        return [
            self::TYPE_PAYING => 'Paying Client',
            self::TYPE_TRIAL => 'Trial Client',
            self::TYPE_DEMO => 'Demo Client',
            self::TYPE_INTERNAL => 'Internal Client',
        ];
    }

    public static function subscriptionStatusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_GRACE => 'Grace Period',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_SUSPENDED => 'Suspended',
        ];
    }

    public function isPlatformSandbox(): bool
    {
        return (bool) $this->is_platform_sandbox;
    }

    public function displayClientType(): string
    {
        return self::clientTypeOptions()[$this->client_type] ?? ucfirst(str_replace('_', ' ', (string) $this->client_type));
    }

    public function displaySubscriptionStatus(): string
    {
        return self::subscriptionStatusOptions()[$this->subscription_status] ?? ucfirst(str_replace('_', ' ', (string) $this->subscription_status));
    }

    public function hasUnlimitedActiveUsers(): bool
    {
        return !is_int($this->active_user_limit) || $this->active_user_limit <= 0;
    }

    public function activeUserLimitLabel(): string
    {
        return $this->hasUnlimitedActiveUsers()
            ? 'Unlimited'
            : number_format((int) $this->active_user_limit);
    }

    public function remainingActiveUserSeats(?int $activeUsersCount = null): ?int
    {
        if ($this->hasUnlimitedActiveUsers()) {
            return null;
        }

        $used = $activeUsersCount ?? $this->activeManagedUsersCount();

        return max(((int) $this->active_user_limit) - $used, 0);
    }

    public function activeUserLimitReached(?int $activeUsersCount = null): bool
    {
        if ($this->hasUnlimitedActiveUsers()) {
            return false;
        }

        $used = $activeUsersCount ?? $this->activeManagedUsersCount();

        return $used >= (int) $this->active_user_limit;
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function setting()
    {
        return $this->hasOne(ClientSetting::class);
    }

    public function activeManagedUsersCount(): int
    {
        return $this->users()
            ->where('is_super_admin', false)
            ->where('is_active', true)
            ->count();
    }
}
