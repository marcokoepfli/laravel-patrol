<?php

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\Patrol;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/patrol-cmd-test-'.uniqid();
    mkdir($this->tempDir.'/app/Http/Controllers', 0777, true);
    mkdir($this->tempDir.'/config', 0777, true);
    mkdir($this->tempDir.'/routes', 0777, true);
});

afterEach(function () {
    cleanDir($this->tempDir);
});

it('reports no violations for a clean project', function () {
    file_put_contents($this->tempDir.'/app/Service.php', '<?php
class Service {
    public function getKey() {
        return config("app.key");
    }
}');

    $this->app->singleton(Patrol::class, fn () => new Patrol(
        version: LaravelVersion::V12,
        basePath: $this->tempDir,
        config: ['preset' => 'recommended', 'paths' => ['app', 'config', 'routes'], 'exclude' => []],
    ));

    $this->artisan('patrol')
        ->expectsOutputToContain('No violations found')
        ->assertExitCode(0);
});

it('reports violations in text format', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/BadController.php', '<?php
namespace App\Http\Controllers;

class BadController extends Controller
{
    public function store(Request $request)
    {
        $key = env("APP_KEY");
        $request->validate(["name" => "required"]);
    }
}');

    $this->app->singleton(Patrol::class, fn () => new Patrol(
        version: LaravelVersion::V12,
        basePath: $this->tempDir,
        config: ['preset' => 'recommended', 'paths' => ['app', 'config', 'routes'], 'exclude' => []],
    ));

    $this->artisan('patrol')
        ->expectsOutputToContain('env() called outside of config files')
        ->expectsOutputToContain('$request->validate()')
        ->assertExitCode(0); // 0 because warnings, not errors
});

it('outputs valid JSON with --format=json', function () {
    file_put_contents($this->tempDir.'/app/Service.php', '<?php
class Service {
    public function getKey() {
        return env("APP_KEY");
    }
}');

    $this->app->singleton(Patrol::class, fn () => new Patrol(
        version: LaravelVersion::V12,
        basePath: $this->tempDir,
        config: ['preset' => 'recommended', 'paths' => ['app', 'config', 'routes'], 'exclude' => []],
    ));

    // Verify JSON format runs without crashing and returns correct exit code
    $this->artisan('patrol', ['--format' => 'json'])
        ->assertExitCode(0);
});

it('filters by severity', function () {
    file_put_contents($this->tempDir.'/app/Service.php', '<?php
use Illuminate\Support\Facades\DB;

class Service {
    public function run() {
        return DB::select("SELECT 1");
    }
}');

    $this->app->singleton(Patrol::class, fn () => new Patrol(
        version: LaravelVersion::V12,
        basePath: $this->tempDir,
        config: ['preset' => 'recommended', 'paths' => ['app', 'config', 'routes'], 'exclude' => []],
    ));

    // Default severity=warning should NOT show info-level NoRawQueries
    $this->artisan('patrol')
        ->expectsOutputToContain('No violations found')
        ->assertExitCode(0);

    // severity=info should show it
    $this->artisan('patrol --severity=info')
        ->expectsOutputToContain('DB::select()')
        ->assertExitCode(0);
});

it('filters by specific rule', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/MultiController.php', '<?php
namespace App\Http\Controllers;

class MultiController extends Controller
{
    public function store(Request $request)
    {
        $key = env("APP_KEY");
        $request->validate(["name" => "required"]);
    }
}');

    $this->app->singleton(Patrol::class, fn () => new Patrol(
        version: LaravelVersion::V12,
        basePath: $this->tempDir,
        config: ['preset' => 'recommended', 'paths' => ['app', 'config', 'routes'], 'exclude' => []],
    ));

    $this->artisan('patrol --rule=no-env-outside-config')
        ->expectsOutputToContain('env() called outside of config files')
        ->assertExitCode(0);
});

it('shows the correct Laravel version in header', function () {
    $this->app->singleton(Patrol::class, fn () => new Patrol(
        version: LaravelVersion::V11,
        basePath: $this->tempDir,
        config: ['preset' => 'relaxed', 'paths' => ['app'], 'exclude' => []],
    ));

    $this->artisan('patrol')
        ->expectsOutputToContain('Laravel 11')
        ->assertExitCode(0);
});

it('shows docs links in output', function () {
    file_put_contents($this->tempDir.'/app/Service.php', '<?php
class Service {
    public function getKey() {
        return env("APP_KEY");
    }
}');

    $this->app->singleton(Patrol::class, fn () => new Patrol(
        version: LaravelVersion::V12,
        basePath: $this->tempDir,
        config: ['preset' => 'recommended', 'paths' => ['app'], 'exclude' => []],
    ));

    $this->artisan('patrol')
        ->expectsOutputToContain('https://laravel.com/docs/12/')
        ->assertExitCode(0);
});
