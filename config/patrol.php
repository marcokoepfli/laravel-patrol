<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Version
    |--------------------------------------------------------------------------
    |
    | The Laravel version your application uses. Rules will be filtered
    | based on this version, and documentation links will point to the
    | correct version of the Laravel docs.
    |
    */

    'version' => 12,

    /*
    |--------------------------------------------------------------------------
    | Preset
    |--------------------------------------------------------------------------
    |
    | Presets define which rules are enabled by default.
    | Available: "strict", "recommended", "relaxed"
    |
    */

    'preset' => 'recommended',

    /*
    |--------------------------------------------------------------------------
    | Rule Overrides
    |--------------------------------------------------------------------------
    |
    | Override the preset by enabling or disabling specific rules.
    | Keys are rule class FQCNs, values are booleans.
    |
    | Example:
    | \MarcoKoepfli\LaravelPatrol\Rules\NoEnvOutsideConfig::class => false,
    |
    */

    'rules' => [],

    /*
    |--------------------------------------------------------------------------
    | Paths to Scan
    |--------------------------------------------------------------------------
    |
    | Directories to scan, relative to your application's base path.
    |
    */

    'paths' => [
        'app',
        'routes',
        'config',
        'resources',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | Directories to exclude from scanning.
    |
    */

    'exclude' => [
        'vendor',
        'node_modules',
        'storage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Rules
    |--------------------------------------------------------------------------
    |
    | Register your own rule classes here. They must implement
    | \MarcoKoepfli\LaravelPatrol\Contracts\Rule.
    |
    */

    'custom_rules' => [],

];
