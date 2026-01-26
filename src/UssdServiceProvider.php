<?php

declare(strict_types=1);

namespace Moffhub\Ussd;

use Illuminate\Support\ServiceProvider;

class UssdServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole() && ! $this->app->environment('testing')) {
            if (function_exists('config_path')) {
                $this->publishes([
                    __DIR__.'/Config/ussd.php' => config_path('ussd.php'),
                ], 'config');
            }

            if (function_exists('database_path')) {
                $this->publishes([
                    __DIR__.'/../database/migrations/create_ussd_tables.php' => database_path('migrations/create_ussd_tables.php'),
                ], 'migrations');
            }
        }
    }

    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/ussd.php', 'ussd');
    }
}
