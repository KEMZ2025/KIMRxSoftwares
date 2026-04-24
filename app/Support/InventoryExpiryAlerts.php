<?php

namespace App\Support;

use App\Models\ProductBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InventoryExpiryAlerts
{
    public const SESSION_SLOT_KEY = 'inventory_expiry.last_slot';

    private const REMINDER_HOURS = [8, 13, 18];

    public static function reminderHours(): array
    {
        return self::REMINDER_HOURS;
    }

    public static function currentReminderSlotKey(?Carbon $now = null): ?string
    {
        $now = ($now ?: Carbon::now(config('app.timezone')))->copy();
        $hour = (int) $now->format('G');

        if ($hour < self::REMINDER_HOURS[0]) {
            return null;
        }

        $slot = 1;
        foreach (self::REMINDER_HOURS as $index => $reminderHour) {
            if ($hour >= $reminderHour) {
                $slot = $index + 1;
            }
        }

        return $now->toDateString() . ':slot-' . $slot;
    }

    public static function pullDueWarning(Request $request, User $user): ?array
    {
        if (!self::shouldWarnUser($user)) {
            return null;
        }

        $currentSlot = self::currentReminderSlotKey();
        if ($currentSlot === null) {
            return null;
        }

        if ((string) $request->session()->get(self::SESSION_SLOT_KEY, '') === $currentSlot) {
            return null;
        }

        $warning = self::forUser($user);
        if ((int) ($warning['count'] ?? 0) <= 0) {
            $request->session()->forget(self::SESSION_SLOT_KEY);

            return null;
        }

        $request->session()->put(self::SESSION_SLOT_KEY, $currentSlot);

        return $warning + ['slot_key' => $currentSlot];
    }

    public static function shouldWarnUser(User $user): bool
    {
        if (
            !$user->exists
            || $user->isSuperAdmin()
            || (int) $user->client_id <= 0
            || (int) $user->branch_id <= 0
            || !ClientFeatureAccess::expiryAlertsEnabled($user->clientSettingsModel())
        ) {
            return false;
        }

        $roleNames = ($user->relationLoaded('roles') ? $user->roles : $user->roles()->get(['name']))
            ->pluck('name')
            ->map(fn ($name) => strtolower(trim((string) $name)));

        if ($roleNames->intersect(['admin', 'dispenser'])->isNotEmpty()) {
            return true;
        }

        return $user->hasAnyPermission(['sales.create', 'stock.view']);
    }

    public static function countForBranch(int $clientId, int $branchId): int
    {
        return self::rowsForBranch($clientId, $branchId)->count();
    }

    public static function forUser(User $user, int $limit = 4): array
    {
        $rows = self::rowsForBranch((int) $user->client_id, (int) $user->branch_id);

        return [
            'count' => $rows->count(),
            'items' => $rows->take($limit)->values()->all(),
            'generated_for' => Carbon::today(config('app.timezone'))->toDateString(),
        ];
    }

    private static function rowsForBranch(int $clientId, int $branchId)
    {
        if ($clientId <= 0 || $branchId <= 0) {
            return collect();
        }

        $today = Carbon::today(config('app.timezone'));

        return ProductBatch::query()
            ->with(['product:id,name,strength,track_expiry,expiry_alert_days', 'product.unit:id,name'])
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNotNull('expiry_date')
            ->get()
            ->map(function (ProductBatch $batch) use ($today) {
                if (!$batch->product?->track_expiry || !$batch->expiry_date) {
                    return null;
                }

                $freeStock = max(0, (float) $batch->quantity_available - (float) $batch->reserved_quantity);
                $alertDays = max(0, (int) ($batch->product->expiry_alert_days ?? 90));
                $expiryDate = $batch->expiry_date->copy()->startOfDay();
                $daysToExpiry = $today->diffInDays($expiryDate, false);

                if ($freeStock <= 0 || $daysToExpiry < 0 || $daysToExpiry > $alertDays) {
                    return null;
                }

                return [
                    'product_name' => $batch->product?->name ?? 'Unknown Product',
                    'strength' => $batch->product?->strength,
                    'unit_name' => $batch->product?->unit?->name,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $expiryDate,
                    'days_to_expiry' => $daysToExpiry,
                    'free_stock' => round($freeStock, 2),
                    'risk_label' => $daysToExpiry === 0 ? 'Expires today' : $daysToExpiry . ' days left',
                ];
            })
            ->filter()
            ->sortBy(function (array $row) {
                return str_pad((string) $row['days_to_expiry'], 5, '0', STR_PAD_LEFT) . '-' . strtolower($row['product_name']);
            })
            ->values();
    }
}
