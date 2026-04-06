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
    }
}
