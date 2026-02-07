<?php
/**
 * AuthLite - Lightweight PHP Authentication Library
 *
 * Main authentication class that handles user sessions, JWT tokens,
 * social logins, and provides a simple API for authentication.
 *
 * @package AuthLite
 */

/**
 * Main AuthLite class
 */
class AuthLite
{
    /**
     * @var array Configuration array
     */
    private static $config = [];

    /**
     * @var PDO Database connection
     */
    private static $db = null;

    /**
     * @var array Current user data
     */
    private static $user = null;

    /**
     * @var bool Initialization status
     */
    private static $initialized = false;

    /**
     * @var string CSRF token for current session
     */
    private static $csrfToken = '';

    /**
     * @var string Current session ID
     */
    private static $sessionId = '';

    /**
     * Initialize AuthLite
     *
     * This should be called early in your application, before any output.
     * It starts the session, sets secure flags, checks login state,
     * and returns the current user array or false.
     *
     * @param array|null $config Optional configuration array
     * @return array|false Current user data or false if not logged in
     * @throws Exception If initialization fails
     */
    public static function init(?array $config = null)
    {
        // Prevent multiple initializations
        if (self::$initialized) {
            return self::$user;
        }

        // Load configuration
        self::loadConfig($config);

        // Initialize debug mode
        if (class_exists('AuthLiteDebug')) {
            AuthLiteDebug::init(self::$config);
        }

        // Initialize database
        self::initDatabase();

        // Start session or handle JWT
        if (self::$config['jwt']['enabled'] ?? false) {
            self::handleJWT();
        } else {
            self::startSession();
        }

        // Check authentication status
        self::checkAuth();

        // Generate CSRF token if needed
        self::initCSRF();

        self::$initialized = true;

        // Debug vault access
        self::checkDebugVault();

        return self::$user;
    }

    /**
     * Load configuration
     *
     * @param array|null $config Optional configuration array
     */
    private static function loadConfig(?array $config = null): void
    {
        // Load default config
        $defaultConfig = [];
        if (file_exists(__DIR__ . '/config.php')) {
            $defaultConfig = require __DIR__ . '/config.php';
        }

        // Merge with provided config
        if ($config !== null) {
            self::$config = array_replace_recursive($defaultConfig, $config);
        } else {
            self::$config = $defaultConfig;
        }

        // Ensure required config sections exist
        self::$config['database'] = self::$config['database'] ?? [];
        self::$config['security'] = self::$config['security'] ?? [];
        self::$config['session'] = self::$config['session'] ?? [];
        self::$config['jwt'] = self::$config['jwt'] ?? [];
        self::$config['debug'] = self::$config['debug'] ?? [];
        self::$config['urls'] = self::$config['urls'] ?? [];
        self::$config['oauth'] = self::$config['oauth'] ?? [];
    }

