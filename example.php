<?php
/**
 * AuthLite Example - Demonstration of AuthLite authentication library
 *
 * This example shows how to use AuthLite in a real PHP application.
 * It includes user registration, login, logout, CSRF protection,
 * and optional OAuth integration.
 */

// Start output buffering to prevent header issues
ob_start();

// Include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/oauth.php';
require_once __DIR__ . '/auth.php';

// Initialize AuthLite
$user = AuthLite::init();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !AuthLite::verifyCsrf($_POST['csrf_token'])) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        // Handle registration
        if (isset($_POST['register'])) {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $username = $_POST['username'] ?? '';

            if ($email && $password) {
                $result = AuthLite::register($email, $password, $username, false); // No email confirmation for demo
                if ($result) {
                    $message = 'Registration successful! You are now logged in.';
                } else {
                    $error = 'Registration failed. Email may already exist or password is too weak.';
                }
            } else {
                $error = 'Please fill in all required fields.';
            }
        }

        // Handle login
        elseif (isset($_POST['login'])) {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);

            if ($email && $password) {
                $result = AuthLite::login($email, $password, $remember);
                if ($result) {
                    $message = 'Login successful!';
                } else {
                    $error = 'Login failed. Invalid email or password.';
                }
            } else {
                $error = 'Please fill in all required fields.';
            }
        }

        // Handle logout
        elseif (isset($_POST['logout'])) {
            AuthLite::logout();
            $message = 'You have been logged out.';
        }

        // Handle OAuth login redirect
        elseif (isset($_POST['oauth_login'])) {
            $provider = $_POST['provider'] ?? '';
            $redirectUrl = AuthLite::oauthLogin($provider);
            if ($redirectUrl) {
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                $error = 'OAuth login is not configured for this provider.';
            }
        }
    }
}

// Handle OAuth callback
if (isset($_GET['code']) && isset($_GET['state'])) {
    $result = AuthLite::oauthCallback();
    if ($result) {
        $message = 'OAuth login successful!';
    } else {
        $error = 'OAuth login failed.';
    }
}

// Get current user and auth status
$currentUser = AuthLite::user();
$isLoggedIn = AuthLite::check();
$csrfToken = AuthLite::csrfToken();
$oauthProviders = AuthLite::oauthProviders();

// Get database stats for demo
$dbStats = [];
if (class_exists('AuthLiteDB')) {
    $dbStats = AuthLiteDB::getStats();
}

