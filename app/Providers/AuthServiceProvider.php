<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only admins can create, edit, or activate formulas
        Gate::define('manage-formulas', fn (User $user) => $user->role === 'admin');

        // Both roles can read data — used on list/show endpoints
        Gate::define('view-only', fn (User $user) => in_array($user->role, ['admin', 'viewer']));
    }
}
