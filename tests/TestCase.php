<?php

namespace MarcoKoepfli\LaravelPatrol\Tests;

use MarcoKoepfli\LaravelPatrol\PatrolServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            PatrolServiceProvider::class,
        ];
    }
}
