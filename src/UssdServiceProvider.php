<?php

declare(strict_types=1);

namespace Moffhub\Ussd;

use Illuminate\Support\ServiceProvider;

class UssdServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/Config/ussd.php' => config_path('ussd.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_ussd_tables' => database_path('migrations/create_ussd_tables.php'),
        ], 'migrations');
    }
}
