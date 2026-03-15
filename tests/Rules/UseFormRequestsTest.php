<?php

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\ProjectContext;
use MarcoKoepfli\LaravelPatrol\Rules\UseFormRequests;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/patrol-test-'.uniqid();
    mkdir($this->tempDir.'/app/Http/Controllers', 0777, true);
});

afterEach(function () {
    cleanDir($this->tempDir);
});

it('detects $request->validate() in controllers', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/UserController.php', '<?php
namespace App\Http\Controllers;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required",
            "email" => "required|email",
        ]);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new UseFormRequests;
    $results = $rule->check($context);

    expect($results)->toHaveCount(1)
        ->and($results[0]->message)->toContain('$request->validate()')
        ->and($results[0]->line)->toBe(8);
});

it('detects Validator::make() in controllers', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/PostController.php', '<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "title" => "required",
        ]);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new UseFormRequests;
    $results = $rule->check($context);

    expect($results)->toHaveCount(1)
        ->and($results[0]->message)->toContain('Validator::make()');
});

it('detects $this->validate() in controllers', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/ItemController.php', '<?php
namespace App\Http\Controllers;

class ItemController extends Controller
{
    public function store(Request $request)
    {
        $this->validate($request, [
            "name" => "required",
        ]);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new UseFormRequests;
    $results = $rule->check($context);

    expect($results)->toHaveCount(1)
        ->and($results[0]->message)->toContain('$this->validate()');
});

it('allows Form Request type-hinted parameters', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/GoodController.php', '<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;

class GoodController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();
        User::create($validated);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new UseFormRequests;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('does not flag validate() on other objects', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/CustomController.php', '<?php
namespace App\Http\Controllers;

class CustomController extends Controller
{
    public function store()
    {
        $service = new ValidationService();
        $service->validate($data);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new UseFormRequests;
    $results = $rule->check($context);

    // $service->validate() should NOT be flagged (it's not $request->validate)
    expect($results)->toBeEmpty();
});

it('respects @patrol-ignore on validate calls', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/IgnoredController.php', '<?php
namespace App\Http\Controllers;

class IgnoredController extends Controller
{
    public function store(Request $request)
    {
        // @patrol-ignore
        $validated = $request->validate([
            "name" => "required",
        ]);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new UseFormRequests;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('handles multiple violations in one file', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/MultiController.php', '<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;

class MultiController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(["name" => "required"]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), ["name" => "required"]);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new UseFormRequests;
    $results = $rule->check($context);

    expect($results)->toHaveCount(2);
});

it('does not scan files outside Controllers directory', function () {
    mkdir($this->tempDir.'/app/Services', 0777, true);
    file_put_contents($this->tempDir.'/app/Services/MyService.php', '<?php
class MyService {
    public function validate(Request $request) {
        $request->validate(["name" => "required"]);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new UseFormRequests;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});
