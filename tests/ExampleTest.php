<?php

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\Patrol;
use MarcoKoepfli\LaravelPatrol\Rules\NoEnvOutsideConfig;

it('can run patrol with no violations on empty project', function () {
    $tempDir = sys_get_temp_dir().'/patrol-test-'.uniqid();
    mkdir($tempDir.'/app', 0777, true);
    mkdir($tempDir.'/config', 0777, true);

    file_put_contents($tempDir.'/app/Service.php', '<?php
class Service {
    public function getKey() {
        return config("app.key");
    }
}');

    file_put_contents($tempDir.'/config/app.php', '<?php
return [
    "key" => env("APP_KEY"),
];');

    $patrol = new Patrol(
        version: LaravelVersion::V12,
        basePath: $tempDir,
        config: ['preset' => 'recommended', 'paths' => ['app', 'config'], 'exclude' => []],
    );

    $results = $patrol->run();

    expect($results)->toHaveCount(0);

    cleanDir($tempDir);
});

it('detects violations across multiple rules', function () {
    $tempDir = sys_get_temp_dir().'/patrol-test-'.uniqid();
    mkdir($tempDir.'/app/Http/Controllers', 0777, true);
    mkdir($tempDir.'/config', 0777, true);

    // env() outside config -> NoEnvOutsideConfig violation
    // $request->validate() -> UseFormRequests violation
    file_put_contents($tempDir.'/app/Http/Controllers/UserController.php', '<?php
namespace App\Http\Controllers;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $key = env("APP_KEY");
        $request->validate(["name" => "required"]);
        return response()->json(["ok" => true]);
    }
}');

    $patrol = new Patrol(
        version: LaravelVersion::V12,
        basePath: $tempDir,
        config: ['preset' => 'recommended', 'paths' => ['app', 'config'], 'exclude' => []],
    );

    $results = $patrol->run();

    expect($results->count())->toBeGreaterThanOrEqual(2);

    $ruleIds = array_unique(array_map(fn ($r) => $r->ruleId, $results->all()));
    expect($ruleIds)->toContain('no-env-outside-config')
        ->and($ruleIds)->toContain('use-form-requests');

    cleanDir($tempDir);
});

it('respects preset configuration', function () {
    $tempDir = sys_get_temp_dir().'/patrol-test-'.uniqid();
    mkdir($tempDir.'/app', 0777, true);

    $patrol = new Patrol(
        version: LaravelVersion::V12,
        basePath: $tempDir,
        config: ['preset' => 'strict'],
    );
    expect(count($patrol->rules()))->toBe(6);

    $patrol = new Patrol(
        version: LaravelVersion::V12,
        basePath: $tempDir,
        config: ['preset' => 'recommended'],
    );
    expect(count($patrol->rules()))->toBe(4);

    $patrol = new Patrol(
        version: LaravelVersion::V12,
        basePath: $tempDir,
        config: ['preset' => 'relaxed'],
    );
    expect(count($patrol->rules()))->toBe(1);

    cleanDir($tempDir);
});

it('allows disabling rules via config overrides', function () {
    $tempDir = sys_get_temp_dir().'/patrol-test-'.uniqid();
    mkdir($tempDir.'/app', 0777, true);

    $patrol = new Patrol(
        version: LaravelVersion::V12,
        basePath: $tempDir,
        config: [
            'preset' => 'recommended',
            'rules' => [
                NoEnvOutsideConfig::class => false,
            ],
        ],
    );

    $ruleIds = array_map(fn ($r) => $r->id(), $patrol->rules());
    expect($ruleIds)->not->toContain('no-env-outside-config');

    cleanDir($tempDir);
});
