<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        return back()->with('success', 'If that email exists in the system, a reset link has been sent.');
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('success', 'Your password has been reset. You can log in now.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => __($status),
            ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $candidate = User::query()->where('email', $credentials['email'])->first();

        if ($candidate && Hash::check($credentials['password'], $candidate->password)) {
            if (!$candidate->is_active) {
                return back()->withErrors([
                    'email' => 'This account is inactive. Contact your administrator.',
                ])->onlyInput('email');
            }

            if (!$candidate->hasActiveHomeClient()) {
                return back()->withErrors([
                    'email' => 'This pharmacy workspace is currently suspended. Contact the platform owner.',
                ])->onlyInput('email');
            }
        }

        if (Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = $request->user();
            app(AccessControlBootstrapper::class)->ensureForUser($user);

            return redirect()->to($this->resolveHomeDestination($user));
        }

        return back()->withErrors([
            'email' => 'Invalid email or password.',
        ])->onlyInput('email');
    }

    public function showChangePassword(Request $request)
    {
        $user = $request->user();
        $hasTenantContext = $user?->isSuperAdmin()
            ? $user->hasSelectedActingContext()
            : true;

        return view('auth.change-password', [
            'user' => $user,
            'clientName' => $hasTenantContext
                ? (optional($user?->client)->name ?? 'N/A')
                : 'Owner Workspace',
            'branchName' => $hasTenantContext
                ? (optional($user?->branch)->name ?? 'N/A')
                : 'No client selected',
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();

        if (!$user || !Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Your current password is not correct.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'remember_token' => Str::random(60),
        ])->save();

        return redirect()
            ->route('account.password.edit')
            ->with('success', 'Your password has been changed successfully.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function resolveHomeDestination(User $user): string
    {
        if ($user->isSuperAdmin()) {
            return route('admin.platform.index');
        }

        $candidates = [
            ['permission' => 'dashboard.view', 'route' => 'dashboard'],
            ['permission' => 'sales.create', 'route' => 'sales.create'],
            ['permission' => 'sales.view_pending', 'route' => 'sales.pending'],
            ['permission' => 'sales.view_approved', 'route' => 'sales.approved'],
            ['permission' => 'sales.view', 'route' => 'sales.index'],
            ['permission' => 'sales.proforma', 'route' => 'sales.proforma'],
            ['permission' => 'sales.view_cancelled', 'route' => 'sales.cancelled'],
            ['permission' => 'purchases.create', 'route' => 'purchases.create'],
            ['permission' => 'purchases.view', 'route' => 'purchases.index'],
            ['permission' => 'products.view', 'route' => 'products.index'],
            ['permission' => 'customers.receivables', 'route' => 'customers.receivables'],
            ['permission' => 'customers.collections.view', 'route' => 'customers.collections.index'],
            ['permission' => 'customers.view', 'route' => 'customers.index'],
            ['permission' => 'suppliers.payables', 'route' => 'suppliers.payables'],
            ['permission' => 'suppliers.payments.view', 'route' => 'suppliers.payments.index'],
            ['permission' => 'suppliers.view', 'route' => 'suppliers.index'],
            ['permission' => 'stock.view', 'route' => 'stock.index'],
            ['permission' => 'accounting.view', 'route' => 'accounting.index'],
            ['permission' => 'accounting.general_ledger', 'route' => 'accounting.general-ledger'],
            ['permission' => 'accounting.trial_balance', 'route' => 'accounting.trial-balance'],
            ['permission' => 'accounting.journals', 'route' => 'accounting.journals'],
            ['permission' => 'accounting.vouchers', 'route' => 'accounting.vouchers'],
            ['permission' => 'accounting.profit_loss', 'route' => 'accounting.profit-loss'],
            ['permission' => 'accounting.balance_sheet', 'route' => 'accounting.balance-sheet'],
            ['permission' => 'accounting.expenses.view', 'route' => 'accounting.expenses.index'],
            ['permission' => 'accounting.fixed_assets.view', 'route' => 'accounting.fixed-assets.index'],
            ['permission' => 'users.manage', 'route' => 'admin.users.index'],
            ['permission' => 'roles.manage', 'route' => 'admin.roles.index'],
            ['permission' => 'reports.view', 'route' => 'reports.index'],
            ['permission' => 'settings.view', 'route' => 'settings.index'],
            ['permission' => 'settings.manage', 'route' => 'settings.index'],
        ];

        foreach ($candidates as $candidate) {
            if ($user->hasPermission($candidate['permission'])) {
                return route($candidate['route']);
            }
        }

        return route('account.password.edit');
    }
}
