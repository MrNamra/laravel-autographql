<?php

namespace MrNamra\AutoGraphQL\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use MrNamra\AutoGraphQL\GraphQLAutoPackageServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            GraphQLAutoPackageServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default config for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/test-app/database/migrations');
    }
}
