<?php

namespace App\Http\Middleware;

use App\Support\AccessControlBootstrapper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        protected AccessControlBootstrapper $bootstrapper,
    ) {
    }

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        $this->bootstrapper->ensureForUser($user);

        $requiredPermissions = collect($permissions)
            ->flatMap(fn (string $permission) => preg_split('/[|,]/', $permission) ?: [])
            ->map(fn (string $permission) => trim($permission))
            ->filter()
            ->values()
            ->all();

        if (!$user->hasAnyPermission($requiredPermissions)) {
            abort(403, 'You do not have permission to access this screen.');
        }

        return $next($request);
    }
}
