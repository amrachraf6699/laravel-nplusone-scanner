# Laravel N+1 Query Scanner

`amrachraf6699/laravel-nplusone-scanner` is an offline, AST-based static analyzer for potential Laravel N+1 query patterns. It scans PHP source code without executing the scanned application, sending HTTP requests, instantiating application models, connecting to a database, or waiting for lazy-loading exceptions.

The current release is **v0.1.1**. Findings are intentionally conservative: they identify code worth reviewing, not runtime proof that an N+1 query will occur.

## Contents

- [Why this package exists](#why-this-package-exists)
- [What it does and does not do](#what-it-does-and-does-not-do)
- [Requirements and compatibility](#requirements-and-compatibility)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Command options and exit codes](#command-options-and-exit-codes)
- [Detection rules](#detection-rules)
- [Eager loading and aggregate awareness](#eager-loading-and-aggregate-awareness)
- [Severity and confidence](#severity-and-confidence)
- [Configuration](#configuration)
- [Console and JSON reports](#console-and-json-reports)
- [Model and relationship discovery](#model-and-relationship-discovery)
- [False positives, false negatives, and limitations](#false-positives-false-negatives-and-limitations)
- [CI usage](#ci-usage)
- [Architecture and extension points](#architecture-and-extension-points)
- [Development and verification](#development-and-verification)
- [Versioning and license](#versioning-and-license)

## Why this package exists

An N+1 query happens when an application loads a collection and then performs another database query for each item in that collection. For example, loading all users with one query and then lazily loading `posts` once per user can turn a page into dozens or thousands of queries.

The package catches common source-level shapes before they reach production or CI. It is useful during code review, local development, and automated checks where a database or HTTP environment is unavailable.

## What it does and does not do

The scanner does:

- Parse PHP with `nikic/php-parser` and resolve namespaces and imported class names.
- Discover PHP files with Symfony Finder.
- Index local Eloquent models, intermediate base models, relationship methods, Resource model evidence, and literal `$with` defaults.
- Track limited variable provenance, aliases, collection provenance, loop context, closure scope, eager-loaded relationships, and aggregate attributes.
- Report deterministic console or JSON findings.
- Continue scanning independent files when another file has a parse or read error.
- Reuse ASTs during one scan when a file is both metadata and analysis input.

The scanner does not:

- Execute scanned application code.
- Make HTTP requests or visit routes.
- Instantiate application models or execute Eloquent queries.
- Connect to a database, run migrations, or require seeders and factories.
- Register runtime query listeners or replace Laravel lazy-loading protection.
- Automatically edit application source code.
- Claim that every N+1 problem is detectable.

The reusable scanner core is isolated from Laravel’s container. When run through Artisan, Laravel still boots the host application before the command is dispatched; providers in that host application may perform their own work. The package itself does not execute the files it scans.

## Requirements and compatibility

- PHP 8.2 or newer.
- Laravel 12 or Laravel 13.
- `nikic/php-parser` 5.x.
- Symfony Finder and Console 7.x or 8.x.
- PHPUnit 11.x for development.
- Orchestra Testbench 10 for Laravel 12 and 11 for Laravel 13.

This is a Composer library, so `composer.lock` is intentionally not committed. CI resolves dependencies independently for each PHP lane: PHP 8.2 selects the compatible Laravel 12/Testbench 10 set, while PHP 8.3 and newer select Laravel 13/Testbench 11 when available.

## Installation

Install the package in a Laravel application:

```bash
composer require amrachraf6699/laravel-nplusone-scanner
```

Laravel package auto-discovery registers the service provider. Publish the optional configuration file:

```bash
php artisan vendor:publish --tag=nplusone-config
```

The package does not require a database connection for scanning.

## Quick start

Run the default scan:

```bash
php artisan nplusone:scan
```

Scan a specific directory and fail CI on high-severity findings:

```bash
php artisan nplusone:scan --path=app --fail-on=high --no-progress
```

Produce machine-readable output:

```bash
php artisan nplusone:scan --format=json --no-progress > nplusone-report.json
```

Scan more than one path by repeating the option:

```bash
php artisan nplusone:scan --path=app/Http --path=app/Services
```

## Command options and exit codes

The command is `nplusone:scan`.

| Option | Meaning |
|---|---|
| `--path=PATH` | Repeatable directory or PHP file to analyze. When supplied, it replaces configured analysis paths. |
| `--format=console\|json` | Select human-readable console output or deterministic JSON. Defaults to `console`. |
| `--min-severity=low\|medium\|high` | Report only findings at or above this severity. |
| `--fail-on=low\|medium\|high` | Return exit code 1 when a reported finding reaches this threshold. |
| `--no-progress` | Suppress progress text in console output. Always use this for redirected output. |

Exit codes:

| Code | Meaning |
|---:|---|
| `0` | The scan completed and no reported finding reached `--fail-on`. This also covers a completed scan with findings when no threshold was requested. |
| `1` | The scan completed and at least one reported finding reached `--fail-on`. |
| `2` | Invalid options or configuration, unreadable input, parser errors, or another analysis failure. |

A scan with independent file errors is reported as `partial` and returns `2`. An empty readable directory is a successful zero-file scan.

## Detection rules

### N1 — relationship property inside an iteration

N1 reports a recognized relationship property accessed on a varying model inside a verified iteration when that relationship is not known to be loaded.

Potential candidate:

```php
$users = User::all();

foreach ($users as $user) {
    echo $user->posts->count();
}
```

Preferred version:

```php
$users = User::with('posts')->get();

foreach ($users as $user) {
    echo $user->posts->count();
}
```

The preferred version is not reported when the `User::with('posts')->get()` provenance is recognized.

N1 does not treat ordinary PHP properties as relationships. The relationship must be established from model source metadata or a reliable indexed inheritance chain.

### N2 — relationship query method inside an iteration

N2 reports explicit relationship-method queries inside a verified iteration. This matters because explicit method calls still query even when lazy-loading protection is enabled.

Potential candidate:

```php
$users = User::all();

foreach ($users as $user) {
    $count = $user->posts()->count();
}
```

For count and existence checks, the suggestion matches the operation:

```php
$users = User::withCount('posts')->get();
$users = User::withExists('posts')->get();
```

Supported relationship query terminals include `count`, `exists`, `first`, `get`, `sum`, `avg`, `max`, `min`, and `pluck`. Unsupported or dynamic query chains are treated as unknown.

### N3 — proven collection callbacks

N3 reports the same kind of risk inside callbacks known to operate on a recognized Eloquent model collection.

Potential candidate:

```php
User::all()->map(fn ($user) => $user->posts->count());
```

Safe eager-loaded callback:

```php
User::with('posts')->get()
    ->each(fn ($user) => $user->posts->count());
```

The scanner does not assume that every method named `map()` or `each()` belongs to an Eloquent collection. Callback parameters have their own scope, so shadowed variables do not inherit stale outer facts.

### N4 — Laravel API Resource relationship access

N4 is a conservative Resource warning. It requires a Resource model established through an explicit configuration mapping or an unambiguous `@mixin` annotation.

Potential candidate:

```php
/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'posts' => PostResource::collection($this->posts),
        ];
    }
}
```

Safe conditional relationship:

```php
'posts' => PostResource::collection(
    $this->whenLoaded('posts')
),
```

N4 does not prove that the caller omitted eager loading. It remains a lower-confidence review item.

### Native loop support

Indexed access to a known model collection is supported conservatively in `for`, `while`, and `do-while` loops:

```php
$users = User::all();

for ($i = 0; $i < 100; $i++) {
    echo $users[$i]->posts->count();
}
```

These findings receive medium confidence. A fixed model does not qualify merely because it appears in a loop:

```php
$user = User::first();

while ($running) {
    echo $user->posts->count();
    $running = false;
}
```

## Eager loading and aggregate awareness

Recognized relationship loading includes:

```php
User::with('posts')->get();
User::query()->with(['posts', 'profile'])->get();
User::with('posts.comments')->get();
$users->load('posts');
$users->loadMissing('posts');
```

The scanner tracks relationship paths conservatively. Loading `posts` does not establish that `posts.comments` is loaded.

Aggregate attributes are separate from relationship loading:

```php
$users = User::withCount('posts')->get();

foreach ($users as $user) {
    echo $user->posts_count; // safe aggregate attribute
    echo $user->posts;       // still a relationship access candidate
}
```

`withExists`, `loadCount`, and `loadExists` are treated as aggregate operations. They do not make `$user->posts` safe.

Collection relationship autoloading is recognized as a loading-state caveat. The scanner does not execute application providers to discover whether automatic eager loading is enabled at runtime.

## Severity and confidence

Severity estimates potential performance impact. Confidence estimates how strongly source evidence supports the finding.

| Rule/context | Typical severity | Typical confidence |
|---|---|---|
| Recognized relationship property in a verified `foreach` | High | High |
| Explicit relationship query in a verified iteration | High | High |
| Indexed `for`, `while`, or `do-while` candidate | High | Medium |
| Proven Eloquent collection callback | High | High |
| Resource relationship access | Medium | Low |

A high-confidence finding is still a static warning. It does not establish row counts, runtime branches, caller loading state, or actual SQL.

## Configuration

The default configuration is:

```php
<?php

return [
    'paths' => ['app'],
    'metadata_paths' => ['app'],
    'exclude' => [
        'vendor',
        'storage',
        'bootstrap/cache',
    ],
    'rules' => [
        'N1' => true,
        'N2' => true,
        'N3' => true,
        'N4' => true,
    ],
    'minimum_severity' => 'low',
    'resource_models' => [],
    'additional_rules' => [],
];
```

Publish it with:

```bash
php artisan vendor:publish --tag=nplusone-config
```

Configuration details:

- `paths` are analysis roots. Relative paths are resolved from the Laravel project root.
- `metadata_paths` are additional roots used to discover models and Resources. The default is `app` so scanning a controller subdirectory can still resolve application models.
- `exclude` prunes excluded directories during discovery.
- `rules` enables or disables N1, N2, N3, and N4.
- `minimum_severity` filters reported findings.
- `resource_models` maps Resource FQCNs to model FQCNs when `@mixin` evidence is unavailable.
- `additional_rules` is reserved for a future public custom-rule registration surface; v0.1.1 runs the built-in rule set.

Example Resource mapping:

```php
'resource_models' => [
    App\Http\Resources\UserResource::class => App\Models\User::class,
],
```

Do not use dynamic relationship names when you need a high-confidence static result:

```php
$relation = $request->string('relation');
$user->{$relation}; // relationship identity is unknown
```

## Console and JSON reports

Console output includes the scanner version, files analyzed, issue count, severity, file, line, model, relationship, and suggestion.

JSON output contains a deterministic object with this shape:

```json
{
  "schema_version": "1",
  "scanner_version": "0.1.1",
  "status": "complete",
  "summary": {
    "files_discovered": 86,
    "files_analyzed": 86,
    "files_failed": 0,
    "files_parsed": 86,
    "cache_hits": 86,
    "findings_total": 2,
    "findings_reported": 2,
    "findings_by_severity": {
      "low": 0,
      "medium": 1,
      "high": 1
    }
  },
  "findings": [
    {
      "rule": "N1",
      "severity": "high",
      "confidence": "high",
      "file": "app/Http/Controllers/UserController.php",
      "line": 42,
      "column": 1,
      "message": "Potential N+1 relationship property access inside an iteration.",
      "model": "App\\Models\\User",
      "relationship": "posts",
      "suggestion": "Consider eager loading posts before iterating.",
      "documentation_url": null
    }
  ],
  "diagnostics": []
}
```

`status` can be `complete`, `partial`, or `failed`. Diagnostics describe parse, read, duplicate-declaration, and unresolved-Resource conditions.

For CI, keep progress and human-readable output out of stdout:

```bash
php artisan nplusone:scan --format=json --no-progress > nplusone-report.json
```

## Model and relationship discovery

The scanner never executes model methods. It recognizes local models through canonical Eloquent roots and transitive local inheritance:

```php
abstract class BaseModel extends Model {}

class User extends BaseModel
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
```

Supported relationship factories include `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `morphOne`, `morphMany`, `morphTo`, `morphToMany`, `morphedByMany`, `hasOneThrough`, and `hasManyThrough`.

The resolver uses fully qualified names produced from namespaces and imports. It does not guess from arbitrary class suffixes. If a model or relationship cannot be identified reliably, the analyzer abstains.

## False positives, false negatives, and limitations

A finding can be a false positive when runtime loading happens outside the source evidence available to the scanner. Common examples include:

- A repository method eager loads a relationship but its return type and implementation are not indexed.
- A service provider enables automatic relationship eager loading at runtime.
- A Resource caller loads the relationship in another layer.
- A custom builder or macro carries loading state the scanner cannot model.
- A branch, reference, or alias mutation is too complex to prove.

The scanner deliberately downgrades confidence or abstains in these cases rather than inventing model or relationship identity.

A false negative can occur when a query is hidden behind a repository, custom scope, dynamic method, runtime relationship resolver, generated code, Blade template, or unsupported iterator. Zero findings never proves that an application is N+1-free.

The scanner also reports only potential repeated work. It cannot determine runtime collection size, SQL query count, indexes, network latency, or whether a relationship is already hydrated through another application mechanism.

## CI usage

The repository workflow runs on PHP 8.2, 8.3, and 8.4. It resolves dependencies per lane because Laravel 12 and Laravel 13 have different PHP requirements, then runs:

```bash
composer test
composer lint
composer validate --strict
```

A consuming application can fail its own CI on high-confidence performance risks:

```bash
php artisan nplusone:scan --format=json --no-progress --fail-on=high
```

Store the JSON artifact or pass it to a later CI reporting step. SARIF, pull-request annotations, baselines, and runtime query correlation are outside v0.1.1.

## Architecture and extension points

The core pipeline is:

1. `FileDiscovery` finds PHP files and applies exclusions.
2. `PhpFileParser` parses source and resolves names.
3. `ModelMetadataResolver` builds a compact model and Resource index.
4. `FlowAnalyzer` tracks bounded provenance, assignments, branches, callbacks, loops, loading, and aggregates.
5. `RuleEngine` dispatches typed analysis events to N1–N4 rules.
6. `FindingCollector` deduplicates and sorts immutable findings.
7. `ConsoleReporter` or `JsonReporter` renders the result.

The scanner retains only scan-local ASTs needed for both metadata and analysis. It does not persist a cache or run analysis in parallel.

The current public command and finding shape are intended to remain stable while future releases may add baselines, SARIF, route/runtime evidence, and query-count correlation.

## Development and verification

Clone the repository and install development dependencies:

```bash
composer install
```

Run the complete local verification suite:

```bash
composer test
composer lint
composer composer-check
```

Generate benchmark fixtures:

```bash
php benchmarks/generate-fixtures.php .tmp/fixtures 1000
```

The tests use isolated PHP fixtures. They parse fixture source without including it, and they do not require a database, migrations, factories, or HTTP requests.

When changing detection behavior, add both a candidate fixture and a safe/ambiguous fixture. Check deterministic ordering, JSON validity, exit codes, and whether the change affects confidence or severity.

## Versioning and license

The package follows semantic versioning within the 0.x development series. The current release is `v0.1.1`; the immutable `v0.1.0` tag remains available for the initial release.

The package is released under the MIT license. See [LICENSE](LICENSE) and [CHANGELOG.md](CHANGELOG.md).
