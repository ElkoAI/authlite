<?php
/**
 * AuthLite Simple Test Script
 *
 * Basic functionality tests for AuthLite authentication library.
 */

// Test configuration
define('TEST_DB_PATH', __DIR__ . '/test_auth.db');
define('TEST_EMAIL', 'test@example.com');
define('TEST_PASSWORD', 'TestPassword123!');
define('TEST_USERNAME', 'testuser');

// Test results tracking
$tests = [];
$currentTest = '';

function start_test($name) {
    global $currentTest;
    $currentTest = $name;
    echo "Testing: $name\n";
}

function record_result($success, $message = '') {
    global $tests, $currentTest;

    $tests[] = [
        'name' => $currentTest,
        'success' => $success,
        'message' => $message
    ];

    if ($success) {
        echo "  PASS: $message\n";
    } else {
        echo "  FAIL: $message\n";
    }
}

// Clean up old test database
if (file_exists(TEST_DB_PATH)) {
    unlink(TEST_DB_PATH);
}

echo "AuthLite Test Suite\n";
echo "===================\n\n";

// Test 1: File existence
start_test("Required files exist");
$required = ['config.php', 'auth.php', 'db.php', 'utils.php', 'oauth.php'];
$missing = [];
foreach ($required as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $missing[] = $file;
    }
}
if (empty($missing)) {
    record_result(true, "All files found");
} else {
    record_result(false, "Missing: " . implode(', ', $missing));
    echo "\nCannot continue - missing required files.\n";
    exit(1);
}

// Test 2: Load configuration
start_test("Load configuration");
try {
    require_once __DIR__ . '/config.php';
    record_result(true, "Configuration loaded");
} catch (Exception $e) {
    record_result(false, "Failed: " . $e->getMessage());
    exit(1);
}

// Test 3: Database initialization
start_test("Database initialization");
try {
    require_once __DIR__ . '/db.php';

    $testConfig = [
        'database' => [
            'path' => TEST_DB_PATH,
            'create_tables' => true
        ],
        'security' => [
            'app_secret' => 'test-secret',
            'cost' => 10
        ],
        'debug' => ['enabled' => true]
    ];

    $pdo = AuthLiteDB::init($testConfig);
    if ($pdo instanceof PDO) {
        record_result(true, "Database connected");
    } else {
        record_result(false, "Connection failed");
    }
} catch (Exception $e) {
    record_result(false, "Exception: " . $e->getMessage());
}

// Test 4: AuthLite initialization
start_test("AuthLite initialization");
try {
    require_once __DIR__ . '/auth.php';

    $authConfig = [
        'database' => [
            'path' => TEST_DB_PATH,
            'create_tables' => false
        ],
        'security' => [
            'app_secret' => 'test-secret',
            'cost' => 10
        ],
        'session' => [
            'secure' => false,
            'httponly' => true
        ],
        'debug' => ['enabled' => true]
    ];

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user = AuthLite::init($authConfig);
    if ($user === false || is_array($user)) {
        record_result(true, "AuthLite initialized");
    } else {
        record_result(false, "Unexpected return value");
    }
} catch (Exception $e) {
    record_result(false, "Exception: " . $e->getMessage());
}

// Test 5: User registration
start_test("User registration");
try {
    $result = AuthLite::register(TEST_EMAIL, TEST_PASSWORD, TEST_USERNAME, false);

    if (is_array($result) && isset($result['id'])) {
        record_result(true, "User registered with ID: " . $result['id']);
    } else {
        record_result(false, "Registration failed");
    }
} catch (Exception $e) {
    record_result(false, "Exception: " . $e->getMessage());
}

// Test 6: Duplicate registration prevention
start_test("Duplicate registration prevention");
try {
    $result = AuthLite::register(TEST_EMAIL, 'AnotherPass123!', 'another', false);

    if ($result === false) {
        record_result(true, "Correctly prevented duplicate");
    } else {
        record_result(false, "Allowed duplicate registration");
    }
} catch (Exception $e) {
    record_result(false, "Exception: " . $e->getMessage());
}

