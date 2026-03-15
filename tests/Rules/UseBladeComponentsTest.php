<?php

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\Enums\Severity;
use MarcoKoepfli\LaravelPatrol\ProjectContext;
use MarcoKoepfli\LaravelPatrol\Rules\UseBladeComponents;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/patrol-test-'.uniqid();
    mkdir($this->tempDir.'/resources/views/partials', 0777, true);
});

afterEach(function () {
    cleanDir($this->tempDir);
});

it('detects @include in blade files', function () {
    file_put_contents($this->tempDir.'/resources/views/home.blade.php',
        '<div>
    @include("partials.header")
    <p>Content</p>
    @include("partials.footer")
</div>');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['resources'], []);
    $rule = new UseBladeComponents;
    $results = $rule->check($context);

    expect($results)->toHaveCount(2)
        ->and($results[0]->message)->toContain('partials.header')
        ->and($results[0]->severity)->toBe(Severity::Info)
        ->and($results[1]->message)->toContain('partials.footer');
});

it('does not flag @includeIf, @includeWhen, @includeFirst, @includeUnless', function () {
    file_put_contents($this->tempDir.'/resources/views/conditional.blade.php',
        '<div>
    @includeIf("partials.optional")
    @includeWhen($showSidebar, "partials.sidebar")
    @includeFirst(["custom.header", "default.header"])
    @includeUnless($hideFooter, "partials.footer")
</div>');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['resources'], []);
    $rule = new UseBladeComponents;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('allows blade component syntax', function () {
    file_put_contents($this->tempDir.'/resources/views/modern.blade.php',
        '<div>
    <x-header />
    <x-alert type="warning" :message="$message" />
    <x-layouts.app>
        <p>Content</p>
    </x-layouts.app>
</div>');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['resources'], []);
    $rule = new UseBladeComponents;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('respects @patrol-ignore on same line', function () {
    file_put_contents($this->tempDir.'/resources/views/ignored.blade.php',
        '<div>
    @include("partials.legacy") {{-- @patrol-ignore --}}
    <p>Some content</p>
    @include("partials.other")
</div>');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['resources'], []);
    $rule = new UseBladeComponents;
    $results = $rule->check($context);

    expect($results)->toHaveCount(1)
        ->and($results[0]->message)->toContain('partials.other');
});

it('respects @patrol-ignore on line above', function () {
    file_put_contents($this->tempDir.'/resources/views/ignored2.blade.php',
        '<div>
    {{-- @patrol-ignore --}}
    @include("partials.legacy")
</div>');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['resources'], []);
    $rule = new UseBladeComponents;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('only scans .blade.php files', function () {
    file_put_contents($this->tempDir.'/resources/views/test.php',
        '<?php echo "@include(\"test\")"; ?>');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['resources'], []);
    $rule = new UseBladeComponents;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('handles empty blade files', function () {
    file_put_contents($this->tempDir.'/resources/views/empty.blade.php', '');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['resources'], []);
    $rule = new UseBladeComponents;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('handles @include with complex view names', function () {
    file_put_contents($this->tempDir.'/resources/views/complex.blade.php',
        '<div>
    @include("components.cards.user-card")
</div>');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['resources'], []);
    $rule = new UseBladeComponents;
    $results = $rule->check($context);

    expect($results)->toHaveCount(1)
        ->and($results[0]->message)->toContain('components.cards.user-card');
});
