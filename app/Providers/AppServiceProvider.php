<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('pagination.premium');
        Paginator::defaultSimpleView('pagination.premium-simple');

        Blade::if('permission', function (string $permissionKey) {
            return auth()->check() && auth()->user()->hasPermission($permissionKey);
        });

        Blade::if('anyPermission', function (...$permissionKeys) {
            return auth()->check() && auth()->user()->hasAnyPermission($permissionKeys);
        });
    }
}
