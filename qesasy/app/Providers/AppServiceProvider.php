<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Log all incoming requests in development
        if ($this->app->environment('local')) {
            \Illuminate\Support\Facades\DB::listen(function ($query) {
                \Illuminate\Support\Facades\Log::info('Query: ' . $query->sql);
            });
        }

        // Log API requests for debugging
        if (request()->is('api/*')) {
            \Illuminate\Support\Facades\Log::info('API Request', [
                'method' => request()->method(),
                'url' => request()->fullUrl(),
                'headers' => request()->headers->all(),
                'body' => request()->all(),
            ]);
        }
    }
}