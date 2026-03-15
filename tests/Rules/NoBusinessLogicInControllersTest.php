<?php

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\ProjectContext;
use MarcoKoepfli\LaravelPatrol\Rules\NoBusinessLogicInControllers;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/patrol-test-'.uniqid();
    mkdir($this->tempDir.'/app/Http/Controllers', 0777, true);
});

afterEach(function () {
    cleanDir($this->tempDir);
});

it('flags methods with too many statements', function () {
    // Generate a controller with a fat method (12+ statements)
    file_put_contents($this->tempDir.'/app/Http/Controllers/FatController.php', '<?php
namespace App\Http\Controllers;

class FatController extends Controller
{
    public function store(Request $request)
    {
        $name = $request->input("name");
        $email = $request->input("email");
        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = bcrypt("password");
        $user->save();
        $profile = new Profile();
        $profile->user_id = $user->id;
        $profile->bio = "Default bio";
        $profile->save();
        event(new UserRegistered($user));
        Mail::to($user)->send(new WelcomeMail($user));
        return redirect("/dashboard");
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoBusinessLogicInControllers;
    $results = $rule->check($context);

    expect($results)->toHaveCount(1)
        ->and($results[0]->message)->toContain('store()')
        ->and($results[0]->suggestion)->toContain('Action or Service class');
});

it('allows thin controller methods', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/ThinController.php', '<?php
namespace App\Http\Controllers;

class ThinController extends Controller
{
    public function index()
    {
        $users = User::paginate(15);
        return view("users.index", compact("users"));
    }

    public function store(StoreUserRequest $request)
    {
        $user = User::create($request->validated());
        return redirect()->route("users.show", $user);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoBusinessLogicInControllers;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('skips __construct method', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/DiController.php', '<?php
namespace App\Http\Controllers;

class DiController extends Controller
{
    public function __construct(
        private UserService $userService,
        private PostService $postService,
        private CommentService $commentService,
        private TagService $tagService,
        private CategoryService $categoryService,
        private MediaService $mediaService,
        private NotificationService $notificationService,
        private CacheService $cacheService,
        private LogService $logService,
        private AnalyticsService $analyticsService,
        private SearchService $searchService,
    ) {}
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoBusinessLogicInControllers;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('counts nested statements properly', function () {
    // Method with if/else/foreach that totals > 10 statements
    file_put_contents($this->tempDir.'/app/Http/Controllers/NestedController.php', '<?php
namespace App\Http\Controllers;

class NestedController extends Controller
{
    public function process(Request $request)
    {
        $data = $request->all();
        if ($data["type"] === "admin") {
            $user = User::find($data["id"]);
            $user->role = "admin";
            $user->save();
            foreach ($data["permissions"] as $perm) {
                $user->permissions()->attach($perm);
            }
        } else {
            $user = User::find($data["id"]);
            $user->role = "user";
            $user->save();
        }
        return response()->json($user);
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoBusinessLogicInControllers;
    $results = $rule->check($context);

    expect($results)->toHaveCount(1)
        ->and($results[0]->message)->toContain('process()');
});

it('only checks public methods', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/PrivateController.php', '<?php
namespace App\Http\Controllers;

class PrivateController extends Controller
{
    public function index()
    {
        return $this->buildResponse();
    }

    private function buildResponse()
    {
        $a = 1; $b = 2; $c = 3; $d = 4; $e = 5;
        $f = 6; $g = 7; $h = 8; $i = 9; $j = 10;
        $k = 11; $l = 12;
        return response()->json(compact("a", "b", "c"));
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoBusinessLogicInControllers;
    $results = $rule->check($context);

    // Only public methods are checked, private buildResponse should be ignored
    expect($results)->toBeEmpty();
});

it('respects @patrol-ignore on method', function () {
    file_put_contents($this->tempDir.'/app/Http/Controllers/IgnoredController.php', '<?php
namespace App\Http\Controllers;

class IgnoredController extends Controller
{
    /** @patrol-ignore */
    public function complexAction(Request $request)
    {
        $a = 1; $b = 2; $c = 3; $d = 4; $e = 5;
        $f = 6; $g = 7; $h = 8; $i = 9; $j = 10;
        $k = 11;
        return response()->json("ok");
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoBusinessLogicInControllers;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});

it('does not scan files outside Controllers directory', function () {
    mkdir($this->tempDir.'/app/Services', 0777, true);
    file_put_contents($this->tempDir.'/app/Services/BigService.php', '<?php
namespace App\Services;

class BigService
{
    public function process()
    {
        $a = 1; $b = 2; $c = 3; $d = 4; $e = 5;
        $f = 6; $g = 7; $h = 8; $i = 9; $j = 10;
        $k = 11; $l = 12;
        return true;
    }
}');

    $context = new ProjectContext(LaravelVersion::V12, $this->tempDir, ['app'], []);
    $rule = new NoBusinessLogicInControllers;
    $results = $rule->check($context);

    expect($results)->toBeEmpty();
});
