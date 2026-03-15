<?php

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\ProjectContext;
use MarcoKoepfli\LaravelPatrol\Rules\NoEnvOutsideConfig;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/patrol-test-'.uniqid();
    mkdir($this->tempDir.'/app', 0777, true);
    mkdir($this->tempDir.'/config', 0777, true);
});

afterEach(function () {
    cleanDir($this->tempDir);
});

it('detects env() in app code', function () {
    file_put_contents($this->tempDir.'/app/Service.php', '<?php
class Service {
    public function getKey() {
        return env("APP_KEY");
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app', 'config'], []);
    $rule = new NoEnvOutsideConfig;
    $results = $rule->check($context);

    expect($results)->toHaveCount(1)
        ->and($results[0]->file)->toBe('app/Service.php')
        ->and($results[0]->line)->toBe(4)
        ->and($results[0]->ruleId)->toBe('no-env-outside-config');
});

it('allows env() in config files', function () {
    file_put_contents($this->tempDir.'/config/app.php', '<?php
return [
    "key" => env("APP_KEY"),
    "debug" => env("APP_DEBUG", false),
    "url" => env("APP_URL", "http://localhost"),
];');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app', 'config'], []);
    $rule = new NoEnvOutsideConfig;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('does not match variables named $env', function () {
    file_put_contents($this->tempDir.'/app/Service.php', '<?php
class Service {
    public function handle() {
        $env = "production";
        $environment = getenv("APP_KEY");
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoEnvOutsideConfig;
    $results = $rule->check($context);

    // getenv() is a different function, should not be flagged
    // $env = ... is a variable assignment, not a function call
    expect($results)->toBeEmpty();
});

it('does not match env in strings or comments', function () {
    file_put_contents($this->tempDir.'/app/Service.php', '<?php
class Service {
    public function handle() {
        // env("APP_KEY") is used in config
        $x = "use env() for configuration";
        /* env("test") */
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoEnvOutsideConfig;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('detects multiple env() calls in same file', function () {
    file_put_contents($this->tempDir.'/app/Service.php', '<?php
class Service {
    public function a() {
        return env("KEY_A");
    }
    public function b() {
        return env("KEY_B");
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoEnvOutsideConfig;
    $results = $rule->check($context);

    expect($results)->toHaveCount(2)
        ->and($results[0]->line)->toBe(4)
        ->and($results[1]->line)->toBe(7);
});

it('respects @patrol-ignore comment', function () {
    file_put_contents($this->tempDir.'/app/Service.php', '<?php
class Service {
    public function handle() {
        // @patrol-ignore
        return env("APP_KEY");
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoEnvOutsideConfig;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('passes when only config() is used', function () {
    file_put_contents($this->tempDir.'/app/Service.php', '<?php
class Service {
    public function getKey() {
        return config("app.key");
    }
    public function getUrl() {
        return config("app.url", "http://localhost");
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoEnvOutsideConfig;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('handles empty files gracefully', function () {
    file_put_contents($this->tempDir.'/app/Empty.php', '<?php');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoEnvOutsideConfig;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('handles syntax errors gracefully', function () {
    file_put_contents($this->tempDir.'/app/Broken.php', '<?php
class Broken {
    this is not valid php {{{{
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoEnvOutsideConfig;
    $results = $rule->check($context);

    // Should not crash, just return empty
    expect($results)->toBeEmpty();
});

it('has correct docs URL per version', function () {
    $rule = new NoEnvOutsideConfig;

    expect($rule->docsUrl(LaravelVersion::V11))->toContain('/docs/11/')
        ->and($rule->docsUrl(LaravelVersion::V12))->toContain('/docs/12/');
});
