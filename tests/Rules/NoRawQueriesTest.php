<?php

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\Enums\Severity;
use MarcoKoepfli\LaravelPatrol\ProjectContext;
use MarcoKoepfli\LaravelPatrol\Rules\NoRawQueries;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/patrol-test-'.uniqid();
    mkdir($this->tempDir.'/app/Models', 0777, true);
    mkdir($this->tempDir.'/app/Http/Controllers', 0777, true);
    mkdir($this->tempDir.'/app/Services', 0777, true);
});

afterEach(function () {
    cleanDir($this->tempDir);
});

it('detects DB::select() in app code', function () {
    file_put_contents($this->tempDir.'/app/Services/ReportService.php', '<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReportService {
    public function getReport() {
        return DB::select("SELECT * FROM users WHERE active = ?", [1]);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoRawQueries;
    $results = $rule->check($context);

    expect($results)->toHaveCount(1)
        ->and($results[0]->message)->toContain('DB::select()')
        ->and($results[0]->severity)->toBe(Severity::Info);
});

it('detects DB::raw() in app code', function () {
    file_put_contents($this->tempDir.'/app/Models/User.php', '<?php
namespace App\Models;

use Illuminate\Support\Facades\DB;

class User {
    public function scopeActive($query) {
        return $query->select(DB::raw("COUNT(*) as count"));
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoRawQueries;
    $results = $rule->check($context);

    expect($results)->toHaveCount(1)
        ->and($results[0]->message)->toContain('DB::raw()');
});

it('detects all raw query methods', function () {
    file_put_contents($this->tempDir.'/app/Services/DataService.php', '<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class DataService {
    public function run() {
        DB::select("SELECT 1");
        DB::insert("INSERT INTO t VALUES (1)");
        DB::update("UPDATE t SET x = 1");
        DB::delete("DELETE FROM t WHERE id = 1");
        DB::statement("ALTER TABLE t ADD COLUMN x INT");
        DB::raw("COUNT(*)");
        DB::unprepared("DROP TABLE IF EXISTS temp");
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoRawQueries;
    $results = $rule->check($context);

    expect($results)->toHaveCount(7);
});

it('allows Eloquent queries', function () {
    file_put_contents($this->tempDir.'/app/Services/UserService.php', '<?php
namespace App\Services;

use App\Models\User;

class UserService {
    public function getActiveUsers() {
        return User::where("active", true)
            ->orderBy("name")
            ->with("posts")
            ->paginate(15);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoRawQueries;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('does not match DB calls that are not raw queries', function () {
    file_put_contents($this->tempDir.'/app/Services/DbService.php', '<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class DbService {
    public function run() {
        DB::table("users")->where("active", true)->get();
        DB::connection("mysql")->table("users")->get();
        DB::transaction(function () {});
        DB::beginTransaction();
        DB::commit();
        DB::rollBack();
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoRawQueries;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('respects @patrol-ignore', function () {
    file_put_contents($this->tempDir.'/app/Services/LegacyService.php', '<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class LegacyService {
    public function complexReport() {
        // @patrol-ignore
        return DB::select("SELECT complex query here");
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoRawQueries;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('handles files without DB usage', function () {
    file_put_contents($this->tempDir.'/app/Services/PureService.php', '<?php
namespace App\Services;

class PureService {
    public function calculate(int $a, int $b): int {
        return $a + $b;
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoRawQueries;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});
