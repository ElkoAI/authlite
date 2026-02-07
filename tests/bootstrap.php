<?php
/**
 * AuthLite Test Bootstrap
 *
 * Sets up the testing environment for AuthLite authentication library.
 * This file should be included at the beginning of all test files.
 *
 * @package AuthLite
 */

declare(strict_types=1);

// ============================================================================
// ERROR REPORTING AND CONFIGURATION
// ============================================================================

// Enable all error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '0');

// Set timezone for consistent testing
date_default_timezone_set('UTC');

// ============================================================================
// CONSTANTS
// ============================================================================

// Project root directory
define('AUTHLITE_ROOT', dirname(__DIR__));

// Test-specific paths
define('AUTHLITE_TEST_DB_PATH', AUTHLITE_ROOT . '/test_auth.db');
define('AUTHLITE_TEST_CONFIG_PATH', AUTHLITE_ROOT . '/config.local.php');
define('AUTHLITE_DEFAULT_DB_PATH', AUTHLITE_ROOT . '/auth.db');

// Test configuration
define('AUTHLITE_TEST_EMAIL', 'test@example.com');
define('AUTHLITE_TEST_PASSWORD', 'TestPassword123!');
define('AUTHLITE_TEST_USERNAME', 'testuser');

// Test session configuration
define('AUTHLITE_TEST_SESSION_NAME', 'authlite_test_session');

// ============================================================================
// FILE INCLUSION
// ============================================================================

// Include required AuthLite files
require_once AUTHLITE_ROOT . '/config.php';
require_once AUTHLITE_ROOT . '/db.php';
require_once AUTHLITE_ROOT . '/utils.php';
require_once AUTHLITE_ROOT . '/oauth.php';
require_once AUTHLITE_ROOT . '/auth.php';

// ============================================================================
// TEST CONFIGURATION SETUP
// ============================================================================

/**
 * Create or load test configuration
 *
 * @return array Test configuration array
 */
function authlite_create_test_config(): array
{
    $testConfig = [
        'database' => [
            'path' => AUTHLITE_TEST_DB_PATH,
            'create_tables' => true,
        ],
        'security' => [
            'app_secret' => 'test-secret-key-' . bin2hex(random_bytes(16)),
            'hash_algo' => 'sha256',
            'cost' => 10, // Lower cost for faster tests
        ],
        'session' => [
            'name' => AUTHLITE_TEST_SESSION_NAME,
            'lifetime' => 7200,
            'secure' => false, // Allow HTTP for testing
            'httponly' => true,
            'samesite' => 'Lax',
        ],
        'jwt' => [
            'enabled' => false,
            'issuer' => 'authlite-test',
            'audience' => 'authlite-test-client',
            'expiration' => 3600,
            'leeway' => 60,
        ],
        'email' => [
            'from' => 'test@example.com',
            'from_name' => 'AuthLite Test',
            'confirm_expiry' => 86400,
        ],
        'oauth' => [
            'google' => ['enabled' => false],
            'facebook' => ['enabled' => false],
            'apple' => ['enabled' => false],
        ],
        'debug' => [
            'enabled' => true,
            'master_key' => 'test-master-key-12345',
            'log_errors' => true,
            'display_errors' => true,
        ],
        'urls' => [
            'base' => 'http://localhost:8888',
            'login' => '/login',
            'logout' => '/logout',
            'signup' => '/signup',
            'confirm' => '/confirm',
            'debug_vault' => '/auth/vault',
        ],
    ];

    // Write configuration to file if it doesn't exist
    if (!file_exists(AUTHLITE_TEST_CONFIG_PATH)) {
        $configContent = "<?php\n/**\n * AuthLite Test Configuration (Auto-generated)\n */\nreturn " .
                        var_export($testConfig, true) . ";\n";
        file_put_contents(AUTHLITE_TEST_CONFIG_PATH, $configContent);
    }

    return $testConfig;
}

// ============================================================================
// TEST DATABASE MANAGEMENT
// ============================================================================

/**
 * Clean up test database
 */
