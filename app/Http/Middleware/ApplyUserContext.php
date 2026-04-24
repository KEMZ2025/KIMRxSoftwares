<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Client;
use App\Support\CashDrawerAlerts;
use App\Support\InventoryExpiryAlerts;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserContext
{
    protected const SESSION_KEY = 'super_admin.context';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if (!$user->isSuperAdmin()) {
            $request->session()->forget(self::SESSION_KEY);
            $user->clearActingContext();

            if (!$user->hasActiveHomeClient()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->withErrors([
                        'email' => 'This pharmacy workspace is currently suspended. Contact the platform owner.',
                    ]);
            }

            $this->queueExpiryReminder($request);
            $this->queueCashDrawerReminder($request);

            return $next($request);
        }

        [$client, $branch] = $this->resolveContext($request);

        if (!$client || !$branch) {
            $user->enterOwnerWorkspace();
            Auth::setUser($user);

            if ($this->requiresTenantContext($request)) {
                return redirect()
                    ->route('admin.platform.index')
                    ->with('warning', 'Choose a client and branch before opening tenant workspace screens.');
            }

            return $next($request);
        }

        $user->setActingContext($client?->id, $branch?->id, $client, $branch);
        Auth::setUser($user);

        return $next($request);
    }

    protected function resolveContext(Request $request): array
    {
        $storedContext = $request->session()->get(self::SESSION_KEY, []);
        $client = $this->resolveClient((int) ($storedContext['client_id'] ?? 0));

        $branch = $client
            ? $this->resolveBranch($client->id, (int) ($storedContext['branch_id'] ?? 0))
            : null;

        if ($client && !$branch) {
            $branch = Branch::query()
                ->where('client_id', $client->id)
                ->where('is_active', true)
                ->orderByDesc('is_main')
                ->orderBy('name')
                ->first();
        }

        $request->session()->put(self::SESSION_KEY, [
            'client_id' => $client?->id,
            'branch_id' => $branch?->id,
        ]);

        return [$client, $branch];
    }

    protected function requiresTenantContext(Request $request): bool
    {
        return !$request->routeIs('admin.platform.*', 'admin.audit.*', 'account.password.*');
    }

    protected function resolveClient(?int $clientId): ?Client
    {
        if (!$clientId) {
            return null;
        }

        return Client::query()
            ->where('id', $clientId)
            ->first();
    }

    protected function resolveBranch(int $clientId, ?int $branchId): ?Branch
    {
        if (!$branchId) {
            return null;
        }

        return Branch::query()
            ->where('id', $branchId)
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->first();
    }

    protected function queueExpiryReminder(Request $request): void
    {
        $user = $request->user();

        if (!$user || !$this->shouldEvaluateExpiryReminder($request, $user)) {
            return;
        }

        $warning = InventoryExpiryAlerts::pullDueWarning($request, $user);
        if (!$warning) {
            return;
        }

        $request->session()->flash('expiry_warning', $warning);
    }

    protected function shouldEvaluateExpiryReminder(Request $request, $user): bool
    {
        if (!InventoryExpiryAlerts::shouldWarnUser($user)) {
            return false;
        }

        if (!$request->isMethod('GET') || $request->expectsJson() || $request->ajax()) {
            return false;
        }

        return !$request->routeIs('*.print*', '*.download*');
    }

    protected function queueCashDrawerReminder(Request $request): void
    {
        $user = $request->user();

        if (!$user || !$this->shouldEvaluateCashDrawerReminder($request, $user)) {
            return;
        }

        $warning = CashDrawerAlerts::pullDueWarning($request, $user);
        if (!$warning) {
            return;
        }

        $request->session()->flash('cash_drawer_warning', $warning);
    }

    protected function shouldEvaluateCashDrawerReminder(Request $request, $user): bool
    {
        if (!CashDrawerAlerts::shouldWarnUser($user)) {
            return false;
        }

        if (!$request->isMethod('GET') || $request->expectsJson() || $request->ajax()) {
            return false;
        }

        return !$request->routeIs('*.print*', '*.download*');
    }
}
