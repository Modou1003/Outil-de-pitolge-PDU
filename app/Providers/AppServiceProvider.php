<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // En production (derrière le proxy de Railway), forcer le HTTPS
        // pour que les assets Vue/Vite se chargent correctement.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
