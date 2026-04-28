<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Throwable;

class PlatformPostDeploySmokeTestService
{
    public const STATUS_PASS = 'pass';
    public const STATUS_WARNING = 'warning';
    public const STATUS_FAIL = 'fail';

    public function __construct(
        protected HttpKernel $kernel,
    ) {
    }

    public function run(): array
    {
        $checks = [];

        $this->pushGuestRouteCheck(
            $checks,
            'login_screen',
            'Login Screen',
            'login',
            'The public login page should open without errors after deployment.',
            'If this fails, inspect auth routes, view cache, and the auth controller before letting users back in.'
        );

        $owner = $this->activeSuperAdmin();
        if (!$owner) {
            $this->pushCheck(
                $checks,
                'owner_workspace',
                'Owner Workspace',
                self::STATUS_FAIL,
                'No active platform owner account is available for smoke testing.',
                'Keep at least one active super admin account so post-deploy checks can verify owner screens.'
            );
        } else {
            $this->pushAuthenticatedRouteCheck(
                $checks,
                'owner_workspace',
                'Owner Workspace',
                'admin.platform.index',
                $owner,
                'Owner workspace opened successfully.',
                'If this fails, review owner routing, middleware, and the platform context screen immediately.'
            );

            $this->pushAuthenticatedRouteCheck(
                $checks,
                'backup_screen',
                'Backups Screen',
                'admin.platform.backups.index',
                $owner,
                'Platform backup screen opened successfully.',
                'If this fails, review the owner backup controller, backup views, and platform routes.'
            );

            $this->pushAuthenticatedRouteCheck(
                $checks,
                'client_setup_screen',
                'Client Setup Screen',
                'admin.platform.clients.index',
                $owner,
                'Client setup screen opened successfully.',
                'If this fails, review platform client routing, package workflows, and owner permissions.'
            );
        }

        $this->pushTenantRouteCheck(
            $checks,
            'tenant_dashboard',
            'Tenant Dashboard',
            'dashboard',
            ['dashboard.view'],
            false,
            'Tenant dashboard opened successfully.',
            'If this fails, review dashboard controllers, tenant context middleware, and role permissions.'
        );

        $this->pushTenantRouteCheck(
            $checks,
            'sales_screen',
            'Sales Screen',
            'sales.create',
            ['sales.create'],
            false,
            'Sales entry screen opened successfully.',
            'If this fails, review sales controllers, sales views, and permission or package gating.'
        );

        $this->pushTenantRouteCheck(
            $checks,
            'purchases_screen',
            'Purchases Screen',
            'purchases.create',
            ['purchases.create'],
            false,
            'Purchase entry screen opened successfully.',
            'If this fails, review purchase controllers, purchase views, and permission or package gating.'
        );

        $this->pushTenantRouteCheck(
            $checks,
            'reports_screen',
            'Reports Screen',
            'reports.index',
            ['reports.view'],
            false,
            'Reports screen opened successfully.',
            'If this fails, review reports controllers, reporting permissions, and client package access.'
        );

        $this->pushTenantRouteCheck(
            $checks,
            'cash_drawer_screen',
            'Cash Drawer Screen',
            'cash-drawer.index',
            ['cash_drawer.view', 'cash_drawer.manage'],
            true,
            'Cash drawer screen opened successfully.',
            'If this should be enabled, review the client package, cash drawer permissions, and screen wiring.'
        );

        $this->pushTenantRouteCheck(
            $checks,
            'insurance_screen',
            'Insurance Screen',
            'insurance.claims.index',
            ['insurance.view', 'insurance.manage'],
            true,
            'Insurance screen opened successfully.',
            'If this should be enabled, review the client package, insurance permissions, and screen wiring.'
        );

        $summary = [
            'passed' => collect($checks)->where('status', self::STATUS_PASS)->count(),
            'warnings' => collect($checks)->where('status', self::STATUS_WARNING)->count(),
            'failed' => collect($checks)->where('status', self::STATUS_FAIL)->count(),
        ];

        return [
            'checked_at' => now(),
            'healthy' => $summary['failed'] === 0,
            'checks' => $checks,
            'summary' => $summary,
        ];
    }

    protected function pushGuestRouteCheck(
        array &$checks,
        string $key,
        string $label,
        string $routeName,
        string $passMessage,
        string $action
    ): void {
        if (!Route::has($routeName)) {
            $this->pushCheck(
                $checks,
                $key,
                $label,
                self::STATUS_FAIL,
                'Route [' . $routeName . '] is not registered.',
                $action
            );

            return;
        }

        $result = $this->dispatchRoute($routeName, null);

        $this->pushCheck(
            $checks,
            $key,
            $label,
            $result['status'],
            $result['status'] === self::STATUS_PASS ? $passMessage : $result['message'],
            $action
        );
    }

