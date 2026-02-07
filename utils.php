<?php
/**
 * AuthLite Utility Functions
 *
 * A collection of helper functions for authentication, security,
 * validation, and common operations used throughout AuthLite.
 *
 * @package AuthLite
 */

/**
 * Password-related utilities
 */
class AuthLitePassword
{
    /**
     * Hash a password using bcrypt
     *
     * @param string $password Plain text password
     * @param array $config Configuration array
     * @return string Hashed password
     * @throws Exception If hashing fails
     */
    public static function hash(string $password, array $config): string
    {
        $algo = $config['security']['hash_algo'] ?? PASSWORD_BCRYPT;
        $options = [
            'cost' => $config['security']['cost'] ?? 12
        ];

        $hash = password_hash($password, $algo, $options);

        if ($hash === false) {
            throw new Exception("Password hashing failed");
        }

        return $hash;
    }

    /**
     * Verify a password against a hash
     *
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool True if password matches
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a hash needs to be rehashed
     *
     * @param string $hash Hashed password
     * @param array $config Configuration array
     * @return bool True if hash needs rehashing
     */
    public static function needsRehash(string $hash, array $config): bool
    {
        $algo = $config['security']['hash_algo'] ?? PASSWORD_BCRYPT;
        $options = [
            'cost' => $config['security']['cost'] ?? 12
        ];

        return password_needs_rehash($hash, $algo, $options);
    }

    /**
     * Generate a random password
     *
     * @param int $length Password length (default: 12)
     * @return string Random password
     */
    public static function generate(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }

    /**
     * Validate password strength
     *
     * @param string $password Password to validate
     * @param int $minLength Minimum length (default: 8)
     * @return array Validation result with 'valid' bool and 'errors' array
     */
    public static function validateStrength(string $password, int $minLength = 8): array
    {
        $errors = [];

        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

/**
 * Token-related utilities
 */
class AuthLiteToken
{
    /**
     * Generate a random token
     *
     * @param int $length Token length in bytes (default: 32)
     * @return string Base64-encoded token
     */
    public static function generate(int $length = 32): string
    {
        $bytes = random_bytes($length);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Generate a JWT token
     *
     * @param array $payload Token payload data
     * @param array $config Configuration array
     * @return string JWT token
     */
    public static function generateJWT(array $payload, array $config): string
    {
        $secret = $config['security']['app_secret'] ?? '';
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        // Add standard claims
        $now = time();
        $payload = array_merge([
            'iat' => $now,
            'exp' => $now + ($config['jwt']['expiration'] ?? 3600),
            'iss' => $config['jwt']['issuer'] ?? 'authlite',
            'aud' => $config['jwt']['audience'] ?? 'authlite-client'
        ], $payload);

        // Encode header and payload
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        // Create signature
        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $secret,
            true
        );

        $signatureEncoded = self::base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Verify a JWT token
     *
     * @param string $token JWT token
     * @param array $config Configuration array
     * @return array|false Decoded payload or false if invalid
     */
    public static function verifyJWT(string $token, array $config)
    {
        $secret = $config['security']['app_secret'] ?? '';
        $leeway = $config['jwt']['leeway'] ?? 60;

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

        // Verify signature
        $signature = self::base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $secret,
            true
        );

        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }

        // Decode payload
        $payloadJson = self::base64UrlDecode($payloadEncoded);
        $payload = json_decode($payloadJson, true);

        if ($payload === null) {
            return false;
        }

        // Verify expiration with leeway
        $now = time();
        if (isset($payload['exp']) && $payload['exp'] < ($now - $leeway)) {
            return false;
        }

        // Verify issuer and audience if specified
        if (isset($config['jwt']['issuer']) && isset($payload['iss']) && $payload['iss'] !== $config['jwt']['issuer']) {
            return false;
        }

        if (isset($config['jwt']['audience']) && isset($payload['aud']) && $payload['aud'] !== $config['jwt']['audience']) {
            return false;
        }

        return $payload;
    }

    /**
     * Base64 URL encode
     *
     * @param string $data Data to encode
     * @return string URL-safe base64 encoded string
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     *
     * @param string $data URL-safe base64 encoded string
     * @return string Decoded data
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    /**
     * Generate a confirmation token with expiry
     *
     * @param array $config Configuration array
     * @return array Token and expiry timestamp
     */
    public static function generateConfirmationToken(array $config): array
    {
        $token = self::generate(24);
        $expires = time() + ($config['email']['confirm_expiry'] ?? 86400);

        return [
            'token' => $token,
            'expires' => $expires
        ];
    }
}

/**
 * Validation utilities
 */
class AuthLiteValidator
{
    /**
     * Validate an email address
     *
     * @param string $email Email address
     * @return bool True if email is valid
     */
    public static function email(string $email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        // Additional validation for domain
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }

