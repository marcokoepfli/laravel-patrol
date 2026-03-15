<?php

namespace MarcoKoepfli\LaravelPatrol\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \MarcoKoepfli\LaravelPatrol\Patrol
 */
class Patrol extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \MarcoKoepfli\LaravelPatrol\Patrol::class;
    }
}
