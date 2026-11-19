<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Paginator::useBootstrapFive();

        Gate::before(fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);
        Gate::define('view-assets', fn (User $user): bool => true);
        Gate::define('manage-assets', fn (User $user): bool => $user->role->canManageAssets());
        Gate::define('view-assignments', fn (User $user): bool => true);
        Gate::define('manage-assignments', fn (User $user): bool => $user->role->canManageAssignments());
        Gate::define('view-inventories', fn (User $user): bool => true);
        Gate::define('view-maintenance', fn (User $user): bool => true);
        Gate::define('view-stock', fn (User $user): bool => true);
        Gate::define('manage-stock', fn (User $user): bool => in_array($user->role, [UserRole::SuperAdmin, UserRole::Administrator, UserRole::AssetManager], true));
        Gate::define('report-maintenance', fn (User $user): bool => true);
        Gate::define('manage-maintenance', fn (User $user): bool => in_array($user->role, [UserRole::SuperAdmin, UserRole::Administrator, UserRole::AssetManager], true));
        Gate::define('validate-maintenance', fn (User $user): bool => in_array($user->role, [UserRole::SuperAdmin, UserRole::Administrator, UserRole::AssetManager], true));
        Gate::define('manage-maintenance-schedules', fn (User $user): bool => in_array($user->role, [UserRole::SuperAdmin, UserRole::Administrator, UserRole::AssetManager], true));
        Gate::define('view-maintenance-costs', fn (User $user): bool => in_array($user->role, [UserRole::SuperAdmin, UserRole::Administrator, UserRole::AssetManager], true));
        Gate::define('manage-inventories', fn (User $user): bool => in_array($user->role, [UserRole::SuperAdmin, UserRole::AssetManager], true));
        Gate::define('verify-inventories', fn (User $user): bool => in_array($user->role, [UserRole::SuperAdmin, UserRole::AssetManager], true));
        Gate::define('validate-inventories', fn (User $user): bool => in_array($user->role, [UserRole::SuperAdmin, UserRole::Administrator], true));
        Gate::define('close-inventories', fn (User $user): bool => in_array($user->role, [UserRole::SuperAdmin, UserRole::Administrator], true));
        Gate::define('manage-users', fn (User $user): bool => in_array($user->role, [UserRole::SuperAdmin, UserRole::Administrator], true));

        RateLimiter::for('login', function (Request $request): Limit {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower($email) : '';

            return Limit::perMinute(5)->by(hash('sha256', $request->ip().'|'.$email));
        });

        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perHour(5)->by($request->ip()));
    }
}
