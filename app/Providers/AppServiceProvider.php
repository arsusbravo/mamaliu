<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Auth\UsernameUserProvider;

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
        Auth::provider('username', function ($app, array $config) {
            return new UsernameUserProvider($app['hash'], $config['model']);
        });

        // Production runs on an older MySQL/MariaDB without InnoDB large-prefix
        // support (767-byte index key limit) — cap default string length so
        // utf8mb4 unique/indexed varchar(255) columns don't exceed it.
        Schema::defaultStringLength(191);
    }
}
