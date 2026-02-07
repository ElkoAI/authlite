<?php
/**
 * AuthLite Front Controller
 *
 * Main entry point for AuthLite authentication system.
 * Handles routing to login, logout, signup, confirmation, and debug endpoints.
 *
 * @package AuthLite
 */

// Display errors during development
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering
ob_start();

// Load AuthLite
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/oauth.php';
require_once __DIR__ . '/auth.php';

// Initialize AuthLite
$user = AuthLite::init();
$isLoggedIn = AuthLite::check();
$csrfToken = AuthLite::csrfToken();
$config = AuthLite::getConfig();

// Get current path
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

// Handle POST requests first
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest();
}

// Route based on path
switch ($path) {
    case '/':
        showHomePage();
        break;
    case '/login':
        showLoginPage();
        break;
    case '/logout':
        handleLogout();
        break;
    case '/signup':
        showSignupPage();
        break;
    case '/confirm':
        handleConfirmation();
        break;
    case '/auth/vault':
        showDebugVault();
        break;
    case '/oauth/callback':
        handleOAuthCallback();
        break;
    default:
        showNotFound();
        break;
}

// Clean output and flush
ob_end_flush();

exit;

/**
 * Handle POST requests
 */
function handlePostRequest(): void {
    global $csrfToken, $config;

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !AuthLite::verifyCsrf($_POST['csrf_token'])) {
        $_SESSION['authlite_error'] = 'Invalid CSRF token. Please try again.';
        header('Location: /');
        exit;
    }

    // Handle login
    if (isset($_POST['login'])) {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if ($email && $password) {
            $result = AuthLite::login($email, $password, $remember);
            if ($result) {
                $_SESSION['authlite_message'] = 'Login successful!';
                header('Location: /');
                exit;
            } else {
                $_SESSION['authlite_error'] = 'Login failed. Invalid email or password.';
                header('Location: /login');
                exit;
            }
        }
    }

    // Handle registration
    if (isset($_POST['register'])) {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $username = $_POST['username'] ?? '';

        if ($email && $password) {
            $sendConfirmation = $config['email']['confirm_expiry'] ?? 86400 > 0;
            $result = AuthLite::register($email, $password, $username, $sendConfirmation);

            if ($result) {
                if ($sendConfirmation) {
                    $_SESSION['authlite_message'] = 'Registration successful! Please check your email for confirmation.';
                } else {
                    $_SESSION['authlite_message'] = 'Registration successful! You are now logged in.';
                }
                header('Location: /');
                exit;
            } else {
                $_SESSION['authlite_error'] = 'Registration failed. Email may already exist or password is too weak.';
                header('Location: /signup');
                exit;
            }
        }
    }

    // Handle OAuth login redirect
    if (isset($_POST['oauth_login'])) {
        $provider = $_POST['provider'] ?? '';
        $redirectUrl = AuthLite::oauthLogin($provider);
        if ($redirectUrl) {
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $_SESSION['authlite_error'] = 'OAuth login is not configured for this provider.';
            header('Location: /login');
            exit;
        }
    }

    // Handle debug vault access
    if (isset($_POST['debug_access'])) {
        $key = $_POST['key'] ?? '';
        $masterKey = $config['debug']['master_key'] ?? '';

        if ($key === $masterKey) {
            $_SESSION['authlite_debug_access'] = true;
            header('Location: /auth/vault?key=' . urlencode($key));
            exit;
        } else {
            $_SESSION['authlite_error'] = 'Invalid master key.';
            header('Location: /auth/vault');
            exit;
        }
    }

    // Handle adding user via debug vault
    if (isset($_POST['add_user_debug'])) {
        if (isset($_SESSION['authlite_debug_access']) && $_SESSION['authlite_debug_access']) {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $username = $_POST['username'] ?? '';

            if ($email && $password) {
                $result = AuthLite::register($email, $password, $username, false);
                if ($result) {
                    $_SESSION['authlite_message'] = 'Debug: User added successfully!';
                } else {
                    $_SESSION['authlite_error'] = 'Debug: Failed to add user.';
                }
            }
        }
        header('Location: /auth/vault');
        exit;
    }
}

/**
 * Show home page
 */