// Test 7: Login with correct credentials
start_test("Login with correct credentials");
try {
    AuthLite::logout(); // Ensure we're logged out

    $result = AuthLite::login(TEST_EMAIL, TEST_PASSWORD);

    if (is_array($result) && $result['email'] === TEST_EMAIL) {
        record_result(true, "Login successful");
    } else {
        record_result(false, "Login failed");
    }
} catch (Exception $e) {
    record_result(false, "Exception: " . $e->getMessage());
}

// Test 8: Login with wrong password
start_test("Login with wrong password");
try {
    $result = AuthLite::login(TEST_EMAIL, 'WrongPassword123!');

    if ($result === false) {
        record_result(true, "Correctly rejected wrong password");
    } else {
        record_result(false, "Accepted wrong password");
    }
} catch (Exception $e) {
    record_result(false, "Exception: " . $e->getMessage());
}

// Test 9: CSRF token functionality
start_test("CSRF token functionality");
try {
    $token = AuthLite::csrfToken();

    if (strlen($token) >= 32) {
        record_result(true, "Token generated (length: " . strlen($token) . ")");

        // Verify token
        if (AuthLite::verifyCsrf($token)) {
            record_result(true, "Token self-verification passed");
        } else {
            record_result(false, "Token self-verification failed");
        }

        // Test wrong token
        if (!AuthLite::verifyCsrf('wrong_token_' . bin2hex(random_bytes(16)))) {
            record_result(true, "Wrong token correctly rejected");
        } else {
            record_result(false, "Wrong token incorrectly accepted");
        }
    } else {
        record_result(false, "Token too short");
    }
} catch (Exception $e) {
    record_result(false, "Exception: " . $e->getMessage());
}

// Test 10: User logout
start_test("User logout");
try {
    $logoutResult = AuthLite::logout();

    if ($logoutResult) {
        $userAfter = AuthLite::user();
        if ($userAfter === false) {
            record_result(true, "Logout successful");
        } else {
            record_result(false, "User still logged in");
        }
    } else {
        record_result(false, "Logout function returned false");
    }
} catch (Exception $e) {
    record_result(false, "Exception: " . $e->getMessage());
}

// Test 11: Utility functions
start_test("Utility functions");
try {
    require_once __DIR__ . '/utils.php';

    if (class_exists('AuthLitePassword')) {
        // Test password validation
        $validation = AuthLitePassword::validateStrength('weak');
        if (!$validation['valid']) {
            record_result(true, "Weak password correctly rejected");
        } else {
            record_result(false, "Weak password incorrectly accepted");
        }

        $validation = AuthLitePassword::validateStrength('StrongPass123!');
        if ($validation['valid']) {
            record_result(true, "Strong password correctly accepted");
        } else {
            record_result(false, "Strong password incorrectly rejected");
        }
    } else {
        record_result(false, "AuthLitePassword class not found");
    }

    if (class_exists('AuthLiteToken')) {
        $token = AuthLiteToken::generate();
        if (strlen($token) >= 32) {
            record_result(true, "Token generation working");
        } else {
            record_result(false, "Generated token too short");
        }
    } else {
        record_result(false, "AuthLiteToken class not found");
    }
} catch (Exception $e) {
    record_result(false, "Exception: " . $e->getMessage());
}

// Summary
echo "\nTest Summary\n";
echo "============\n";

$passed = 0;
$failed = 0;
foreach ($tests as $test) {
    if ($test['success']) {
        $passed++;
    } else {
        $failed++;
    }
}

echo "Total tests: " . count($tests) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed > 0) {
    echo "\nFailed tests:\n";
    foreach ($tests as $test) {
        if (!$test['success']) {
            echo "  - {$test['name']}: {$test['message']}\n";
        }
    }
    exit(1);
} else {
    echo "\nAll tests passed!\n";
    exit(0);
}
