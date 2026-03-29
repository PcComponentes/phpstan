# pccomponentes/phpstan

Shared PHPStan configuration and extensions for PcComponentes projects.

## What it includes

- `phpstan/phpstan`
- `phpstan/phpstan-beberlei-assert`
- `phpstan/phpstan-symfony`
- custom type-specifying extensions for Beberlei Assert chains and `LazyAssertion::verifyNow()`

## Installation

```bash
composer require --dev pccomponentes/phpstan
```

## Usage

Include the package extension from your project `phpstan.neon`:

```neon
includes:
  - vendor/pccomponentes/phpstan/extension.neon
```

Keep project-specific configuration such as `level`, `excludePaths`, `ignoreErrors`, and `symfony.containerXmlPath` in the consumer project.

## Development

This repository includes a local PHP environment based on Docker Compose.

```bash
make start
```

Useful targets:

- `make composer-install`
- `make composer-update`
- `make bash`
- `make logs`
