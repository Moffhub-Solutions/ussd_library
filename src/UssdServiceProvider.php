<?php

use Illuminate\Support\ServiceProvider;

class UssdServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/Config/ussd.php' => config_path('ussd.php'),
        ], 'config');
    }
}