function showHomePage(): void {
    global $user, $isLoggedIn, $csrfToken;

    $message = $_SESSION['authlite_message'] ?? '';
    $error = $_SESSION['authlite_error'] ?? '';

    // Clear messages
    unset($_SESSION['authlite_message']);
    unset($_SESSION['authlite_error']);

    // Get OAuth providers
    $oauthProviders = AuthLite::oauthProviders();

    // Get database stats if available
    $dbStats = [];
    if (class_exists('AuthLiteDB')) {
        $dbStats = AuthLiteDB::getStats();
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AuthLite - Home</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; border-radius: 10px; margin-bottom: 30px; }
            .header h1 { font-size: 2.5rem; margin-bottom: 10px; }
            .nav { display: flex; gap: 10px; justify-content: center; margin-bottom: 30px; }
            .nav a { padding: 10px 20px; background: white; color: #667eea; text-decoration: none; border-radius: 5px; font-weight: 500; }
            .nav a:hover { background: #f0f0f0; }
            .card { background: white; border-radius: 10px; padding: 30px; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .user-info { background: #e8f4fd; border-left: 4px solid #2196f3; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 5px; font-weight: 500; }
            input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
            button, .btn { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
            button:hover, .btn:hover { background: #5a67d8; }
            .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
            .stat { background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; }
            .stat-value { font-size: 24px; font-weight: bold; color: #667eea; }
            .oauth-buttons { display: flex; gap: 10px; margin-top: 20px; }
            .oauth-btn { flex: 1; text-align: center; }
            .footer { text-align: center; margin-top: 40px; color: #666; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔐 AuthLite</h1>
                <p>Lightweight PHP Authentication System</p>
            </div>

            <div class="nav">
                <a href="/">Home</a>
                <?php if ($isLoggedIn): ?>
                    <a href="/logout">Logout</a>
                <?php else: ?>
                    <a href="/login">Login</a>
                    <a href="/signup">Sign Up</a>
                <?php endif; ?>
                <a href="/auth/vault">Debug Vault</a>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <h2>Authentication Status</h2>
                <?php if ($isLoggedIn && $user): ?>
                    <div class="user-info" style="padding: 20px; margin: 20px 0;">
                        <h3>Welcome, <?php echo htmlspecialchars($user['email']); ?>!</h3>
                        <p>You are logged in with the following details:</p>
                        <ul style="margin: 10px 0 10px 20px;">
                            <li>User ID: <?php echo $user['id']; ?></li>
                            <li>Email: <?php echo htmlspecialchars($user['email']); ?></li>
                            <li>Username: <?php echo htmlspecialchars($user['username'] ?? 'Not set'); ?></li>
                            <li>Account Created: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></li>
                            <li>Email Confirmed: <?php echo $user['is_confirmed'] ? 'Yes' : 'No'; ?></li>
                        </ul>
                        <form method="post" action="/logout" style="margin-top: 15px;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <button type="submit" name="logout">Logout</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p>You are not logged in. Please <a href="/login">login</a> or <a href="/signup">register</a>.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($dbStats)): ?>
            <div class="card">
                <h2>System Statistics</h2>
                <div class="stats">
                    <?php foreach ($dbStats as $key => $value): ?>
                        <?php if ($key !== 'size'): ?>
                            <div class="stat">
                                <div class="stat-value"><?php echo $value; ?></div>
                                <div class="stat-label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (isset($dbStats['size'])): ?>
                        <div class="stat">
                            <div class="stat-value"><?php echo number_format($dbStats['size'] / 1024, 2); ?> KB</div>
                            <div class="stat-label">Database Size</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2>Quick Actions</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 20px;">
                    <?php if (!$isLoggedIn): ?>
                        <a href="/login" class="btn" style="display: block; text-align: center;">Login</a>
                        <a href="/signup" class="btn" style="display: block; text-align: center; background: #48bb78;">Sign Up</a>
                    <?php else: ?>
                        <form method="post" action="/logout">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <button type="submit" name="logout" style="width: 100%; background: #f56565;">Logout</button>
                        </form>
                    <?php endif; ?>
                    <a href="/auth/vault" class="btn" style="display: block; text-align: center; background: #805ad5;">Debug Vault</a>
                </div>

                <?php if (!empty($oauthProviders)): ?>
                <div style="margin-top: 20px;">
                    <h3>Social Login</h3>
                    <p>Login using your social media accounts:</p>
                    <div class="oauth-buttons">
                        <?php foreach ($oauthProviders as $provider): ?>
                            <form method="post" class="oauth-btn">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="provider" value="<?php echo htmlspecialchars($provider); ?>">
                                <button type="submit" name="oauth_login" class="btn" style="width: 100%;">
                                    <?php echo ucfirst($provider); ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="footer">
                <p>AuthLite &copy; <?php echo date('Y'); ?> | PHP <?php echo phpversion(); ?> |
                   <a href="https://github.com/yourusername/authlite" style="color: #667eea;">GitHub</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Show login page
 */
function showLoginPage(): void {
    global $csrfToken, $oauthProviders;

    $message = $_SESSION['authlite_message'] ?? '';
    $error = $_SESSION['authlite_error'] ?? '';

    unset($_SESSION['authlite_message']);
    unset($_SESSION['authlite_error']);

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AuthLite - Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .login-container { background: white; border-radius: 10px; padding: 40px; width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
            h1 { text-align: center; color: #333; margin-bottom: 30px; }
            .alert { padding: 10px; border-radius: 5px; margin-bottom: 20px; }
            .alert-success { background: #d4edda; color: #155724; }
            .alert-error { background: #f8d7da; color: #721c24; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 5px; font-weight: 500; }
            input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
            button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
            button:hover { background: #5a67d8; }
            .links { text-align: center; margin-top: 20px; }
            .links a { color: #667eea; text-decoration: none; }
            .oauth-buttons { margin-top: 20px; }
            .oauth-btn { display: block; width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px; background: white; color: #333; cursor: pointer; }
            .oauth-btn:hover { background: #f5f5f5; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h1>🔐 Login</h1>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="you@example.com">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required placeholder="Your password">
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="remember" name="remember" style="width: auto;">
                    <label for="remember" style="margin: 0;">Remember me (30 days)</label>
                </div>

                <button type="submit" name="login">Login</button>
            </form>

            <?php if (!empty($oauthProviders)): ?>
            <div class="oauth-buttons">
                <p style="text-align: center; margin: 20px 0 10px; color: #666;">Or login with:</p>
                <?php foreach ($oauthProviders as $provider): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="provider" value="<?php echo htmlspecialchars($provider); ?>">
                        <button type="submit" name="oauth_login" class="oauth-btn">
                            <?php echo ucfirst($provider); ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="links">
                <p><a href="/signup">Don't have an account? Sign up</a></p>
                <p><a href="/">← Back to Home</a></p>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Show signup page
 */
function showSignupPage(): void {
    global $csrfToken;

    $message = $_SESSION['authlite_message'] ?? '';
    $error = $_SESSION['authlite_error'] ?? '';

    unset($_SESSION['authlite_message']);
    unset($_SESSION['authlite_error']);

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AuthLite - Sign Up</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .signup-container { background: white; border-radius: 10px; padding: 40px; width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
            h1 { text-align: center; color: #333; margin-bottom: 30px; }
            .alert { padding: 10px; border-radius: 5px; margin-bottom: 20px; }
            .alert-success { background: #d4edda; color: #155724; }
            .alert-error { background: #f8d7da; color: #721c24; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 5px; font-weight: 500; }
            input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
            button { width: 100%; padding: 12px; background: #48bb78; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
            button:hover { background: #38a169; }
            .links { text-align: center; margin-top: 20px; }
            .links a { color: #48bb78; text-decoration: none; }
            .password-hint { font-size: 12px; color: #666; margin-top: 5px; }
        </style>
    </head>
    <body>
        <div class="signup-container">
            <h1>📝 Sign Up</h1>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required placeholder="you@example.com">
                </div>

                <div class="form-group">
                    <label for="username">Username (Optional)</label>
                    <input type="text" id="username" name="username" placeholder="Choose a username">
                </div>

                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required placeholder="Minimum 8 characters">
                    <div class="password-hint">Must include uppercase, lowercase, number, and special character.</div>
                </div>

                <button type="submit" name="register">Create Account</button>
            </form>

            <div class="links">
                <p><a href="/login">Already have an account? Login</a></p>
                <p><a href="/">← Back to Home</a></p>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Handle logout
 */
function handleLogout(): void {
    global $csrfToken;

    // Verify CSRF for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !AuthLite::verifyCsrf($_POST['csrf_token'])) {
            $_SESSION['authlite_error'] = 'Invalid CSRF token.';
            header('Location: /');
            exit;
        }
    }

    AuthLite::logout();
    $_SESSION['authlite_message'] = 'You have been logged out.';
    header('Location: /');
    exit;
}

/**
 * Handle email confirmation
 */
function handleConfirmation(): void {
    $token = $_GET['token'] ?? '';

    if ($token) {
        $result = AuthLite::confirmEmail($token);
        if ($result) {
            $_SESSION['authlite_message'] = 'Email confirmed successfully! You are now logged in.';
        } else {
            $_SESSION['authlite_error'] = 'Invalid or expired confirmation token.';
        }
    } else {
        $_SESSION['authlite_error'] = 'No confirmation token provided.';
    }

    header('Location: /');
    exit;
}

/**
 * Show debug vault
 */
function showDebugVault(): void {
    global $config, $csrfToken;

    $debugEnabled = $config['debug']['enabled'] ?? false;
    $masterKey = $config['debug']['master_key'] ?? '';

    // Check access
    $hasAccess = false;
    if (isset($_GET['key']) && $_GET['key'] === $masterKey) {
        $hasAccess = true;
        $_SESSION['authlite_debug_access'] = true;
    } elseif (isset($_SESSION['authlite_debug_access']) && $_SESSION['authlite_debug_access']) {
        $hasAccess = true;
    }

    // Get database stats
    $dbStats = [];
    if (class_exists('AuthLiteDB')) {
        $dbStats = AuthLiteDB::getStats();
    }

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AuthLite - Debug Vault</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: monospace; background: #1a202c; color: #cbd5e0; }
            .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            h1 { color: #805ad5; margin-bottom: 10px; }
            .access-denied { background: #742a2a; color: #fed7d7; padding: 40px; text-align: center; border-radius: 10px; }
            .login-form { max-width: 400px; margin: 0 auto; }
            .login-form input { width: 100%; padding: 12px; margin: 10px 0; background: #2d3748; border: 1px solid #4a5568; color: white; border-radius: 5px; }
            .login-form button { width: 100%; padding: 12px; background: #805ad5; color: white; border: none; border-radius: 5px; cursor: pointer; }
            .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0; }
            .stat { background: #2d3748; padding: 15px; border-radius: 5px; text-align: center; border: 1px solid #4a5568; }
            .stat-value { font-size: 24px; color: #68d391; }
            .config-section { background: #2d3748; padding: 20px; border-radius: 5px; margin: 20px 0; border: 1px solid #4a5568; }
            pre { background: #1a202c; padding: 15px; border-radius: 5px; overflow: auto; font-size: 12px; border: 1px solid #4a5568; }
            .danger-zone { background: #742a2a; padding: 20px; border-radius: 5px; margin: 20px 0; border: 1px solid #c53030; }
            .danger-zone input { width: 100%; padding: 10px; margin: 5px 0; background: #2d3748; border: 1px solid #c53030; color: white; }
            .danger-zone button { background: #c53030; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
            .nav { margin: 20px 0; }
            .nav a { color: #805ad5; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔓 AuthLite Debug Vault</h1>
                <p>System administration and debugging interface</p>
            </div>

            <div class="nav">
                <a href="/">← Back to Home</a>
            </div>

            <?php if (!$debugEnabled || empty($masterKey)): ?>
                <div class="access-denied">
                    <h2>⚠️ Debug Mode Disabled</h2>
                    <p>Debug mode is not enabled or no master key is configured.</p>
                    <p>Enable debug mode in config.local.php to access this interface.</p>
                </div>
            <?php elseif (!$hasAccess): ?>
                <div class="access-denied">
                    <h2>🔒 Access Denied</h2>
                    <p>Enter master key to access debug vault:</p>
                    <form method="post" class="login-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="password" name="key" placeholder="Master Key" required>
                        <button type="submit" name="debug_access">Access</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="config-section">
                    <h2>📊 Database Statistics</h2>
                    <?php if (!empty($dbStats)): ?>
                        <div class="stats-grid">
                            <?php foreach ($dbStats as $key => $value): ?>
                                <?php if ($key !== 'size'): ?>
                                    <div class="stat">
                                        <div class="stat-value"><?php echo $value; ?></div>
                                        <div class="stat-label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (isset($dbStats['size'])): ?>
                                <div class="stat">
                                    <div class="stat-value"><?php echo number_format($dbStats['size'] / 1024, 2); ?> KB</div>
                                    <div class="stat-label">Database Size</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p>No database statistics available.</p>
                    <?php endif; ?>
                </div>

                <div class="config-section">
                    <h2>⚙️ Current Configuration</h2>
                    <pre><?php echo htmlspecialchars(json_encode($config, JSON_PRETTY_PRINT)); ?></pre>
                </div>

                <div class="danger-zone">
                    <h2>⚠️ Add User (Debug)</h2>
                    <p>Create a user directly without email confirmation:</p>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="text" name="username" placeholder="Username" required>
                        <input type="email" name="email" placeholder="Email" required>
                        <input type="password" name="password" placeholder="Password" required>
                        <button type="submit" name="add_user_debug">Add User</button>
                    </form>
                </div>

                <div style="text-align: center; margin-top: 30px; color: #718096;">
                    <p>Debug access granted at <?php echo date('Y-m-d H:i:s'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Handle OAuth callback
 */
function handleOAuthCallback(): void {
    $result = AuthLite::oauthCallback();
    if ($result) {
        $_SESSION['authlite_message'] = 'OAuth login successful!';
    } else {
        $_SESSION['authlite_error'] = 'OAuth login failed.';
    }
    header('Location: /');
    exit;
}

/**
 * Show 404 page
 */
function showNotFound(): void {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 Not Found - AuthLite</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; text-align: center; }
            .error-container { padding: 40px; }
            h1 { font-size: 5rem; color: #667eea; margin: 0; }
            h2 { color: #4a5568; margin: 20px 0; }
            a { color: #667eea; text-decoration: none; font-weight: 500; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>404</h1>
            <h2>Page Not Found</h2>
            <p>The page you are looking for does not exist.</p>
            <p><a href="/">← Back to Home</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
