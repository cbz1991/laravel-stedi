<?php

namespace Cabuz\Stedi;

use Cabuz\Stedi\Commands\CheckCommand;
use Cabuz\Stedi\Commands\PayersCommand;
use Cabuz\Stedi\Http\Controllers\WebhookController;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class StediServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/stedi.php', 'stedi');

        $this->app->singleton(Stedi::class, fn ($app) => new Stedi(
            $app->make(HttpFactory::class),
            (array) $app['config']->get('stedi', []),
        ));

        $this->app->alias(Stedi::class, 'stedi');
    }

    public function boot(): void
    {
        $path = $this->app['config']->get('stedi.webhook.path');

        if (is_string($path) && $path !== '' && ! $this->app->routesAreCached()) {
            Route::post($path, WebhookController::class)
                ->middleware((array) $this->app['config']->get('stedi.webhook.middleware', []))
                ->name('stedi.webhook');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/stedi.php' => config_path('stedi.php'),
            ], 'stedi-config');

            $this->commands([
                CheckCommand::class,
                PayersCommand::class,
            ]);
        }
    }
}
