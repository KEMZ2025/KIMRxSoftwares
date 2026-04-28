<?php

namespace App\Models;

use App\Support\ClientPackagePresetCatalog;
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
        'package_preset',
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

    public static function packagePresetOptions(): array
    {
        return ClientPackagePresetCatalog::options();
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

    public function displayPackagePreset(): string
    {
        return ClientPackagePresetCatalog::label($this->package_preset);
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

    public function subscriptionDaysUntilEnd(): ?int
    {
        if (!$this->subscription_ends_at) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->subscription_ends_at->copy()->startOfDay(), false);
    }

    public function subscriptionExpired(): bool
    {
        $daysUntilEnd = $this->subscriptionDaysUntilEnd();

        return is_int($daysUntilEnd) && $daysUntilEnd < 0;
    }

    public function subscriptionEndsSoon(int $days = 7): bool
    {
        $daysUntilEnd = $this->subscriptionDaysUntilEnd();

        return is_int($daysUntilEnd) && $daysUntilEnd >= 0 && $daysUntilEnd <= $days;
    }

    public function subscriptionRenewalLabel(): string
    {
        if (!$this->subscription_ends_at) {
            return 'No renewal date set';
        }

        $daysUntilEnd = $this->subscriptionDaysUntilEnd();

        if (!is_int($daysUntilEnd)) {
            return 'Ends ' . $this->subscription_ends_at->format('d M Y');
        }

        if ($daysUntilEnd < 0) {
            return 'Expired ' . abs($daysUntilEnd) . ' day(s) ago';
        }

        if ($daysUntilEnd === 0) {
            return 'Expires today';
        }

        return 'Expires in ' . $daysUntilEnd . ' day(s)';
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