    protected function pushAuthenticatedRouteCheck(
        array &$checks,
        string $key,
        string $label,
        string $routeName,
        User $user,
        string $passMessage,
        string $action
    ): void {
        if (!Route::has($routeName)) {
            $this->pushCheck(
                $checks,
                $key,
                $label,
                self::STATUS_FAIL,
                'Route [' . $routeName . '] is not registered.',
                $action
            );

            return;
        }

        $result = $this->dispatchRoute($routeName, $user);

        $this->pushCheck(
            $checks,
            $key,
            $label,
            $result['status'],
            $result['status'] === self::STATUS_PASS ? $passMessage : $result['message'],
            $action
        );
    }

    protected function pushTenantRouteCheck(
        array &$checks,
        string $key,
        string $label,
        string $routeName,
        array $permissions,
        bool $optional,
        string $passMessage,
        string $action
    ): void {
        if (!Route::has($routeName)) {
            $this->pushCheck(
                $checks,
                $key,
                $label,
                $optional ? self::STATUS_WARNING : self::STATUS_FAIL,
                'Route [' . $routeName . '] is not registered.',
                $action
            );

            return;
        }

        $tenantUser = $this->findTenantUserForPermissions($permissions);

        if (!$tenantUser) {
            $permissionList = implode(', ', $permissions);

            $this->pushCheck(
                $checks,
                $key,
                $label,
                $optional ? self::STATUS_WARNING : self::STATUS_FAIL,
                'No active tenant user with [' . $permissionList . '] is available for this smoke test.',
                $optional
                    ? 'This optional module check was skipped because no active tenant currently exposes it.'
                    : 'Ensure at least one active tenant user has the required role and module access so this core screen can be tested after deployment.'
            );

            return;
        }

        $result = $this->dispatchRoute($routeName, $tenantUser);

        $this->pushCheck(
            $checks,
            $key,
            $label,
            $result['status'],
            $result['status'] === self::STATUS_PASS
                ? $passMessage . ' Checked as ' . $tenantUser->name . '.'
                : $result['message'],
            $action
        );
    }

    protected function activeSuperAdmin(): ?User
    {
        return User::query()
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    protected function findTenantUserForPermissions(array $permissions): ?User
    {
        return User::query()
            ->where('is_super_admin', false)
            ->where('is_active', true)
            ->whereNotNull('client_id')
            ->whereNotNull('branch_id')
            ->orderBy('id')
            ->get()
            ->first(function (User $user) use ($permissions) {
                return $user->hasActiveHomeClient() && $user->hasAnyPermission($permissions);
            });
    }

    protected function dispatchRoute(string $routeName, ?User $user): array
    {
        $this->resetAuthState();

        $routeUrl = route($routeName);
        $request = Request::create($routeUrl, 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);

        if ($user) {
            Auth::shouldUse('web');
            Auth::guard('web')->setUser($user->fresh());
            $request->setUserResolver(fn () => Auth::user());
        } else {
            $request->setUserResolver(fn () => null);
        }

        try {
            $response = $this->kernel->handle($request);
            $statusCode = $response->getStatusCode();
            $redirectTarget = $response->isRedirection() ? $response->headers->get('Location') : null;
            $this->kernel->terminate($request, $response);
        } catch (Throwable $exception) {
            $this->resetAuthState();

            return [
                'status' => self::STATUS_FAIL,
                'message' => 'Route [' . $routeName . '] threw an exception: ' . $exception->getMessage(),
            ];
        }

        $this->resetAuthState();

        if ($statusCode === 200) {
            return [
                'status' => self::STATUS_PASS,
                'message' => 'Route [' . $routeName . '] returned HTTP 200.',
            ];
        }

        $message = 'Route [' . $routeName . '] returned HTTP ' . $statusCode . '.';
        if ($redirectTarget) {
            $message .= ' Redirect target: ' . $redirectTarget . '.';
        }

        return [
            'status' => self::STATUS_FAIL,
            'message' => $message,
        ];
    }

    protected function resetAuthState(): void
    {
        if (method_exists(app('auth'), 'forgetGuards')) {
            app('auth')->forgetGuards();
        }
    }

    protected function pushCheck(array &$checks, string $key, string $label, string $status, string $message, string $action): void
    {
        $checks[] = [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'action' => $action,
        ];
    }
}
