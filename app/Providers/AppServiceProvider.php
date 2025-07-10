<?php

namespace App\Providers;

use App\Listeners\EmailVerificationListener;
use Illuminate\Support\{ServiceProvider, Str};
use Laravel\Passport\Passport;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\{OpenApi, SecurityScheme};
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\{Schema, URL, Event};

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local'))
        {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('Debugbar', \Barryvdh\Debugbar\ServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        Scramble::routes(function (Route $route) {
            return Str::startsWith($route->uri, 'api/');
        });

        Scramble::afterOpenApiGenerated( function(OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });

        if (env('APP_ENV') == 'production')
        {
            URL::forceScheme('https');
        }
        Schema::defaultStringLength(191);

        Event::listen(EmailVerificationListener::class);

    }
}