function authlite_cleanup_test_database(): void
{
    // Remove test database
    if (file_exists(AUTHLITE_TEST_DB_PATH)) {
        unlink(AUTHLITE_TEST_DB_PATH);
    }

    // Remove default database if it exists in test context
    if (file_exists(AUTHLITE_DEFAULT_DB_PATH)) {
        unlink(AUTHLITE_DEFAULT_DB_PATH);
    }

    // Remove session files
    $sessionFiles = glob(session_save_path() . '/sess_*');
    foreach ($sessionFiles as $file) {
        if (strpos($file, AUTHLITE_TEST_SESSION_NAME) !== false) {
            unlink($file);
        }
    }
}

/**
 * Initialize test database
 *
 * @return PDO Database connection
 * @throws Exception If database initialization fails
 */
function authlite_init_test_database(): PDO
{
    // Clean up old database first
    authlite_cleanup_test_database();

    // Create test configuration
    $testConfig = authlite_create_test_config();

    // Initialize database
    if (class_exists('AuthLiteDB')) {
        return AuthLiteDB::init($testConfig);
    }

    // Fallback to direct PDO connection
    $dbPath = $testConfig['database']['path'];
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

// ============================================================================
// SESSION MANAGEMENT
// ============================================================================

/**
 * Start test session
 */
function authlite_start_test_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Set test session name
        session_name(AUTHLITE_TEST_SESSION_NAME);

        // Set cookie parameters for testing
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }
}

/**
 * Destroy test session
 */
function authlite_destroy_test_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Clear all session data
        $_SESSION = [];

        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }
}

// ============================================================================
// TEST HELPER FUNCTIONS
// ============================================================================

/**
 * Create a test user
 *
 * @param string $email Optional email (defaults to test email)
 * @param string $password Optional password (defaults to test password)
 * @param string $username Optional username (defaults to test username)
 * @return array|false User data or false on failure
 */
function authlite_create_test_user(
    string $email = AUTHLITE_TEST_EMAIL,
    string $password = AUTHLITE_TEST_PASSWORD,
    string $username = AUTHLITE_TEST_USERNAME
) {
    // Ensure AuthLite is initialized
    if (!class_exists('AuthLite')) {
        return false;
    }

    // Create test configuration
    $testConfig = authlite_create_test_config();

    // Initialize AuthLite with test config
    AuthLite::init($testConfig);

    // Register test user
    return AuthLite::register($email, $password, $username, false);
}

/**
 * Login test user
 *
 * @param string $email Optional email (defaults to test email)
 * @param string $password Optional password (defaults to test password)
 * @param bool $remember Remember login
 * @return array|false User data or false on failure
 */
function authlite_login_test_user(
    string $email = AUTHLITE_TEST_EMAIL,
    string $password = AUTHLITE_TEST_PASSWORD,
    bool $remember = false
) {
    return AuthLite::login($email, $password, $remember);
}

/**
 * Logout test user
 *
 * @return bool True on success
 */
function authlite_logout_test_user(): bool
{
    return AuthLite::logout();
}

/**
 * Get test database statistics
 *
 * @return array Database statistics
 */
function authlite_get_test_stats(): array
{
    if (class_exists('AuthLiteDB')) {
        return AuthLiteDB::getStats();
    }
    return [];
}

/**
 * Generate test CSRF token
 *
 * @return string CSRF token
 */
function authlite_generate_test_csrf_token(): string
{
    if (class_exists('AuthLite')) {
        return AuthLite::csrfToken();
    }
    return bin2hex(random_bytes(32));
}

// ============================================================================
// TEST ASSERTION HELPERS
// ============================================================================

/**
 * Assert that a condition is true
 *
 * @param mixed $condition Condition to check
 * @param string $message Assertion message
 * @return bool True if assertion passed
 */
function authlite_assert($condition, string $message = ''): bool
{
    if ($condition) {
        echo "✅ PASS: $message\n";
        return true;
    } else {
        echo "❌ FAIL: $message\n";
        return false;
    }
}

/**
 * Assert that two values are equal
 *
 * @param mixed $expected Expected value
 * @param mixed $actual Actual value
 * @param string $message Assertion message
 * @return bool True if assertion passed
 */
