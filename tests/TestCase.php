<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use UssdServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            UssdServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        //
    }
}
