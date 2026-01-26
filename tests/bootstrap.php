<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log as LogFacade;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = new Container;
Facade::setFacadeApplication($app);

$app->instance('config', new ConfigRepository([]));

$app->singleton('cache.store', fn (): ArrayStore => new ArrayStore);
$app->singleton('cache', fn ($app): CacheRepository => new CacheRepository($app->make('cache.store')));
CacheFacade::swap($app['cache']);

$app->singleton(LoggerInterface::class, fn (): NullLogger => new NullLogger);
$app->singleton('log', fn ($app) => $app->make(LoggerInterface::class));
LogFacade::swap($app['log']);

date_default_timezone_set('UTC');
ini_set('memory_limit', '2G');
