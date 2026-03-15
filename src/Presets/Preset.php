<?php

namespace MarcoKoepfli\LaravelPatrol\Presets;

interface Preset
{
    /**
     * Return array of rule FQCNs that are enabled in this preset.
     *
     * @return string[]
     */
    public function rules(): array;
}
