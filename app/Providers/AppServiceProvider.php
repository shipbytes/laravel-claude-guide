<?php

namespace App\Providers;

use Anthropic\Client;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One client for the whole request, built from config/claude.php. Anything that
        // needs Claude asks the container for Anthropic\Client; tests swap it for a fake.
        $this->app->singleton(Client::class, fn () => new Client(
            apiKey: config('claude.api_key'),
            requestOptions: [
                'timeout' => config('claude.timeout'),
                'maxRetries' => config('claude.max_retries'),
            ],
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
