<?php

declare(strict_types=1);

namespace Moffhub\Ussd;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Moffhub\Ussd\Console\Commands\CleanupSessionsCommand;
use Moffhub\Ussd\Console\Commands\HealthCheckCommand;
use Moffhub\Ussd\Console\Commands\ListSessionsCommand;
use Moffhub\Ussd\Console\Commands\ManageAccessCommand;
use Moffhub\Ussd\Console\Commands\SimulateCommand;
use Moffhub\Ussd\Http\Controllers\AccessManagementController;
use Moffhub\Ussd\Http\Middleware\UssdAuthentication;
use Moffhub\Ussd\Http\Middleware\UssdRateLimit;
use Moffhub\Ussd\Security\RequestVerifier;
use Moffhub\Ussd\Security\SessionEncryptor;
use Moffhub\Ussd\Services\CircuitBreaker;
use Moffhub\Ussd\Services\TranslationService;

class UssdServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'ussd');

        // Only load migrations when not in testing environment
        // Tests that need migrations should load them explicitly
        if (! $this->app->environment('testing')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole() && ! $this->app->environment('testing')) {
            if (function_exists('config_path')) {
                $this->publishes([
                    __DIR__.'/Config/ussd.php' => config_path('ussd.php'),
                ], 'config');
            }

            if (function_exists('database_path')) {
                $this->publishes([
                    __DIR__.'/../database/migrations/2024_01_01_000000_create_ussd_tables.php' => database_path('migrations/2024_01_01_000000_create_ussd_tables.php'),
                ], 'migrations');
            }

            // Allow publishing language files
            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/ussd'),
            ], 'ussd-lang');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupSessionsCommand::class,
                ListSessionsCommand::class,
                ManageAccessCommand::class,
                HealthCheckCommand::class,
                SimulateCommand::class,
            ]);
        }

        // Register middleware aliases
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('ussd.auth', UssdAuthentication::class);
        $router->aliasMiddleware('ussd.rate-limit', UssdRateLimit::class);

        // Register admin API routes
        $this->registerAdminRoutes();
    }

    protected function registerAdminRoutes(): void
    {
        if (! config('ussd.admin.enabled', true)) {
            return;
        }

        $prefix = config('ussd.admin.prefix', 'ussd/admin');
        $middleware = config('ussd.admin.middleware', ['api', 'auth']);

        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(function (): void {
                $controller = AccessManagementController::class;

                Route::get('access-list', [$controller, 'index']);
                Route::post('access-list', [$controller, 'store']);
                Route::post('access-list/bulk', [$controller, 'bulkStore']);
                Route::delete('access-list/bulk', [$controller, 'bulkDestroy']);
                Route::get('access-list/export', [$controller, 'export']);
                Route::delete('access-list/{id}', [$controller, 'destroy'])->where('id', '[0-9]+');
                Route::get('rate-limits', [$controller, 'rateLimits']);
                Route::post('rate-limits/override', [$controller, 'rateLimitOverride']);
                Route::delete('rate-limits/override/{phone}', [$controller, 'removeRateLimitOverride']);
                Route::get('sessions', [$controller, 'sessions']);
            });
    }

    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/ussd.php', 'ussd');

        // Register UssdFramework as a singleton for the facade
        $this->app->singleton(UssdFramework::class, fn ($app): UssdFramework => new UssdFramework(config('ussd', [])));

        // Register Phase 4 services
        $this->app->singleton(TranslationService::class, fn (): TranslationService => new TranslationService);

        $this->app->singleton(RequestVerifier::class, fn (): RequestVerifier => new RequestVerifier);

        $this->app->singleton(SessionEncryptor::class, fn (): SessionEncryptor => new SessionEncryptor);

        $this->app->singleton(CircuitBreaker::class, fn (): CircuitBreaker => new CircuitBreaker);
    }
}
