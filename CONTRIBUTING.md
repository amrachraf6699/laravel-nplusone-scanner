# Contributing

Run `composer install`, `composer test`, `composer lint`, and `composer validate` before opening a pull request. Add isolated fixtures for every rule change and include both a candidate and a false-positive prevention case.

The scanner must remain offline and must not execute application source, require models, connect to a database, or modify scanned files. Keep inference conservative and document new limitations.
