<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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

        // L'administrateur a TOUS les droits, quelles que soient les permissions
        // attribuées en base. Couvre les middlewares de route (canAny) et les
        // vérifications can() dans les contrôleurs.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('admin') ? true : null;
        });

        // En production (derrière le proxy de Railway), forcer le HTTPS
        // pour que les assets Vue/Vite se chargent correctement.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