// Clear any previous output
ob_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AuthLite Example</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 20px;
        }

        h1, h2, h3 {
            color: #2d3748;
            margin-bottom: 20px;
        }

        h1 {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            font-size: 2.5rem;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h1 {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #4a5568;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox input {
            margin: 0;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .btn-danger {
            background: #f56565;
            color: white;
        }

        .btn-danger:hover {
            background: #e53e3e;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .user-info {
            background: #f7fafc;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .user-info h3 {
            margin-bottom: 10px;
            color: #667eea;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .stat-item {
            background: #f7fafc;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-top: 5px;
        }

        .oauth-providers {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .oauth-btn {
            flex: 1;
            min-width: 120px;
        }

        .oauth-btn.google {
            background: #4285f4;
            color: white;
        }

        .oauth-btn.facebook {
            background: #3b5998;
            color: white;
        }

        .oauth-btn.apple {
            background: #000;
            color: white;
        }

        .debug-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .debug-link a {
            color: #718096;
            text-decoration: none;
            font-size: 14px;
        }

        .debug-link a:hover {
            color: #667eea;
            text-decoration: underline;
        }

        .section {
            margin-bottom: 40px;
        }

        @media (max-width: 600px) {
            .container {
                padding: 10px;
            }

            .card {
                padding: 20px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>AuthLite</h1>
            <p style="color: white; text-align: center;">Lightweight PHP Authentication Library</p>
        </div>

        <div class="card">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="section">
                <h2>Authentication Status</h2>

                <?php if ($isLoggedIn && $currentUser): ?>
                    <div class="user-info">
                        <h3>Welcome, <?php echo htmlspecialchars($currentUser['email']); ?>!</h3>
                        <p>You are logged in with the following details:</p>
                        <ul>
                            <li>User ID: <?php echo $currentUser['id']; ?></li>
                            <li>Email: <?php echo htmlspecialchars($currentUser['email']); ?></li>
                            <li>Username: <?php echo htmlspecialchars($currentUser['username'] ?? 'Not set'); ?></li>
                            <li>Account Created: <?php echo date('F j, Y', strtotime($currentUser['created_at'])); ?></li>
                            <li>Email Confirmed: <?php echo $currentUser['is_confirmed'] ? 'Yes' : 'No'; ?></li>
                        </ul>

                        <form method="post" style="margin-top: 15px;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <button type="submit" name="logout" class="btn btn-danger">Logout</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="user-info">
                        <h3>Not Logged In</h3>
                        <p>You are not currently logged in. Please register or login below.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>Registration</h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <div class="form-group">
                        <label for="reg_email">Email Address *</label>
                        <input type="email" id="reg_email" name="email" required placeholder="you@example.com">
                    </div>

                    <div class="form-group">
                        <label for="reg_username">Username (Optional)</label>
                        <input type="text" id="reg_username" name="username" placeholder="Choose a username">
                    </div>

                    <div class="form-group">
                        <label for="reg_password">Password *</label>
                        <input type="password" id="reg_password" name="password" required placeholder="Minimum 8 characters with letters, numbers, and symbols">
                        <small style="color: #718096; font-size: 14px;">Password must be at least 8 characters with uppercase, lowercase, number, and special character.</small>
                    </div>

                    <div class="form-group">
                        <button type="submit" name="register" class="btn btn-primary">Register Account</button>
                    </div>
                </form>
            </div>

            <div class="section">
                <h2>Login</h2>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <div class="form-group">
                        <label for="login_email">Email Address</label>
                        <input type="email" id="login_email" name="email" required placeholder="you@example.com">
                    </div>

                    <div class="form-group">
                        <label for="login_password">Password</label>
                        <input type="password" id="login_password" name="password" required placeholder="Your password">
                    </div>

                    <div class="form-group">
                        <div class="checkbox">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember me (30 days)</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <button type="submit" name="login" class="btn btn-primary">Login</button>
                    </div>
                </form>

                <?php if (!empty($oauthProviders)): ?>
                <div class="form-group">
                    <h3>Social Login</h3>
                    <p>Login using your social media accounts:</p>

                    <div class="oauth-providers">
                        <?php foreach ($oauthProviders as $provider): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="provider" value="<?php echo htmlspecialchars($provider); ?>">
                                <button type="submit" name="oauth_login" class="btn oauth-btn <?php echo htmlspecialchars($provider); ?>">
                                    <?php echo ucfirst($provider); ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>Database Statistics</h2>
                <?php if (!empty($dbStats)): ?>
                    <div class="stats">
                        <?php foreach ($dbStats as $key => $value): ?>
                            <?php if ($key !== 'size'): ?>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $value; ?></div>
                                    <div class="stat-label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if (isset($dbStats['size'])): ?>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($dbStats['size'] / 1024, 2); ?> KB</div>
                                <div class="stat-label">Database Size</div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p>Database statistics not available.</p>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>CSRF Protection</h2>
                <p>AuthLite automatically generates and validates CSRF tokens to protect against cross-site request forgery attacks.</p>
                <p><strong>Current CSRF Token:</strong> <code style="background: #f7fafc; padding: 5px; border-radius: 3px;"><?php echo htmlspecialchars($csrfToken); ?></code></p>
                <p><small>This token is automatically included in all forms and validated on submission.</small></p>
            </div>

            <div class="debug-link">
                <?php
                $config = AuthLite::getConfig();
                if ($config['debug']['enabled'] ?? false && !empty($config['debug']['master_key'])):
                ?>
                    <a href="<?php echo htmlspecialchars($config['urls']['debug_vault'] ?? '/auth/vault'); ?>?key=<?php echo urlencode($config['debug']['master_key']); ?>" target="_blank">
                        Access Debug Vault
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="text-align: center; color: #718096; font-size: 14px;">
            <p>AuthLite Example &copy; <?php echo date('Y'); ?> |
                <a href="https://github.com/yourusername/authlite" style="color: #667eea;">GitHub Repository</a> |
                PHP <?php echo phpversion(); ?>
            </p>
        </div>
    </div>
</body>
</html>
<?php
// End output buffering and flush
ob_end_flush();
?>
