<?php
/**
 * Unit tests for AuthLitePassword class
 *
 * @package AuthLite
 */

declare(strict_types=1);

// Include test bootstrap
require_once dirname(__DIR__, 2) . '/tests/bootstrap.php';

/**
 * @covers \AuthLitePassword
 */
final class AuthLitePasswordTest
{
    /**
     * @var array Test configuration
     */
    private static $config;

    /**
     * Setup before tests run
     */
    public static function setUpBeforeClass(): void
    {
        // Create test configuration
        self::$config = [
            'security' => [
                'hash_algo' => PASSWORD_BCRYPT,
                'cost' => 10
            ]
        ];
    }

    /**
     * Test password hashing
     */
    public function testHash(): void
    {
        $password = 'TestPassword123!';

        $hash = AuthLitePassword::hash($password, self::$config);

        authlite_assert(
            is_string($hash) && strlen($hash) > 0,
            'Password hash should be a non-empty string'
        );

        authlite_assert(
            password_get_info($hash)['algo'] === PASSWORD_BCRYPT,
            'Password should be hashed with bcrypt'
        );
    }

    /**
     * Test password verification with correct password
     */
    public function testVerifyWithCorrectPassword(): void
    {
        $password = 'TestPassword123!';
        $hash = AuthLitePassword::hash($password, self::$config);

        $result = AuthLitePassword::verify($password, $hash);

        authlite_assert($result, 'Password verification should pass with correct password');
    }

    /**
     * Test password verification with incorrect password
     */
    public function testVerifyWithIncorrectPassword(): void
    {
        $password = 'TestPassword123!';
        $wrongPassword = 'WrongPassword123!';
        $hash = AuthLitePassword::hash($password, self::$config);

        $result = AuthLitePassword::verify($wrongPassword, $hash);

        authlite_assert(!$result, 'Password verification should fail with incorrect password');
    }

    /**
     * Test password rehashing detection
     */
    public function testNeedsRehash(): void
    {
        $password = 'TestPassword123!';

        // Create hash with low cost
        $lowCostConfig = array_merge_recursive(self::$config, [
            'security' => ['cost' => 8]
        ]);
        $hash = AuthLitePassword::hash($password, $lowCostConfig);

        // Check with higher cost (should need rehash)
        $result = AuthLitePassword::needsRehash($hash, self::$config);

        authlite_assert($result, 'Password should need rehash when cost increases');

        // Check with same cost (should not need rehash)
        $sameCostConfig = array_merge_recursive(self::$config, [
            'security' => ['cost' => 8]
        ]);
        $result = AuthLitePassword::needsRehash($hash, $sameCostConfig);

        authlite_assert(!$result, 'Password should not need rehash with same cost');
    }

    /**
     * Test password generation
     */
    public function testGenerate(): void
    {
        // Test default length
        $password = AuthLitePassword::generate();

        authlite_assert(
            is_string($password) && strlen($password) >= 12,
            'Generated password should be at least 12 characters by default'
        );

        // Test custom length
        $customLength = 16;
        $password = AuthLitePassword::generate($customLength);

        authlite_assert(
            strlen($password) >= $customLength,
            'Generated password should be at least the specified length'
        );

        // Test password contains different character types
        authlite_assert(
            preg_match('/[A-Z]/', $password) === 1,
            'Generated password should contain uppercase letters'
        );

        authlite_assert(
            preg_match('/[a-z]/', $password) === 1,
            'Generated password should contain lowercase letters'
        );

        authlite_assert(
            preg_match('/[0-9]/', $password) === 1,
            'Generated password should contain numbers'
        );

        authlite_assert(
            preg_match('/[^A-Za-z0-9]/', $password) === 1,
            'Generated password should contain special characters'
        );
    }

    /**
     * Test password strength validation
     */
    public function testValidateStrength(): void
    {
        // Test weak password
        $weakPassword = 'weak';
        $result = AuthLitePassword::validateStrength($weakPassword);

        authlite_assert(
            $result['valid'] === false,
            'Weak password should fail validation'
        );

        authlite_assert(
            count($result['errors']) > 0,
            'Weak password should have error messages'
        );

        // Test strong password
        $strongPassword = 'StrongPass123!';
        $result = AuthLitePassword::validateStrength($strongPassword);

        authlite_assert(
            $result['valid'] === true,
            'Strong password should pass validation'
        );

        authlite_assert(
            empty($result['errors']),
            'Strong password should have no error messages'
        );

        // Test minimum length validation
        $shortPassword = 'Aa1!';
        $result = AuthLitePassword::validateStrength($shortPassword, 8);

        authlite_assert(
            $result['valid'] === false,
            'Password shorter than minimum length should fail'
        );

        authlite_assert(
            in_array('Password must be at least 8 characters long', $result['errors']),
            'Should report minimum length error'
        );
    }

    /**
     * Test password strength validation with missing character types
     */
    public function testValidateStrengthMissingCharacterTypes(): void
    {
        // Missing uppercase
        $password = 'lowercase123!';
        $result = AuthLitePassword::validateStrength($password);

        authlite_assert(
            $result['valid'] === false,
            'Password missing uppercase should fail'
        );

        authlite_assert(
            in_array('Password must contain at least one uppercase letter', $result['errors']),
            'Should report missing uppercase error'
        );

        // Missing lowercase
        $password = 'UPPERCASE123!';
        $result = AuthLitePassword::validateStrength($password);

        authlite_assert(
            $result['valid'] === false,
            'Password missing lowercase should fail'
        );

        authlite_assert(
            in_array('Password must contain at least one lowercase letter', $result['errors']),
            'Should report missing lowercase error'
        );

        // Missing number
        $password = 'NoNumbers!';
        $result = AuthLitePassword::validateStrength($password);

        authlite_assert(
            $result['valid'] === false,
            'Password missing numbers should fail'
        );

        authlite_assert(
            in_array('Password must contain at least one number', $result['errors']),
            'Should report missing number error'
        );

        // Missing special character
        $password = 'NoSpecial123';
        $result = AuthLitePassword::validateStrength($password);

        authlite_assert(
            $result['valid'] === false,
            'Password missing special characters should fail'
        );

        authlite_assert(
            in_array('Password must contain at least one special character', $result['errors']),
            'Should report missing special character error'
        );
    }

    /**
     * Run all tests
     */
    public static function run(): void
    {
        authlite_test_section('AuthLitePassword Tests');

        $test = new self();
        $test->setUpBeforeClass();

        $tests = [
            'testHash',
            'testVerifyWithCorrectPassword',
            'testVerifyWithIncorrectPassword',
            'testNeedsRehash',
            'testGenerate',
            'testValidateStrength',
            'testValidateStrengthMissingCharacterTypes'
        ];

        $passed = 0;
        $failed = 0;

        foreach ($tests as $testMethod) {
            try {
                $test->$testMethod();
                $passed++;
            } catch (Exception $e) {
                echo "❌ $testMethod failed: " . $e->getMessage() . "\n";
                $failed++;
            }
        }

        authlite_test_summary($passed, $failed);

        if ($failed > 0) {
            exit(1);
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    // Check if AuthLitePassword class exists
    if (!class_exists('AuthLitePassword')) {
        echo "❌ AuthLitePassword class not found. Make sure AuthLite is properly included.\n";
        exit(1);
    }

    AuthLitePasswordTest::run();
}
