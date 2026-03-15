# Contributing

Contributions are welcome! Here's how you can help.

## Bug Reports

If you find a bug, please open an issue with:
- A clear description of the problem
- Steps to reproduce
- Expected vs actual behavior

## Adding Rules

The best way to contribute is by adding new rules. Every rule must:

1. Implement `MarcoKoepfli\LaravelPatrol\Contracts\Rule`
2. Extend `MarcoKoepfli\LaravelPatrol\Rules\AbstractRule` for helpers
3. Use AST parsing via `FileAnalyzer` (no regex for PHP files)
4. Include a `docsUrl()` pointing to the relevant Laravel docs
5. Provide an actionable `suggestion` in every violation
6. Have full test coverage including edge cases

### Rule checklist

- [ ] Rule class in `src/Rules/`
- [ ] Tests in `tests/Rules/` with edge cases
- [ ] Added to relevant Presets
- [ ] `@patrol-ignore` support works
- [ ] No false positives on common patterns

## Development Setup

```bash
git clone git@github.com:marcokoepfli/laravel-patrol.git
cd laravel-patrol
composer install
```

## Running Tests

```bash
composer test          # Run tests
composer analyse       # Run PHPStan
composer format        # Fix code style
```

## Pull Requests

1. Fork the repo and create a branch from `main`
2. Make your changes
3. Ensure all tests pass: `composer test`
4. Ensure PHPStan passes: `composer analyse`
5. Ensure code style is clean: `vendor/bin/pint`
6. Submit a PR with a clear description
