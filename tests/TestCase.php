<?php

namespace Cabuz\Stedi\Tests;

use Cabuz\Stedi\Facades\Stedi;
use Cabuz\Stedi\StediServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [StediServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Stedi' => Stedi::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('stedi.api_key', 'test_key');
        $app['config']->set('stedi.retry_delay', 0);
    }
}
