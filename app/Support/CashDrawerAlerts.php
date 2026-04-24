<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class CashDrawerAlerts
{
    public const SESSION_SIGNATURE_KEY = 'cash_drawer.last_warning_signature';

    public static function pollSeconds(): int
    {
        return 60;
    }

    public static function shouldWarnUser(User $user): bool
    {
        if (
            !$user->exists
            || $user->isSuperAdmin()
            || (int) $user->client_id <= 0
            || (int) $user->branch_id <= 0
            || !ClientFeatureAccess::cashDrawerEnabled($user->clientSettingsModel())
        ) {
            return false;
        }

        $roleNames = ($user->relationLoaded('roles') ? $user->roles : $user->roles()->get(['name']))
            ->pluck('name')
            ->map(fn ($name) => strtolower(trim((string) $name)));

        if ($roleNames->intersect(['admin', 'cashier'])->isNotEmpty()) {
            return true;
        }

        return $user->hasAnyPermission(['cash_drawer.view', 'cash_drawer.manage']);
    }

    public static function pullDueWarning(Request $request, User $user): ?array
    {
        if (!self::shouldWarnUser($user)) {
            return null;
        }

        $warning = self::forUser($user);
        if (!$warning) {
            $request->session()->forget(self::SESSION_SIGNATURE_KEY);

            return null;
        }

        if ((string) $request->session()->get(self::SESSION_SIGNATURE_KEY, '') === (string) $warning['signature']) {
            return null;
        }

        $request->session()->put(self::SESSION_SIGNATURE_KEY, (string) $warning['signature']);

        return $warning;
    }

    public static function forUser(User $user): ?array
    {
        $summary = app(CashDrawerService::class)->summaryForUser($user, Carbon::today(config('app.timezone')), false);

        if (($summary['day_closed'] ?? false) || !($summary['threshold_reached'] ?? false)) {
            return null;
        }

        $sessionId = $summary['session']?->id ?: 0;
        $signature = implode('|', [
            $summary['date'],
            $sessionId,
            number_format((float) ($summary['current_balance'] ?? 0), 2, '.', ''),
            number_format((float) ($summary['draws_total'] ?? 0), 2, '.', ''),
            number_format((float) ($summary['alert_threshold'] ?? 0), 2, '.', ''),
        ]);

        return [
            'count' => 1,
            'signature' => $signature,
            'date' => $summary['date'],
            'currency_symbol' => $summary['currency_symbol'],
            'current_balance' => $summary['current_balance'],
            'alert_threshold' => $summary['alert_threshold'],
            'threshold_gap' => $summary['threshold_gap'],
            'opening_balance' => $summary['opening_balance'],
            'cash_sales_total' => $summary['cash_sales_total'],
            'cash_collections_total' => $summary['cash_collections_total'],
            'draws_total' => $summary['draws_total'],
            'action_url' => Route::has('cash-drawer.index') ? route('cash-drawer.index') : null,
        ];
    }
}
