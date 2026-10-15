# Repository instructions for AI agents

## Project purpose

This repository contains `amrachraf6699/laravel-nplusone-scanner`, an offline static analyzer for potential Laravel N+1 query patterns. The scanner parses PHP source and must not execute scanned application code, instantiate application models, send HTTP requests, connect to a database, or modify scanned source files.

Read `README.md`, `composer.json`, and the relevant tests before changing behavior. Keep documentation aligned with the actual implementation; do not claim complete N+1 detection.

## Implementation rules

- Use PHP-Parser ASTs. Do not add regex-based source detection.
- Keep inference conservative. Unknown model, relationship, loading, or control-flow state must abstain or lower confidence.
- Preserve the distinction between loaded relationships and aggregate attributes such as `posts_count` and `posts_exists`.
- Treat Laravel Resources as caller-dependent risks, not confirmed N+1 queries.
- Keep the core scanner independent from the Laravel container and database.
- Add a true-positive fixture and a false-positive prevention fixture for every detection change.
- Update README examples, limitations, and command behavior when public behavior changes.
- Do not commit `composer.lock`; this is a Composer library supporting Laravel 12 and 13 across different PHP lanes.
- Keep generated dependencies under `vendor/` out of Git.

## Required verification after every task

From the repository root, run:

```bash
composer test
composer lint
composer composer-check
```

Also run:

```bash
git diff --check
```

If the change affects the Artisan command or reporting, run the package integration tests and, inside a Laravel host application, verify:

```bash
php artisan nplusone:scan --format=json --no-progress
```

The JSON command must remain machine-readable. Do not redirect unrelated application logs into JSON stdout.

## Tests and review expectations

Before committing a rule or inference change, check:

- safe eager-loaded relationships remain unreported;
- ordinary PHP properties and non-Eloquent collections remain unreported;
- reassignment and branch joins invalidate stale facts;
- explicit relationship queries remain distinct from relationship property reads;
- Resource `whenLoaded()` remains safe;
- findings remain deterministic and deduplicated;
- severity, confidence, suggestions, and exit codes remain correct.

Use focused PHPUnit tests first, then run the complete verification commands above. Do not skip tests because a change appears documentation-only if it changes command examples or public configuration.

## Git and releases

Use small, descriptive commits. Do not rewrite published tags or force-push. Update `src/Support/Version.php` and `CHANGELOG.md` together for a release. Push branches or tags only when the user explicitly requests delivery.
