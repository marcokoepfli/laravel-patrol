<?php

namespace MarcoKoepfli\LaravelPatrol\Presets;

use MarcoKoepfli\LaravelPatrol\Rules\NoBusinessLogicInControllers;
use MarcoKoepfli\LaravelPatrol\Rules\NoEnvOutsideConfig;
use MarcoKoepfli\LaravelPatrol\Rules\NoRawQueries;
use MarcoKoepfli\LaravelPatrol\Rules\UseBladeComponents;
use MarcoKoepfli\LaravelPatrol\Rules\UseFormRequests;
use MarcoKoepfli\LaravelPatrol\Rules\UseResourceControllers;

class StrictPreset implements Preset
{
    public function rules(): array
    {
        return [
            NoEnvOutsideConfig::class,
            UseFormRequests::class,
            NoRawQueries::class,
            UseResourceControllers::class,
            UseBladeComponents::class,
            NoBusinessLogicInControllers::class,
        ];
    }
}
