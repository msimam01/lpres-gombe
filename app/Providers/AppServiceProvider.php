<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // Content administration (news, events, projects, media, settings) is
        // restricted to designated administrators. Ordinary authenticated
        // users keep their account but cannot reach these actions.
        Gate::define('manage-content', fn (User $user) => (bool) $user->is_admin);
    }
}
