# AuthLite Testing Guide

This directory contains tests for the AuthLite authentication library. AuthLite provides multiple testing approaches to ensure reliability and security.

## 📋 Overview

AuthLite uses a hybrid testing approach:

1. **Custom Test Runner** (`../test2.php`): Comprehensive functional tests
2. **PHPUnit Configuration**: Ready for unit and integration tests
3. **Shell Scripts**: Server testing and integration tests

## 🧪 Test Files

### Core Test Files
- `../test2.php` - Comprehensive functional test suite
- `../test-server.sh` - Automated web server testing
- `phpunit.xml.dist` - PHPUnit configuration (optional)
- `phpcs.xml.dist` - Code style checking (optional)

### Test Configuration
- Test database: `test_auth.db` (auto-created)
- Test configuration: `config.local.php` (auto-generated)
- Environment variables for testing

## 🚀 Running Tests

### Quick Test (All-in-One)
```bash
# From the project root
php test2.php
```

This runs the comprehensive test suite covering:
- File existence and configuration loading
- Database initialization and table creation
- User registration and duplicate prevention
- Login with correct and incorrect credentials
- CSRF token generation and validation
- User logout functionality
- Utility function testing
- Database statistics retrieval

### Development Server Testing
```bash
# Start background server
./start-background.sh start 8899

# Run server tests
./test-server.sh

# Check server status
./start-background.sh status

# Stop server
./start-background.sh stop
```

### Code Style Checking (Optional)
```bash
# Install PHP_CodeSniffer
composer require --dev squizlabs/php_codesniffer

# Run style checks
./vendor/bin/phpcs --standard=phpcs.xml.dist .
```

## 📝 Writing Tests

### Test Structure
AuthLite tests follow this pattern:

```php
// Test setup
start_test("Descriptive test name");

// Test execution and assertions
try {
    $result = AuthLite::someFunction();
    if ($result) {
        record_result(true, "Function worked as expected");
    } else {
        record_result(false, "Function failed");
    }
} catch (Exception $e) {
    record_result(false, "Exception: " . $e->getMessage());
}
```

### Test Categories

#### 1. Unit Tests
- Test individual functions and classes
- Mock dependencies where needed
- Focus on edge cases and error conditions

#### 2. Integration Tests
- Test interactions between components
- Use test database
- Test configuration loading

#### 3. Functional Tests
- Test complete user workflows
- Simulate HTTP requests
- Test form submissions and redirects

### Test Database
- Created automatically as `test_auth.db`
- Tables are created on first run
- Cleaned up after tests (optional)
- Never use production database for testing

### Test Configuration
```php
// Auto-generated test configuration includes:
- Separate test database path
- Debug mode enabled
- Test master key for debug vault
- Lower bcrypt cost for faster tests
- HTTP allowed (not HTTPS) for testing
```

## 🔧 Test Environment

### Environment Variables
```bash
# Set these for custom test environments
export AUTHLITE_TEST_DB_PATH="./custom_test.db"
export AUTHLITE_DEBUG_ENABLED=1
export AUTHLITE_MASTER_KEY="test-key"
```

### PHP Configuration for Testing
```ini
; Recommended php.ini settings for testing
error_reporting = E_ALL
display_errors = 1
display_startup_errors = 1
log_errors = 0
session.save_handler = files
session.use_cookies = 0
```

### Test Cleanup
```bash
# Clean all test files
./start-background.sh clean

# Clean specific files
rm -f test_auth.db config.local.php .authlite-server.*
```

## 🧪 Test Coverage Areas

### Authentication Tests
- [x] User registration
- [x] User login/logout
- [x] Password hashing and validation
- [x] Session management
- [x] CSRF protection
- [ ] Rate limiting
- [ ] Account locking

### Security Tests
- [x] Password strength validation
- [x] SQL injection prevention
- [x] XSS protection (input sanitization)
- [x] Secure session handling
- [ ] Password reset functionality
- [ ] Two-factor authentication

