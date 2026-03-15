<?php

namespace MarcoKoepfli\LaravelPatrol\Presets;

use MarcoKoepfli\LaravelPatrol\Rules\NoEnvOutsideConfig;

class RelaxedPreset implements Preset
{
    public function rules(): array
    {
        return [
            NoEnvOutsideConfig::class,
        ];
    }
}
