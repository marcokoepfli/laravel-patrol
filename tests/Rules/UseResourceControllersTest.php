<?php

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\ProjectContext;
use MarcoKoepfli\LaravelPatrol\Rules\UseResourceControllers;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/patrol-test-'.uniqid();
    mkdir($this->tempDir.'/routes', 0777, true);
});

afterEach(function () {
    cleanDir($this->tempDir);
});

it('detects controller with 3+ individual routes', function () {
    file_put_contents($this->tempDir.'/routes/web.php', '<?php
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get("/users", [UserController::class, "index"]);
Route::get("/users/create", [UserController::class, "create"]);
Route::post("/users", [UserController::class, "store"]);
Route::get("/users/{user}", [UserController::class, "show"]);
Route::delete("/users/{user}", [UserController::class, "destroy"]);
');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['routes'], []);
    $rule = new UseResourceControllers;
    $results = $rule->check($context);

    expect($results)->toHaveCount(1)
        ->and($results[0]->message)->toContain('UserController')
        ->and($results[0]->message)->toContain('5 individual route definitions');
});

it('allows Route::resource()', function () {
    file_put_contents($this->tempDir.'/routes/web.php', '<?php
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::resource("users", UserController::class);
');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['routes'], []);
    $rule = new UseResourceControllers;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('allows fewer than 3 individual routes per controller', function () {
    file_put_contents($this->tempDir.'/routes/web.php', '<?php
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get("/login", [AuthController::class, "showLoginForm"]);
Route::post("/login", [AuthController::class, "login"]);
');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['routes'], []);
    $rule = new UseResourceControllers;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('flags multiple controllers separately', function () {
    file_put_contents($this->tempDir.'/routes/web.php', '<?php
use App\Http\Controllers\UserController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get("/users", [UserController::class, "index"]);
Route::post("/users", [UserController::class, "store"]);
Route::delete("/users/{user}", [UserController::class, "destroy"]);

Route::get("/posts", [PostController::class, "index"]);
Route::post("/posts", [PostController::class, "store"]);
Route::put("/posts/{post}", [PostController::class, "update"]);
Route::delete("/posts/{post}", [PostController::class, "destroy"]);
');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['routes'], []);
    $rule = new UseResourceControllers;
    $results = $rule->check($context);

    expect($results)->toHaveCount(2);
});

it('does not flag closure-based routes', function () {
    file_put_contents($this->tempDir.'/routes/web.php', '<?php
use Illuminate\Support\Facades\Route;

Route::get("/", function () {
    return view("welcome");
});
Route::get("/about", function () {
    return view("about");
});
Route::get("/contact", function () {
    return view("contact");
});
Route::post("/contact", function () {
    return redirect("/thanks");
});
');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['routes'], []);
    $rule = new UseResourceControllers;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('respects @patrol-ignore', function () {
    file_put_contents($this->tempDir.'/routes/web.php', '<?php
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// @patrol-ignore
Route::get("/users", [UserController::class, "index"]);
Route::post("/users", [UserController::class, "store"]);
Route::delete("/users/{user}", [UserController::class, "destroy"]);
');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['routes'], []);
    $rule = new UseResourceControllers;
    $results = $rule->check($context);

    // The first route is ignored, so only 2 routes for UserController, which is < 3
    expect($results)->toBeEmpty();
});

it('handles empty route files', function () {
    file_put_contents($this->tempDir.'/routes/web.php', '<?php

use Illuminate\Support\Facades\Route;
');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['routes'], []);
    $rule = new UseResourceControllers;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});
