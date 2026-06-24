<?php

namespace App\Providers;

use App\Support\Config\AppSecrets;
use App\Support\Config\AppUrls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
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
        Config::set('jwt.secret', AppSecrets::getJwtSecret());
        Config::set('jwt.ttl', 480);
        Config::set('cors.allowed_origins', AppUrls::getCorsOrigins());

        Request::macro('authUser', function (): ?\App\Support\Auth\AuthUser {
            /** @var Request $this */
            $authUser = $this->attributes->get('authUser');

            return $authUser instanceof \App\Support\Auth\AuthUser ? $authUser : null;
        });
    }
}