        $domain = $parts[1];

        // Check for MX record
        if (!function_exists('checkdnsrr') || !checkdnsrr($domain, 'MX')) {
            // Fallback: at least check domain format
            return preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain);
        }

        return true;
    }

    /**
     * Sanitize input string
     *
     * @param string $input Input string
     * @param bool $stripTags Whether to strip HTML tags
     * @return string Sanitized string
     */
    public static function sanitize(string $input, bool $stripTags = true): string
    {
        if ($stripTags) {
            $input = strip_tags($input);
        }

        $input = trim($input);
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $input;
    }

    /**
     * Validate CSRF token
     *
     * @param string $token Token from form
     * @param string $sessionToken Token from session
     * @return bool True if tokens match
     */
    public static function csrf(string $token, string $sessionToken): bool
    {
        return hash_equals($sessionToken, $token);
    }

    /**
     * Validate URL
     *
     * @param string $url URL to validate
     * @param array $allowedSchemes Allowed URL schemes (default: ['http', 'https'])
     * @return bool True if URL is valid
     */
    public static function url(string $url, array $allowedSchemes = ['http', 'https']): bool
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            return false;
        }

        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], $allowedSchemes)) {
            return false;
        }

        if (!isset($parsed['host'])) {
            return false;
        }

        return true;
    }
}

/**
 * HTTP and session utilities
 */
class AuthLiteHttp
{
    /**
     * Get client IP address
     *
     * @return string IP address
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                foreach (explode(',', $_SERVER[$header]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get user agent
     *
     * @return string User agent string
     */
    public static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Redirect to URL
     *
     * @param string $url URL to redirect to
     * @param int $status HTTP status code (default: 302)
     */
    public static function redirect(string $url, int $status = 302): void
    {
        if (headers_sent()) {
            echo '<script>window.location.href="' . htmlspecialchars($url) . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
        } else {
            header("Location: $url", true, $status);
        }

        exit;
    }

    /**
     * Send JSON response
     *
     * @param mixed $data Data to send as JSON
     * @param int $status HTTP status code (default: 200)
     */
    public static function json($data, int $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Get current URL
     *
     * @param bool $includeQuery Include query string
     * @return string Current URL
     */
    public static function getCurrentUrl(bool $includeQuery = true): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = $_SERVER['REQUEST_URI'] ?? '/';

        if (!$includeQuery) {
            $path = strtok($path, '?');
        }

        return $protocol . '://' . $host . $path;
    }
}

/**
 * Debug and logging utilities
 */
class AuthLiteDebug
{
    /**
     * @var bool Debug mode flag
     */
    private static $enabled = false;

    /**
     * @var string Log file path
     */
    private static $logFile = '';

