# GGBlastPlugin Unit Tests

This directory contains PHPUnit tests for the GGBlastPlugin.

## Test Coverage

- **BlastValidatorTest.php**: Tests for BLAST parameter validation, security checks, and input sanitization
- **ConfigReaderTest.php**: Tests for configuration file parsing and validation
- **JobManagerTest.php**: Tests for job management logic, command building, and data handling
- **SecurityTest.php**: Security-focused tests for injection prevention and access control

## Running Tests

### Prerequisites

PHPUnit requires PHP XML extensions. If you get "dom", "xml", or "xmlwriter" extension errors:

```bash
# For Ubuntu/Debian with your PHP version
sudo apt install php-xml php-mbstring

# Then install composer dependencies
composer install --ignore-platform-reqs
```

### Run All Tests

```bash
composer test
# or
./vendor/bin/phpunit
# or using the PHAR file
php phpunit.phar
```

### Alternative: Simple Test Runner (No Extensions Required)

If you cannot install the XML extensions, use the simple test runner:

```bash
# Using npm (recommended)
npm test

# Or using composer
composer test-simple

# Or directly with PHP
php simple-test.php
```

### Run Specific Test File

```bash
./vendor/bin/phpunit tests/BlastValidatorTest.php
```

### Run with Coverage Report

```bash
composer test-coverage
# Opens coverage report in coverage/index.html
```

### Run Syntax Check Only

```bash
composer lint
```

## Test Philosophy

These tests focus on:

1. **Logic validation** - Testing validation rules and business logic
2. **Security** - Preventing injection attacks and directory traversal
3. **Data handling** - JSON encoding/decoding, type conversion
4. **Command building** - Safe shell command construction
5. **Path handling** - Proper path normalization and sanitization

Tests do NOT require:
- Actual BLAST installation
- Database connections
- File system access (uses mocks where needed)
- Network access

## Adding New Tests

When adding new functionality:

1. Create test file in `tests/` directory
2. Extend `PHPUnit\Framework\TestCase`
3. Follow naming convention: `*Test.php`
4. Add descriptive test method names starting with `test`
5. Run tests to ensure they pass

## Continuous Integration

These tests are designed to run in CI/CD pipelines (Travis CI, GitHub Actions, etc.) without special dependencies beyond PHP and composer.

Example `.travis.yml`:

```yaml
language: php
php:
  - '7.4'
  - '8.0'
  - '8.1'

install:
  - composer install

script:
  - composer test
  - composer lint
```