    /**
     * Initialize database connection
     *
     * @throws Exception If database initialization fails
     */
    private static function initDatabase(): void
    {
        if (class_exists('AuthLiteDB')) {
            self::$db = AuthLiteDB::init(self::$config);
        } else {
            // Fallback simple PDO connection
            $dbPath = self::$config['database']['path'] ?? __DIR__ . '/auth.db';
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            self::$db = new PDO("sqlite:" . $dbPath);
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
    }

    /**
     * Start PHP session with secure settings
     */
    private static function startSession(): void
    {
        // Set session cookie parameters
        $sessionConfig = self::$config['session'];
        session_set_cookie_params([
            'lifetime' => $sessionConfig['lifetime'] ?? 7200,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => $sessionConfig['secure'] ?? true,
            'httponly' => $sessionConfig['httponly'] ?? true,
            'samesite' => $sessionConfig['samesite'] ?? 'Lax'
        ]);

        // Set session name
        $sessionName = $sessionConfig['name'] ?? 'authlite_session';
        session_name($sessionName);

        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::$sessionId = session_id();

        // Regenerate session ID periodically for security
        $regenerateAfter = 300; // 5 minutes
        if (!isset($_SESSION['authlite_last_regenerate'])) {
            $_SESSION['authlite_last_regenerate'] = time();
        } elseif (time() - $_SESSION['authlite_last_regenerate'] > $regenerateAfter) {
            session_regenerate_id(true);
            $_SESSION['authlite_last_regenerate'] = time();
            self::$sessionId = session_id();
        }
    }

    /**
     * Handle JWT-based authentication
     */
    private static function handleJWT(): void
    {
        // Check for JWT in Authorization header
        $jwt = self::getBearerToken();

        if ($jwt && class_exists('AuthLiteToken')) {
            $payload = AuthLiteToken::verifyJWT($jwt, self::$config);
            if ($payload && isset($payload['user_id'])) {
                // Store user ID from JWT
                $_SESSION['authlite_user_id'] = $payload['user_id'];
                $_SESSION['authlite_jwt'] = true;
            }
        }

        // Still use session for CSRF and other session data
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Get Bearer token from Authorization header
     *
     * @return string|null JWT token or null
     */
    private static function getBearerToken(): ?string
    {
        $headers = getallheaders();

        // Check Authorization header
        if (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s+(\S+)/', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }

        // Check $_SERVER as fallback
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            if (preg_match('/Bearer\s+(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Check authentication status
     */
    private static function checkAuth(): void
    {
        // Check if user is already logged in
        if (isset($_SESSION['authlite_user_id'])) {
            $userId = (int) $_SESSION['authlite_user_id'];
            self::loadUser($userId);
            return;
        }

        // Check for "remember me" cookie
        if (isset($_COOKIE['authlite_remember'])) {
            $token = $_COOKIE['authlite_remember'];
            self::checkRememberToken($token);
        }

        // User is not authenticated
        self::$user = false;
    }

    /**
     * Load user data from database
     *
     * @param int $userId User ID
     */
    private static function loadUser(int $userId): void
    {
        try {
            $stmt = self::$db->prepare("
                SELECT id, email, username, is_confirmed, created_at, last_login, is_active
                FROM users
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Update last login if not updated recently
                $updateThreshold = 300; // 5 minutes
                $shouldUpdate = true;

                if ($user['last_login']) {
                    $lastLogin = strtotime($user['last_login']);
                    if (time() - $lastLogin < $updateThreshold) {
                        $shouldUpdate = false;
                    }
                }

                if ($shouldUpdate) {
                    $stmt = self::$db->prepare("
                        UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?
                    ");
                    $stmt->execute([$userId]);
                }

                self::$user = $user;
                $_SESSION['authlite_user'] = $user; // Cache in session
            } else {
                // User not found or inactive
                self::logout();
                self::$user = false;
            }
        } catch (PDOException $e) {
            error_log("Failed to load user: " . $e->getMessage());
            self::$user = false;
        }
    }

    /**
     * Check "remember me" token
     *
     * @param string $token Remember token
     */
    private static function checkRememberToken(string $token): void
    {
        try {
            $stmt = self::$db->prepare("
                SELECT t.user_id, u.is_active
                FROM tokens t
                JOIN users u ON t.user_id = u.id
                WHERE t.token = ? AND t.type = 'remember'
                AND t.expires_at > CURRENT_TIMESTAMP AND t.revoked = 0
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['is_active']) {
                $_SESSION['authlite_user_id'] = $result['user_id'];
                self::loadUser($result['user_id']);

                // Extend remember token
                $newExpiry = date('Y-m-d H:i:s', time() + 2592000); // 30 days
                $stmt = self::$db->prepare("
                    UPDATE tokens SET expires_at = ? WHERE token = ?
                ");
                $stmt->execute([$newExpiry, $token]);
            }
        } catch (PDOException $e) {
            error_log("Remember token check failed: " . $e->getMessage());
        }
    }

    /**
     * Initialize CSRF protection
     */
    private static function initCSRF(): void
    {
        if (!isset($_SESSION['authlite_csrf_token'])) {
            $_SESSION['authlite_csrf_token'] = bin2hex(random_bytes(32));
        }
        self::$csrfToken = $_SESSION['authlite_csrf_token'];
    }

    /**
     * Authenticate user with email and password
     *
     * @param string $email User email
     * @param string $password User password
     * @param bool $remember Remember user across sessions
     * @return array|false User data on success, false on failure
     */
    public static function login(string $email, string $password, bool $remember = false)
    {
        if (!self::$initialized) {
            self::init();
        }

        try {
            $stmt = self::$db->prepare("
                SELECT id, email, password_hash, is_confirmed, is_active
                FROM users
                WHERE email = ? AND is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return false;
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return false;
            }

            // Check if password needs rehashing
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, [
                'cost' => self::$config['security']['cost'] ?? 12
            ])) {
                $newHash = password_hash($password, PASSWORD_BCRYPT, [
                    'cost' => self::$config['security']['cost'] ?? 12
                ]);
                $stmt = self::$db->prepare("
                    UPDATE users SET password_hash = ? WHERE id = ?
                ");
                $stmt->execute([$newHash, $user['id']]);
            }

            // Set session
            $_SESSION['authlite_user_id'] = $user['id'];
            self::loadUser($user['id']);

            // Handle "remember me"
            if ($remember) {
                self::setRememberToken($user['id']);
            }

            // Generate JWT if enabled
            if (self::$config['jwt']['enabled'] ?? false) {
                self::generateJWT($user['id']);
            }

            return self::$user;
        } catch (PDOException $e) {
            error_log("Login failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set "remember me" token
     *
     * @param int $userId User ID
     */
    private static function setRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 2592000); // 30 days

        try {
            $stmt = self::$db->prepare("
                INSERT INTO tokens (token, type, user_id, expires_at)
                VALUES (?, 'remember', ?, ?)
            ");
            $stmt->execute([$token, $userId, $expires]);

            // Set cookie
            setcookie('authlite_remember', $token, [
                'expires' => time() + 2592000,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'secure' => self::$config['session']['secure'] ?? true,
                'httponly' => true,
                'samesite' => self::$config['session']['samesite'] ?? 'Lax'
            ]);
        } catch (PDOException $e) {
            error_log("Failed to set remember token: " . $e->getMessage());
        }
    }

    /**
     * Generate JWT for user
     *
     * @param int $userId User ID
     * @return string|false JWT token or false
     */
    private static function generateJWT(int $userId)
    {
        if (!class_exists('AuthLiteToken')) {
            return false;
        }

        $payload = [
            'user_id' => $userId,
            'session_id' => session_id()
        ];

        return AuthLiteToken::generateJWT($payload, self::$config);
    }

    /**
     * Logout current user
     *
     * @return bool True on success
     */
    public static function logout(): bool
    {
        if (!self::$initialized) {
            return false;
        }

        // Clear session
        unset($_SESSION['authlite_user_id']);
        unset($_SESSION['authlite_user']);
        unset($_SESSION['authlite_jwt']);

        // Clear remember token
        if (isset($_COOKIE['authlite_remember'])) {
            $token = $_COOKIE['authlite_remember'];
            try {
                $stmt = self::$db->prepare("
                    UPDATE tokens SET revoked = 1 WHERE token = ? AND type = 'remember'
                ");
                $stmt->execute([$token]);
            } catch (PDOException $e) {
                error_log("Failed to revoke remember token: " . $e->getMessage());
            }

            setcookie('authlite_remember', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'secure' => self::$config['session']['secure'] ?? true,
                'httponly' => true,
                'samesite' => self::$config['session']['samesite'] ?? 'Lax'
            ]);
        }

        // Clear user data
        self::$user = false;

        // Regenerate session ID
        session_regenerate_id(true);

        return true;
    }

    /**
     * Register a new user
     *
     * @param string $email User email
     * @param string $password User password
     * @param string $username Optional username (defaults to email)
     * @param bool $sendConfirmation Send confirmation email
     * @return array|false User data on success, false on failure
     */
    public static function register(string $email, string $password, string $username = '', bool $sendConfirmation = true)
    {
        if (!self::$initialized) {
            self::init();
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Validate password strength
        if (class_exists('AuthLitePassword')) {
            $validation = AuthLitePassword::validateStrength($password);
            if (!$validation['valid']) {
                return false;
            }
        }

        // Check if email already exists
        try {
            $stmt = self::$db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return false;
            }
        } catch (PDOException $e) {
            error_log("Email check failed: " . $e->getMessage());
            return false;
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, [
            'cost' => self::$config['security']['cost'] ?? 12
        ]);

        // Generate confirmation token if needed
        $confirmationToken = null;
        $confirmationExpires = null;

        if ($sendConfirmation && class_exists('AuthLiteToken')) {
            $tokenData = AuthLiteToken::generateConfirmationToken(self::$config);
            $confirmationToken = $tokenData['token'];
            $confirmationExpires = date('Y-m-d H:i:s', $tokenData['expires']);
        }

        // Insert user
        try {
            $stmt = self::$db->prepare("
                INSERT INTO users (email, username, password_hash, is_confirmed, confirmation_token, confirmation_expires)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $username = $username ?: $email;
            $isConfirmed = $sendConfirmation ? 0 : 1;

            $stmt->execute([
                $email,
                $username,
                $hashedPassword,
                $isConfirmed,
                $confirmationToken,
                $confirmationExpires
            ]);

            $userId = self::$db->lastInsertId();

            // Send confirmation email
            if ($sendConfirmation && $confirmationToken) {
                self::sendConfirmationEmail($email, $confirmationToken);
            }

            // Auto-login if no confirmation required
            if (!$sendConfirmation) {
                $_SESSION['authlite_user_id'] = $userId;
                self::loadUser($userId);
            }

            return [
                'id' => $userId,
                'email' => $email,
                'username' => $username,
                'is_confirmed' => !$sendConfirmation
            ];
        } catch (PDOException $e) {
            error_log("Registration failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send confirmation email
     *
     * @param string $email User email
     * @param string $token Confirmation token
     * @return bool True on success
     */
    private static function sendConfirmationEmail(string $email, string $token): bool
    {
        $config = self::$config['email'] ?? [];
        $urls = self::$config['urls'] ?? [];

        $confirmUrl = ($urls['base'] ?? '') . ($urls['confirm'] ?? '/confirm') . '?token=' . urlencode($token);

        $subject = "Confirm your email address";
        $message = "Please click the following link to confirm your email address:\n\n";
        $message .= $confirmUrl . "\n\n";
        $message .= "This link will expire in 24 hours.\n";

        $headers = "From: " . ($config['from_name'] ?? 'AuthLite') . " <" . ($config['from'] ?? 'noreply@example.com') . ">\r\n";
        $headers .= "Reply-To: " . ($config['from'] ?? 'noreply@example.com') . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        return mail($email, $subject, $message, $headers);
    }

    /**
     * Confirm user email with token
     *
     * @param string $token Confirmation token
     * @return bool True on success
     */
    public static function confirmEmail(string $token): bool
    {
        if (!self::$initialized) {
            self::init();
        }

        try {
            $stmt = self::$db->prepare("
                SELECT id FROM users
                WHERE confirmation_token = ?
                AND confirmation_expires > CURRENT_TIMESTAMP
                AND is_confirmed = 0
            ");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return false;
            }

            // Confirm user
            $stmt = self::$db->prepare("
                UPDATE users SET
                    is_confirmed = 1,
                    confirmation_token = NULL,
                    confirmation_expires = NULL
                WHERE id = ?
            ");
            $stmt->execute([$user['id']]);

            // Auto-login the user
            $_SESSION['authlite_user_id'] = $user['id'];
            self::loadUser($user['id']);

            return true;
        } catch (PDOException $e) {
            error_log("Email confirmation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current user
     *
     * @return array|false User data or false if not logged in
     */
    public static function user()
    {
        if (!self::$initialized) {
            self::init();
        }
        return self::$user;
    }

    /**
     * Check if user is logged in
     *
     * @return bool True if user is logged in
     */
    public static function check(): bool
    {
        if (!self::$initialized) {
            self::init();
        }
        return self::$user !== false;
    }

    /**
     * Get CSRF token
     *
     * @return string CSRF token
     */
    public static function csrfToken(): string
    {
        if (!self::$initialized) {
            self::init();
        }
        return self::$csrfToken;
    }

    /**
     * Verify CSRF token
     *
     * @param string $token Token to verify
     * @return bool True if token is valid
     */
    public static function verifyCsrf(string $token): bool
    {
        return hash_equals(self::$csrfToken, $token);
    }

    /**
     * Handle OAuth login
     *
     * @param string $provider OAuth provider name
     * @return string|false Redirect URL or false
     */
    public static function oauthLogin(string $provider)
    {
        if (!class_exists('AuthLiteOAuth')) {
            return false;
        }

        if (!self::$initialized) {
            self::init();
        }

        // Generate state token for CSRF protection
        $state = bin2hex(random_bytes(16));
        $_SESSION['authlite_oauth_state'] = $state;
        $_SESSION['authlite_oauth_provider'] = $provider;

        return AuthLiteOAuth::getAuthUrl($provider, $state);
    }

    /**
     * Handle OAuth callback
     *
     * @return array|false User data or false
     */
    public static function oauthCallback()
    {
        if (!class_exists('AuthLiteOAuth')) {
            return false;
        }

        if (!self::$initialized) {
            self::init();
        }

        // Verify state
        $state = $_GET['state'] ?? $_POST['state'] ?? '';
        $sessionState = $_SESSION['authlite_oauth_state'] ?? '';
        $provider = $_SESSION['authlite_oauth_provider'] ?? '';

        if (empty($state) || empty($sessionState) || empty($provider)) {
            return false;
        }

        if (!hash_equals($sessionState, $state)) {
            return false;
        }

        // Get authorization code
        $code = $_GET['code'] ?? $_POST['code'] ?? '';
        if (empty($code)) {
            return false;
        }

        // Handle callback
        $profile = AuthLiteOAuth::handleCallback($provider, $code, $state, $sessionState);
        if (!$profile) {
            return false;
        }

        // Create or update user
        $user = AuthLiteOAuth::createOrUpdateUser($profile);
        if (!$user) {
            return false;
        }

        // Log in the user
        $_SESSION['authlite_user_id'] = $user['id'];
        self::loadUser($user['id']);

        // Clear OAuth session data
        unset($_SESSION['authlite_oauth_state']);
        unset($_SESSION['authlite_oauth_provider']);

        return self::$user;
    }

    /**
     * Get OAuth providers
     *
     * @return array Enabled OAuth providers
     */
    public static function oauthProviders(): array
    {
        if (!class_exists('AuthLiteOAuth')) {
            return [];
        }

        return AuthLiteOAuth::getEnabledProviders();
    }

    /**
     * Check debug vault access
     */
    private static function checkDebugVault(): void
    {
        $debug = self::$config['debug'] ?? [];
        $urls = self::$config['urls'] ?? [];

        if (!($debug['enabled'] ?? false) || empty($debug['master_key'])) {
            return;
        }

        $debugPath = $urls['debug_vault'] ?? '/auth/vault';
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        if ($currentPath === $debugPath) {
            $key = $_GET['key'] ?? $_POST['key'] ?? '';
            if ($key === $debug['master_key']) {
                self::showDebugVault();
                exit;
            } else {
                self::showDebugLogin();
                exit;
            }
        }
    }

    /**
     * Show debug login form
     */
    private static function showDebugLogin(): void
    {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>AuthLite Debug Vault</title>
            <style>
                body { font-family: monospace; margin: 40px; }
                form { max-width: 400px; margin: 0 auto; }
                input { width: 100%; padding: 8px; margin: 5px 0; }
                button { padding: 10px; background: #007bff; color: white; border: none; }
            </style>
        </head>
        <body>
            <h1>AuthLite Debug Vault</h1>
            <form method="post">
                <input type="password" name="key" placeholder="Master Key" required>
                <button type="submit">Access</button>
            </form>
        </body>
        </html>';
    }

    /**
     * Show debug vault interface
     */
    private static function showDebugVault(): void
    {
        $stats = [];
        if (class_exists('AuthLiteDB')) {
            $stats = AuthLiteDB::getStats();
        }

        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>AuthLite Debug Vault</title>
            <style>
                body { font-family: monospace; margin: 40px; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                pre { background: #f5f5f5; padding: 10px; overflow: auto; }
                .danger { background: #ffebee; color: #c62828; }
            </style>
        </head>
        <body>
            <h1>AuthLite Debug Vault</h1>
            <h2>Database Statistics</h2>
            <table>
                <tr><th>Table</th><th>Count</th></tr>';

        foreach ($stats as $table => $count) {
            if ($table !== 'size') {
                echo "<tr><td>$table</td><td>$count</td></tr>";
            }
        }

        if (isset($stats['size'])) {
            $size = number_format($stats['size'] / 1024, 2) . ' KB';
            echo "<tr><td>Database Size</td><td>$size</td></tr>";
        }

        echo '</table>
            <h2>Configuration</h2>
            <pre>' . htmlspecialchars(json_encode(self::$config, JSON_PRETTY_PRINT)) . '</pre>
            <h2>Add User (Debug)</h2>
            <form method="post" action="?key=' . urlencode(self::$config['debug']['master_key']) . '">
                <input type="text" name="username" placeholder="Username" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="add_user">Add User</button>
            </form>';

        // Handle adding user
        if (isset($_POST['add_user'])) {
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            if ($username && $email && $password) {
                $result = self::register($email, $password, $username, false);
                if ($result) {
                    echo '<p style="color: green;">User added successfully!</p>';
                } else {
                    echo '<p style="color: red;">Failed to add user.</p>';
                }
            }
        }

        echo '</body></html>';
    }

    /**
     * Get database connection (for advanced usage)
     *
     * @return PDO Database connection
     */
    public static function getDB(): PDO
    {
        if (!self::$initialized) {
            self::init();
        }
        return self::$db;
    }

    /**
     * Get configuration (for advanced usage)
     *
     * @return array Configuration array
     */
    public static function getConfig(): array
    {
        if (!self::$initialized) {
            self::init();
        }
        return self::$config;
    }
}

/**
 * Convenience function for quick initialization
 *
 * @param array|null $config Optional configuration
 * @return array|false Current user or false
 */
function authlite_init(?array $config = null)
{
    return AuthLite::init($config);
}

/**
 * Get current user
 *
 * @return array|false User data or false
 */
function authlite_user()
{
    return AuthLite::user();
}

/**
 * Check if user is logged in
 *
 * @return bool True if logged in
 */
function authlite_check(): bool
{
    return AuthLite::check();
}

/**
 * Get CSRF token
 *
 * @return string CSRF token
 */
function authlite_csrf_token(): string
{
    return AuthLite::csrfToken();
}
