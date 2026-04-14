# GGBlastPlugin Unit Tests

This directory contains unit tests for the GGBlastPlugin.

## Test Coverage

- **BlastValidatorTest.php**: Tests for BLAST parameter validation, security checks, and input sanitization
- **ConfigReaderTest.php**: Tests for configuration file parsing and validation
- **JobManagerTest.php**: Tests for job management logic, command building, and data handling
- **SecurityTest.php**: Security-focused tests for injection prevention and access control

## Running Tests

### Run All Tests

```bash
# Using npm (recommended)
npm test

# Or using composer
composer test

# Or directly with PHP
php tests/simple-test.php
```

### Run Syntax Check Only

```bash
# Check PHP syntax
composer lint

# Or using npm
npm run lint
```

## Test Philosophy

These tests focus on:

1. **Logic validation** - Testing validation rules and business logic
2. **Security** - Preventing injection attacks and directory traversal
3. **Data handling** - JSON encoding/decoding, type conversion
4. **Command building** - Safe shell command construction
5. **Path handling** - Proper path normalization and sanitization

Tests do NOT require:
- PHPUnit or PHP XML extensions
- Actual BLAST installation
- Database connections
- File system access (uses mocks where needed)
- Network access

## Adding New Tests

When adding new functionality:

1. Create test file in `tests/` directory
2. Follow naming convention: `*Test.php`
3. Add descriptive test method names starting with `test`
4. Run tests to ensure they pass

## Continuous Integration

These tests are designed to run in CI/CD pipelines (Travis CI, GitHub Actions, etc.) without special dependencies beyond PHP.

Example `.github/workflows/test.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['7.4', '8.0', '8.1', '8.2']
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
      
      - name: Run tests
        run: php tests/simple-test.php
      
      - name: Lint PHP files
        run: find blast -name '*.php' -exec php -l {} \;
```