### OAuth Tests (when configured)
- [ ] Google OAuth flow
- [ ] Facebook OAuth flow
- [ ] Apple OAuth flow
- [ ] OAuth error handling
- [ ] Token refresh and revocation

### Database Tests
- [x] Table creation
- [x] CRUD operations
- [x] Index usage
- [ ] Migration testing
- [ ] Backup/restore

### API Tests
- [ ] REST API endpoints
- [ ] JSON responses
- [ ] Error handling
- [ ] Authentication headers
- [ ] Rate limiting

## 🔍 Debugging Tests

### Enable Debug Output
```php
// In test configuration
'debug' => [
    'enabled' => true,
    'log_errors' => true,
    'display_errors' => true,
],
```

### View Test Logs
```bash
# Server logs
tail -f .authlite-server.log

# PHP error logs
tail -f /var/log/php_error.log
```

### Use Debug Vault
1. Start server: `./start-background.sh start`
2. Access: `http://localhost:8899/auth/vault?key=test-master-key-bg-12345`
3. View database statistics and configuration

## 🏗️ CI/CD Integration

### GitHub Actions Example
```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
          extensions: pdo, pdo_sqlite, openssl, curl
      - name: Run tests
        run: php test2.php
```

### Docker Testing
```dockerfile
FROM php:7.4-cli
RUN docker-php-ext-install pdo pdo_sqlite
RUN apt-get update && apt-get install -y curl
COPY . /app
WORKDIR /app
CMD ["php", "test2.php"]
```

## 📊 Test Reports

### Generating Reports
```bash
# HTML test report (custom)
php test2.php > test-report.html

# Code coverage (with PHPUnit)
./vendor/bin/phpunit --coverage-html coverage/
```

### Performance Testing
```bash
# Measure test execution time
time php test2.php

# Memory usage
/usr/bin/time -v php test2.php 2>&1 | grep "Maximum resident"
```

## 🚨 Common Issues

### "Session cannot be started after headers"
- Ensure `AuthLite::init()` is called before any output
- Check for whitespace before `<?php`
- Verify no output before session start

### Database Permission Errors
```bash
# Fix permissions
chmod 755 .
chmod 644 test_auth.db
```

### Test Failures After Code Changes
1. Clean test database: `rm -f test_auth.db`
2. Regenerate config: `rm -f config.local.php`
3. Run tests again

## 🤝 Contributing Tests

### Test Guidelines
1. **Isolate tests**: Each test should be independent
2. **Clean up**: Remove test data after tests
3. **Document**: Add comments for complex tests
4. **Cover edge cases**: Test failures and errors
5. **Be descriptive**: Clear test names and messages

### Adding New Tests
1. Add test function to `test2.php`
2. Follow existing test patterns
3. Test both success and failure cases
4. Update this README if adding new test categories
5. Run existing tests to ensure no regressions

### Test Review Checklist
- [ ] Tests pass consistently
- [ ] No side effects on other tests
- [ ] Proper error handling
- [ ] Clear assertion messages
- [ ] Appropriate test coverage

## 📚 Resources

### Testing Tools
- [PHPUnit](https://phpunit.de/) - PHP testing framework
- [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) - Code style
- [Xdebug](https://xdebug.org/) - Debugging and code coverage

### Testing Patterns
- [Test-Driven Development](https://en.wikipedia.org/wiki/Test-driven_development)
- [Behavior-Driven Development](https://en.wikipedia.org/wiki/Behavior-driven_development)
- [Testing Pyramid](https://martinfowler.com/articles/practical-test-pyramid.html)

### Security Testing
- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)
- [Security Headers](https://securityheaders.com/)

---

**Last Updated**: February 2025  
**Test Framework Version**: 1.0

For issues with tests, see the main [CONTRIBUTING.md](../CONTRIBUTING.md) file.