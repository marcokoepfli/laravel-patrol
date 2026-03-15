<?php

use MarcoKoepfli\LaravelPatrol\Analyzers\FileAnalyzer;

beforeEach(function () {
    $this->analyzer = new FileAnalyzer;
});

it('parses valid PHP code', function () {
    $ast = $this->analyzer->parse('<?php echo "hello";');
    expect($ast)->not->toBeNull()->and($ast)->toBeArray();
});

it('returns null for invalid PHP code', function () {
    $ast = $this->analyzer->parse('<?php class { this is not valid php {{{{ }');
    expect($ast)->toBeNull();
});

it('caches parsed results', function () {
    $code = '<?php echo "hello";';
    $ast1 = $this->analyzer->parse($code);
    $ast2 = $this->analyzer->parse($code);
    expect($ast1)->toBe($ast2);
});

it('finds function calls by name', function () {
    $ast = $this->analyzer->parse('<?php
        env("APP_KEY");
        config("app.key");
        env("DB_HOST");
    ');

    $envCalls = $this->analyzer->findFunctionCalls($ast, 'env');
    expect($envCalls)->toHaveCount(2);

    $configCalls = $this->analyzer->findFunctionCalls($ast, 'config');
    expect($configCalls)->toHaveCount(1);
});

it('does not match function calls in strings', function () {
    $ast = $this->analyzer->parse('<?php
        $x = "env(APP_KEY)";
        $y = \'env("test")\';
    ');

    $calls = $this->analyzer->findFunctionCalls($ast, 'env');
    expect($calls)->toBeEmpty();
});

it('does not match method calls as function calls', function () {
    $ast = $this->analyzer->parse('<?php
        $obj->env("key");
        SomeClass::env("key");
    ');

    $calls = $this->analyzer->findFunctionCalls($ast, 'env');
    expect($calls)->toBeEmpty();
});

it('finds static method calls', function () {
    $ast = $this->analyzer->parse('<?php
        DB::select("SELECT 1");
        DB::table("users")->get();
        Cache::get("key");
    ');

    $dbSelect = $this->analyzer->findStaticCalls($ast, 'DB', 'select');
    expect($dbSelect)->toHaveCount(1);

    $allDb = $this->analyzer->findStaticCalls($ast, 'DB');
    expect($allDb)->toHaveCount(2);

    $cache = $this->analyzer->findStaticCalls($ast, 'Cache', 'get');
    expect($cache)->toHaveCount(1);
});

it('finds method calls on variables', function () {
    $ast = $this->analyzer->parse('<?php
        $request->validate(["name" => "required"]);
        $request->input("name");
        $other->validate(["name" => "required"]);
    ');

    $requestValidate = $this->analyzer->findMethodCalls($ast, 'request', 'validate');
    expect($requestValidate)->toHaveCount(1);

    $otherValidate = $this->analyzer->findMethodCalls($ast, 'other', 'validate');
    expect($otherValidate)->toHaveCount(1);
});

it('finds class methods by visibility', function () {
    $ast = $this->analyzer->parse('<?php
    class MyClass {
        public function publicMethod() {}
        protected function protectedMethod() {}
        private function privateMethod() {}
        public function anotherPublic() {}
    }');

    $publicMethods = $this->analyzer->findClassMethods($ast, 'public');
    expect($publicMethods)->toHaveCount(2);

    $privateMethods = $this->analyzer->findClassMethods($ast, 'private');
    expect($privateMethods)->toHaveCount(1);

    $allMethods = $this->analyzer->findClassMethods($ast, null);
    expect($allMethods)->toHaveCount(4);
});

it('detects @patrol-ignore via source code lines', function () {
    $code = '<?php
// @patrol-ignore
env("KEY");
';
    $ast = $this->analyzer->parse($code);
    $calls = $this->analyzer->findFunctionCalls($ast, 'env');
    expect($calls)->toHaveCount(1);
    expect($this->analyzer->hasIgnoreComment($calls[0], $code))->toBeTrue();
});

it('detects @patrol-ignore on the same line', function () {
    $code = '<?php
env("KEY"); // @patrol-ignore
';
    $ast = $this->analyzer->parse($code);
    $calls = $this->analyzer->findFunctionCalls($ast, 'env');
    expect($calls)->toHaveCount(1);
    expect($this->analyzer->hasIgnoreComment($calls[0], $code))->toBeTrue();
});

it('does not detect @patrol-ignore when absent', function () {
    $code = '<?php
env("KEY");
';
    $ast = $this->analyzer->parse($code);
    $calls = $this->analyzer->findFunctionCalls($ast, 'env');
    expect($calls)->toHaveCount(1);
    expect($this->analyzer->hasIgnoreComment($calls[0], $code))->toBeFalse();
});

it('counts statements in methods', function () {
    $ast = $this->analyzer->parse('<?php
    class MyClass {
        public function simple() {
            $a = 1;
            $b = 2;
            return $a + $b;
        }
    }');

    $methods = $this->analyzer->findClassMethods($ast, 'public');
    expect($methods)->toHaveCount(1);
    expect($this->analyzer->countStatements($methods[0]))->toBe(3);
});

it('counts nested statements recursively', function () {
    $ast = $this->analyzer->parse('<?php
    class MyClass {
        public function complex() {
            $x = 1;
            if ($x > 0) {
                $y = 2;
                foreach ([1,2] as $item) {
                    echo $item;
                }
            } else {
                $z = 3;
            }
            return $x;
        }
    }');

    $methods = $this->analyzer->findClassMethods($ast, 'public');
    $count = $this->analyzer->countStatements($methods[0]);

    // $x = 1, if (with $y, foreach (with echo), else (with $z)), return $x
    // = 1 + 1 + (1 + 1 + (1)) + (1) + 1 = 7
    expect($count)->toBe(7);
});

it('counts try-catch statements', function () {
    $ast = $this->analyzer->parse('<?php
    class MyClass {
        public function withTryCatch() {
            try {
                $a = 1;
                $b = 2;
            } catch (\Exception $e) {
                log($e);
            }
        }
    }');

    $methods = $this->analyzer->findClassMethods($ast, 'public');
    $count = $this->analyzer->countStatements($methods[0]);

    // try (with $a, $b) + catch (with log) = 1 + 2 + 1 = 4
    expect($count)->toBe(4);
});

it('returns 0 for abstract methods', function () {
    $ast = $this->analyzer->parse('<?php
    abstract class MyClass {
        abstract public function noBody(): void;
    }');

    $methods = $this->analyzer->findClassMethods($ast, 'public');
    expect($methods)->toHaveCount(1);
    expect($this->analyzer->countStatements($methods[0]))->toBe(0);
});

it('handles case-insensitive function names', function () {
    $ast = $this->analyzer->parse('<?php
        ENV("KEY");
        Env("KEY");
        env("KEY");
    ');

    $calls = $this->analyzer->findFunctionCalls($ast, 'env');
    // PHP function names are case-insensitive, parser lowercases them
    expect($calls)->toHaveCount(3);
});
