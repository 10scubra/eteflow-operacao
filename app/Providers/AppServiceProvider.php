<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\User;
use App\Policies\EmployeePolicy;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::before(function (User $user, string $ability): ?bool {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return app(PermissionService::class)->allows($user, $ability);
        });
    }
}
