<p align="center">
    <img src="art/logo.svg" width="550" alt="Laravel Patrol">
</p>

<p align="center">
    <a href="https://packagist.org/packages/marcokoepfli/laravel-patrol"><img src="https://img.shields.io/packagist/v/marcokoepfli/laravel-patrol.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://github.com/marcokoepfli/laravel-patrol/actions?query=workflow%3Arun-tests+branch%3Amain"><img src="https://img.shields.io/github/actions/workflow/status/marcokoepfli/laravel-patrol/run-tests.yml?branch=main&label=tests&style=flat-square" alt="Tests"></a>
    <a href="https://github.com/marcokoepfli/laravel-patrol/actions?query=workflow%3APHPStan+branch%3Amain"><img src="https://img.shields.io/github/actions/workflow/status/marcokoepfli/laravel-patrol/phpstan.yml?branch=main&label=phpstan&style=flat-square" alt="PHPStan"></a>
    <a href="https://packagist.org/packages/marcokoepfli/laravel-patrol"><img src="https://img.shields.io/packagist/dt/marcokoepfli/laravel-patrol.svg?style=flat-square" alt="Total Downloads"></a>
</p>

An opinionated linter that patrols your Laravel app for convention violations. It checks if your code follows "the Laravel way" based on official Laravel documentation and provides actionable improvement suggestions with links to the relevant docs.

## Why Patrol?

Tools like Larastan catch type errors. Pint fixes code style. **Patrol checks if you're actually using Laravel the way it was designed** — Form Requests instead of inline validation, config() instead of env(), Eloquent instead of raw queries, and more.

Every violation includes:
- A clear message explaining what's wrong
- A suggestion for how to fix it
- A link to the relevant Laravel docs section

## Installation

```bash
composer require --dev marcokoepfli/laravel-patrol
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag="patrol-config"
```

## Usage

```bash
php artisan patrol
```

Example output:

```
  ██████╗  █████╗ ████████╗██████╗  ██████╗ ██╗
  ██╔══██╗██╔══██╗╚══██╔══╝██╔══██╗██╔═══██╗██║
  ██████╔╝███████║   ██║   ██████╔╝██║   ██║██║
  ██╔═══╝ ██╔══██║   ██║   ██╔══██╗██║   ██║██║
  ██║     ██║  ██║   ██║   ██║  ██║╚██████╔╝███████╗
  ╚═╝     ╚═╝  ╚═╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚══════╝

  v1.0 · Laravel 12 · 4 rules · the Laravel way

  app/Http/Controllers/UserController.php ....................................
  [WARNING] env() called outside of config files:11
           Move this env() call to a config file and use config() to retrieve the value.
           Docs: https://laravel.com/docs/12.x/configuration#accessing-configuration-values
  [WARNING] Inline validation: $request->validate():12
           Extract validation to a Form Request class using: php artisan make:request
           Docs: https://laravel.com/docs/12.x/validation#form-request-validation

  app/Services/ReportService.php .............................................
  [WARNING] env() called outside of config files:11
           Move this env() call to a config file and use config() to retrieve the value.
           Docs: https://laravel.com/docs/12.x/configuration#accessing-configuration-values
  [INFO] Raw database query: DB::select():12
         Consider using Eloquent models and relationships instead of raw queries.
         Docs: https://laravel.com/docs/12.x/eloquent

  Found 4 violation(s): 3 warning(s), 1 info
```

### Options

```bash
# Show everything including info-level hints
php artisan patrol --severity=info

# Run a specific rule only
php artisan patrol --rule=no-env-outside-config

# JSON output for CI pipelines
php artisan patrol --format=json
```

## Configuration

```php
// config/patrol.php
return [
    'version' => 12,           // Your Laravel version (11, 12)
    'preset' => 'recommended', // 'strict', 'recommended', 'relaxed'
    'rules' => [],             // Override individual rules (FQCN => bool)
    'paths' => ['app', 'routes', 'config', 'resources'],
    'exclude' => ['vendor', 'node_modules', 'storage'],
    'custom_rules' => [],      // Your own rule classes
];
```

### Presets

| Preset | Rules | Use case |
|--------|-------|----------|
| `relaxed` | 1 rule | Just the essentials |
| `recommended` | 4 rules | Sensible defaults for most projects |
| `strict` | 6 rules | Full enforcement |

### Disabling a rule

```php
'rules' => [
    \MarcoKoepfli\LaravelPatrol\Rules\NoRawQueries::class => false,
],
```

## Built-in Rules

| Rule | ID | Severity | Description |
|------|----|----------|-------------|
| NoEnvOutsideConfig | `no-env-outside-config` | Warning | `env()` should only be used in config files |
| UseFormRequests | `use-form-requests` | Warning | Use Form Request classes instead of inline validation |
| NoRawQueries | `no-raw-queries` | Info | Prefer Eloquent over raw DB queries |
| UseResourceControllers | `use-resource-controllers` | Warning | Use `Route::resource()` for CRUD routes |
| UseBladeComponents | `use-blade-components` | Info | Prefer Blade components over `@include` |
| NoBusinessLogicInControllers | `no-business-logic-in-controllers` | Warning | Keep controllers thin (max 10 statements per method) |

All PHP rules use AST parsing via [nikic/php-parser](https://github.com/nikic/PHP-Parser) — no regex matching. This means:
- `env()` in strings or comments is **not** flagged
- `$service->validate()` is **not** confused with `$request->validate()`
- `DB::table()` and `DB::transaction()` are correctly ignored by the raw query rule

## Suppressing violations

Add `@patrol-ignore` on the line above or the same line:

```php
// @patrol-ignore
$key = env('APP_KEY');
```

```blade
{{-- @patrol-ignore --}}
@include('partials.legacy')
```

## Custom Rules

Create a class extending `AbstractRule`:

```php
use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\ProjectContext;
use MarcoKoepfli\LaravelPatrol\Rules\AbstractRule;

class NoTodoComments extends AbstractRule
{
    public function id(): string
    {
        return 'no-todo-comments';
    }

    public function description(): string
    {
        return 'TODO comments should be resolved before merging.';
    }

    public function docsUrl(LaravelVersion $version): string
    {
        return 'https://your-team-wiki.com/conventions';
    }

    public function check(ProjectContext $context): array
    {
        $violations = [];

        foreach ($context->phpFiles() as $file) {
            // Use $context->analyzer() for AST parsing
            // Use $this->violation() to create results
            // Use $this->shouldIgnore() for @patrol-ignore support
        }

        return $violations;
    }
}
```

Register in `config/patrol.php`:

```php
'custom_rules' => [
    \App\Patrol\NoTodoComments::class,
],
```

## Testing

```bash
composer test       # Run tests
composer analyse    # Run PHPStan
composer format     # Fix code style
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Marco Koepfli](https://github.com/marcokoepfli)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
