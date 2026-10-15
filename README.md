# Laravel N+1 Query Scanner

`amrachraf6699/laravel-nplusone-scanner` v0.1.1 is a conservative, offline static analyzer for potential Laravel N+1 query patterns. It parses PHP source with PHP-Parser and never performs HTTP requests, instantiates application models, connects to a database, or executes scanned code.

## Installation

```bash
composer require amrachraf6699/laravel-nplusone-scanner
php artisan vendor:publish --tag=nplusone-config
```

The package supports Laravel 12 and 13. Run a scan with:

```bash
php artisan nplusone:scan
php artisan nplusone:scan --path=app --format=json --fail-on=high
```

Exit code `0` means the scan completed without reaching `--fail-on`; `1` means the threshold was reached; `2` means configuration, path, parsing, or analysis errors occurred.

## What it detects

Version 0.1.1 detects conservative candidates for relationship properties and relationship query methods used during model iteration, proven Eloquent collection callbacks, and Laravel API Resource relationship access. Findings include severity and confidence. They are static warnings, not runtime proof of an N+1 query.

Recognized eager loading includes `with`, `withOnly`, `load`, and `loadMissing`. `withCount` and `withExists` are tracked as aggregate attributes and do not make a relationship property loaded.

## Configuration

Publish `config/nplusone.php` to configure paths, metadata paths, exclusions, enabled rules, minimum severity, Resource-to-model mappings, and trusted custom rules. Relative paths are resolved from the Laravel project root. JSON output is deterministic and suitable for CI.

Resource findings require an explicit `resource_models` mapping or an unambiguous `@mixin` model annotation. Unknown dynamic relationships, custom repositories, complex control flow, runtime configuration, and Blade templates are intentionally outside v0.1.

Zero findings do not prove that an application is N+1-free. Review each candidate against the actual query and loading path.

## Development

```bash
composer install
composer test
composer lint
composer composer-check
```

The test fixtures are parsed as source and are never included or executed. The package's core scanner can be used without a Laravel application container.

## License

MIT. See [LICENSE](LICENSE).
