<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Constants\UsersRoles;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        'App\Models\User' => 'App\Policies\UserPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('super_admin', function (User $user): bool  {
            return $user->getActiveRole()->role_code == UsersRoles::SUPER_ADMIN;
        });

        Gate::define('moderator', function (User $user): bool  {
            return $user->getActiveRole()->role_code == UsersRoles::MODERATOR;
        });

        Gate::define('editor', function (User $user): bool  {
            return $user->getActiveRole()->role_code == UsersRoles::EDITOR;
        });

        Gate::define('user', function (User $user): bool  {
            return $user->getActiveRole()->role_code == UsersRoles::USER;
        });

        Gate::define('super_user', function (User $user): bool  {
            return $user->getActiveRole()->role_code == UsersRoles::SUPER_USER;
        });

        Gate::define('new_user', function (User $user): bool {
            return $user->getActiveRole()->role_code == UsersRoles::NEW_USER;
        });

    }
}