    /**
     * Initialize debug settings
     *
     * @param array $config Configuration array
     */
    public static function init(array $config): void
    {
        self::$enabled = $config['debug']['enabled'] ?? false;
        self::$logFile = __DIR__ . '/authlite.log';

        if ($config['debug']['log_errors'] ?? true) {
            ini_set('log_errors', '1');
            ini_set('error_log', self::$logFile);
        }

        if ($config['debug']['display_errors'] ?? false) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            error_reporting(0);
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context data
     */
    public static function log(string $message, array $context = []): void
    {
        if (!self::$enabled) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';

        $logMessage = "[$timestamp] $message$contextStr" . PHP_EOL;

        file_put_contents(self::$logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log an error
     *
     * @param string $message Error message
     * @param Exception|null $exception Exception object
     */
    public static function error(string $message, ?Exception $exception = null): void
    {
        $context = [];

        if ($exception !== null) {
            $context['exception'] = [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        self::log("ERROR: $message", $context);
        error_log("AuthLite Error: $message");
    }

    /**
     * Dump variable for debugging
     *
     * @param mixed $var Variable to dump
     * @param string $label Optional label
     */
    public static function dump($var, string $label = ''): void
    {
        if (!self::$enabled) {
            return;
        }

        ob_start();
        echo $label ? "$label: " : '';
        var_dump($var);
        $output = ob_get_clean();

        self::log($output);
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool True if debug mode is enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}

/**
 * Date and time utilities
 */
class AuthLiteTime
{
    /**
     * Format a datetime for SQLite
     *
     * @param int|null $timestamp Unix timestamp (null for current time)
     * @return string Formatted datetime string
     */
    public static function sqlite(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Parse SQLite datetime string
     *
     * @param string $datetime SQLite datetime string
     * @return int Unix timestamp
     */
    public static function fromSqlite(string $datetime): int
    {
        return strtotime($datetime);
    }

    /**
     * Check if a timestamp is expired
     *
     * @param int $timestamp Unix timestamp
     * @param int $leeway Leeway in seconds (default: 0)
     * @return bool True if expired
     */
    public static function isExpired(int $timestamp, int $leeway = 0): bool
    {
        return $timestamp < (time() - $leeway);
    }

    /**
     * Calculate time remaining
     *
     * @param int $expiryTimestamp Expiry timestamp
     * @return int Seconds remaining (negative if expired)
     */
    public static function timeRemaining(int $expiryTimestamp): int
    {
        return $expiryTimestamp - time();
    }

    /**
     * Format time remaining in human-readable format
     *
     * @param int $seconds Seconds remaining
     * @return string Human-readable time
     */
    public static function formatRemaining(int $seconds): string
    {
        if ($seconds < 0) {
            return 'expired';
        }

        if ($seconds < 60) {
            return "$seconds seconds";
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return "$minutes minutes";
        }

        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            return "$hours hours";
        }

        $days = floor($seconds / 86400);
        return "$days days";
    }
}

/**
 * Convenience functions for easy access
 */

/**
 * Generate a random token
 *
 * @param int $length Token length in bytes
 * @return string Base64-encoded token
 */
function authlite_generate_token(int $length = 32): string
{
    return AuthLiteToken::generate($length);
}

/**
 * Hash a password
 *
 * @param string $password Plain text password
 * @param array $config Configuration array
 * @return string Hashed password
 */
function authlite_hash_password(string $password, array $config): string
{
    return AuthLitePassword::hash($password, $config);
}

/**
 * Validate an email address
 *
 * @param string $email Email address
 * @return bool True if email is valid
 */
function authlite_validate_email(string $email): bool
{
    return AuthLiteValidator::email($email);
}

/**
 * Get client IP address
 *
 * @return string IP address
 */
function authlite_get_client_ip(): string
{
    return AuthLiteHttp::getClientIp();
}

/**
 * Sanitize input string
 *
 * @param string $input Input string
 * @param bool $stripTags Whether to strip HTML tags
 * @return string Sanitized string
 */
function authlite_sanitize(string $input, bool $stripTags = true): string
{
    return AuthLiteValidator::sanitize($input, $stripTags);
}
