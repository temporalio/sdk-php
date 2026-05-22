# Contributing

This doc is intended for contributors to `sdk-php` (hopefully that's you!)

All contributors must complete the Temporal Contributor License Agreement (CLA) before changes can be merged. A link to the CLA will be posted in the PR.

## Development environment

- [PHP 8.1+](https://www.php.net/downloads.php)
- [Composer](https://getcomposer.org/download/)

## Build

```bash
composer install              # Downloads regular dependencies
composer run get:binaries     # Downloads dependencies for local development
pecl install grpc             # Required by the Temporal client
pecl install protobuf         # Improves performance of protobuf serialization
```

## Test

```bash
composer run test:unit        # Unit tests
composer run test:func        # Functional tests
composer run test:arch        # Architecture tests
composer run test:accept      # All acceptance tests
composer run test:accept-fast # All acceptance tests except the slow ones
composer run test:accept-slow # Only the slow acceptance tests
```

## Quality control

```bash
composer run cs:diff          # Show code style violations (dry run)
composer run cs:fix           # Auto-fix code style violations
composer run psalm            # Run static analysis
composer run psalm:baseline   # Update the Psalm baseline file
```