function authlite_assert_equals($expected, $actual, string $message = ''): bool
{
    $passed = $expected === $actual;
    if ($passed) {
        echo "✅ PASS: $message\n";
        return true;
    } else {
        echo "❌ FAIL: $message (expected: " . var_export($expected, true) .
             ", got: " . var_export($actual, true) . ")\n";
        return false;
    }
}

/**
 * Assert that a value is not false
 *
 * @param mixed $value Value to check
 * @param string $message Assertion message
 * @return bool True if assertion passed
 */
function authlite_assert_not_false($value, string $message = ''): bool
{
    $passed = $value !== false;
    if ($passed) {
        echo "✅ PASS: $message\n";
        return true;
    } else {
        echo "❌ FAIL: $message (got false)\n";
        return false;
    }
}

// ============================================================================
// TEST OUTPUT FORMATTING
// ============================================================================

/**
 * Print test section header
 *
 * @param string $title Section title
 */
function authlite_test_section(string $title): void
{
    echo "\n" . str_repeat("=", 60) . "\n";
    echo strtoupper($title) . "\n";
    echo str_repeat("=", 60) . "\n\n";
}

/**
 * Print test summary
 *
 * @param int $passed Number of passed tests
 * @param int $failed Number of failed tests
 */
function authlite_test_summary(int $passed, int $failed): void
{
    $total = $passed + $failed;

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "TEST SUMMARY\n";
    echo str_repeat("=", 60) . "\n";
    echo "Total tests: $total\n";
    echo "Passed: $passed\n";
    echo "Failed: $failed\n";

    if ($failed === 0) {
        echo "\n🎉 ALL TESTS PASSED!\n";
    } else {
        echo "\n⚠️  SOME TESTS FAILED\n";
    }
    echo str_repeat("=", 60) . "\n";
}

// ============================================================================
// ENVIRONMENT CHECK
// ============================================================================

/**
 * Check if testing environment is properly configured
 *
 * @return bool True if environment is ready for testing
 */
function authlite_check_test_environment(): bool
{
    $checks = [];

    // Check PHP version
    $checks['PHP Version >= 7.4'] = version_compare(PHP_VERSION, '7.4.0', '>=');

    // Check required extensions
    $checks['PDO Extension'] = extension_loaded('pdo');
    $checks['PDO SQLite Extension'] = extension_loaded('pdo_sqlite');
    $checks['OpenSSL Extension'] = extension_loaded('openssl');

    // Check file permissions
    $checks['Project Directory Writable'] = is_writable(AUTHLITE_ROOT);

    // Output check results
    $allPassed = true;
    echo "Environment Check:\n";
    foreach ($checks as $check => $result) {
        if ($result) {
            echo "  ✅ $check\n";
        } else {
            echo "  ❌ $check\n";
            $allPassed = false;
        }
    }

    return $allPassed;
}

// ============================================================================
// INITIALIZATION
// ============================================================================

// Auto-initialize if this file is included directly
if (php_sapi_name() === 'cli' && basename($_SERVER['PHP_SELF']) === 'bootstrap.php') {
    echo "AuthLite Test Bootstrap\n";
    echo "======================\n\n";

    // Check environment
    if (!authlite_check_test_environment()) {
        echo "\n❌ Environment check failed. Cannot run tests.\n";
        exit(1);
    }

    echo "\n✅ Environment ready for testing.\n";
    echo "Use the helper functions from this bootstrap in your tests.\n";
}

// ============================================================================
// CLEANUP REGISTRATION
// ============================================================================

// Register cleanup function for end of script execution
register_shutdown_function(function() {
    // Clean up session
    authlite_destroy_test_session();

    // Note: We don't auto-cleanup database to allow inspection after tests
    // Call authlite_cleanup_test_database() explicitly when needed
});

// ============================================================================
// END OF BOOTSTRAP
// ============================================================================

// Prevent direct execution with output
if (!defined('AUTHLITE_TESTING')) {
    define('AUTHLITE_TESTING', true);
}
