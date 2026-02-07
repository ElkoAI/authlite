# AuthLite 🔐

A lightweight, dependency-free PHP authentication library that runs on pure PHP 7.4+. Requires only SQLite for storage, with support for sessions, JWT tokens, and OAuth social logins without external SDKs.

## ✨ Features

- **No Dependencies**: Pure PHP 7.4+, only SQLite for storage
- **Simple Setup**: One-line initialization with `AuthLite::init()`
- **Flexible Authentication**: Session-based or stateless JWT tokens
- **Social Login**: Google, Facebook, Apple OAuth with raw cURL (no SDKs)
- **Security First**: CSRF protection, secure sessions, password hashing
- **Email Confirmation**: Built-in email verification with expiry
- "Remember Me" functionality
- **Debug Interface**: Admin vault for development and testing
- **Modular Design**: Five core files, easy to extend

## 📋 Requirements

- PHP 7.4 or higher
- SQLite3 (usually bundled with PHP)
- PDO extension
- OpenSSL extension (for secure tokens)
- cURL extension (for OAuth)

## 🚀 Installation

### 1. Download or Clone
```bash
git clone https://github.com/yourusername/authlite.git
cd authlite
```

### 2. Configure
Copy the example configuration and customize:
```bash
cp config.php config.local.php
nano config.local.php
```

### 3. Include in Your Project
```php
require_once '/path/to/authlite/config.php';
require_once '/path/to/authlite/auth.php';
// Other files are auto-loaded as needed
```

## ⚡ Quick Start

### Basic Usage
```php
<?php
// Initialize AuthLite early in your application
require_once 'authlite/auth.php';

$user = AuthLite::init();

if ($user) {
    echo "Welcome, " . htmlspecialchars($user['email']) . "!";
} else {
    echo "Please <a href='/login'>login</a>";
}
?>
```

### User Registration
```php
// Register a new user
$result = AuthLite::register('user@example.com', 'SecurePass123!', 'username');
if ($result) {
    echo "Registration successful!";
}
```

### User Login
```php
// Login with credentials
$user = AuthLite::login('user@example.com', 'SecurePass123!', true); // true for "remember me"
if ($user) {
    echo "Login successful!";
}
```

### Check Authentication Status
```php
if (AuthLite::check()) {
    $user = AuthLite::user();
    // User is logged in
} else {
    // User is not logged in
}
```

### Logout
```php
AuthLite::logout();
```

## ⚙️ Configuration

### Configuration Files
- `config.php` - Default configuration (safe to modify)
- `config.local.php` - Local overrides (not in version control)

### Key Configuration Options

```php
// Example config.local.php
return [
    'database' => [
        'path' => __DIR__ . '/auth.db', // SQLite database path
        'create_tables' => true,        // Auto-create tables
    ],
    
    'security' => [
        'app_secret' => 'change-this-to-a-random-secret-key',
        'hash_algo' => 'sha256',
        'cost' => 12, // Bcrypt cost factor
    ],
    
    'session' => [
        'name' => 'authlite_session',
        'lifetime' => 7200, // 2 hours
        'secure' => true,   // HTTPS only
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    
    'jwt' => [
        'enabled' => false, // Enable for stateless auth
        'expiration' => 3600,
    ],
    
    'oauth' => [
        'google' => [
            'enabled' => true,
            'client_id' => 'YOUR_CLIENT_ID',
            'client_secret' => 'YOUR_CLIENT_SECRET',
        ],
        // ... Facebook, Apple configurations
    ],
    
    'debug' => [
        'enabled' => false, // Only for development!
        'master_key' => 'your-secret-debug-key',
    ],
];
```

## 📖 API Reference

### Core Methods

#### `AuthLite::init(?array $config = null): array|false`
Initialize the authentication system. Should be called early in your application.
- Returns: Current user array or `false` if not logged in

#### `AuthLite::register(string $email, string $password, string $username = '', bool $sendConfirmation = true): array|false`
Register a new user.
- Returns: User data on success, `false` on failure

#### `AuthLite::login(string $email, string $password, bool $remember = false): array|false`
Authenticate a user.
- `$remember`: Set to `true` for "remember me" functionality

#### `AuthLite::logout(): bool`
Log out the current user.

#### `AuthLite::user(): array|false`
Get current authenticated user data.

#### `AuthLite::check(): bool`
Check if a user is logged in.

#### `AuthLite::csrfToken(): string`
Get current CSRF token for form protection.

#### `AuthLite::verifyCsrf(string $token): bool`
Verify a CSRF token.

#### `AuthLite::confirmEmail(string $token): bool`
Confirm user email with token.

#### `AuthLite::oauthLogin(string $provider): string|false`
Initiate OAuth login.
- Returns: Redirect URL or `false`

#### `AuthLite::oauthCallback(): array|false`
Handle OAuth callback.
- Returns: User data or `false`

#### `AuthLite::oauthProviders(): array`
Get list of enabled OAuth providers.

#### `AuthLite::getDB(): PDO`
Get database connection (advanced usage).

#### `AuthLite::getConfig(): array`
Get current configuration.

### Helper Functions

```php
authlite_init(?array $config = null)        // Alias for AuthLite::init()
authlite_user()                             // Alias for AuthLite::user()
authlite_check()                            // Alias for AuthLite::check()
authlite_csrf_token()                       // Alias for AuthLite::csrfToken()
```

### Utility Classes

- **`AuthLiteDB`**: Database operations
- **`AuthLitePassword`**: Password hashing and validation
- **`AuthLiteToken`**: Token generation and JWT handling
- **`AuthLiteValidator`**: Input validation and sanitization
- **`AuthLiteHttp`**: HTTP utilities
- **`AuthLiteDebug`**: Debug and logging
- **`AuthLiteTime`**: Date/time utilities
- **`AuthLiteOAuth`**: OAuth social login handling

## 🔐 OAuth / Social Login

### Supported Providers
- Google
- Facebook
- Apple

### Configuration Example
```php
'oauth' => [
    'google' => [
        'enabled' => true,
        'client_id' => 'YOUR_GOOGLE_CLIENT_ID',
        'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
        'redirect_uri' => 'https://yourdomain.com/oauth/callback',
    ],
],
```

### Implementation
AuthLite uses raw cURL requests to OAuth endpoints - no external SDKs or libraries required.

## 🛠️ Debug Vault

The debug vault provides a secure administrative interface for development:

### Access
1. Enable debug mode in configuration:
```php
'debug' => [
    'enabled' => true,
    'master_key' => 'your-secret-key',
],
```

2. Access via: `/auth/vault?key=your-secret-key`

### Features
- Database statistics and monitoring
- Configuration viewing
- Direct user creation (bypasses email confirmation)
- System information

⚠️ **Warning**: Only enable debug mode in development environments!

## 📊 Database Schema

AuthLite creates four main tables:

### `users`
- `id` (INTEGER, PRIMARY KEY)
- `email` (TEXT, UNIQUE)
- `username` (TEXT)
- `password_hash` (TEXT)
- `is_confirmed` (BOOLEAN)
- `confirmation_token` (TEXT)
- `confirmation_expires` (DATETIME)
- `created_at`, `updated_at`, `last_login` (DATETIME)
- `is_active` (BOOLEAN)

### `sessions`
- Session storage with expiry

### `tokens`
- JWT, refresh, confirmation, and password reset tokens

### `oauth_connections`
- Social login connections

## 🧪 Examples

### Example 1: Complete Login System
See `example.php` for a full-featured authentication example with:
- User registration
- Login/logout
- CSRF protection
- Social login buttons
- Database statistics

### Example 2: API with JWT
```php
// Enable JWT in config
'jwt' => ['enabled' => true],

// In your API endpoint
$user = AuthLite::init();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Proceed with authenticated request
```

### Example 3: Front Controller
See `index.php` for a complete front controller with:
- Routing (login, logout, signup, confirmation, debug vault)
- Form handling
- Session management
- Error handling

## 🧪 Testing

### Run Test Suite
```bash
# Run basic functionality tests
php test2.php

# Start development server
chmod +x start-background.sh
./start-background.sh start 8080

# Check server status
./start-background.sh status

# Stop server
./start-background.sh stop
```

### Test Server Script
```bash
# Interactive server with test configuration
chmod +x start-server.sh
./start-server.sh

# Specify port
./start-server.sh -p 8080
```

## 🔒 Security Considerations

### Production Checklist
1. [ ] Change `app_secret` to a strong random value
2. [ ] Set `session.secure` to `true` (requires HTTPS)
3. [ ] Disable debug mode: `'debug' => ['enabled' => false]`
4. [ ] Set proper file permissions for database
5. [ ] Use HTTPS in production
6. [ ] Configure email settings for confirmation emails
7. [ ] Regular database backups
8. [ ] Keep PHP and extensions updated

### Password Security
- Uses bcrypt with configurable cost factor
- Automatic password rehashing when needed
- Password strength validation
- Secure random token generation

### Session Security
- Secure cookie flags (HttpOnly, Secure, SameSite)
- Session ID regeneration
- CSRF protection on all forms
- IP and user agent binding (optional)

## 📁 Project Structure

```
authlite/
├── auth.php              # Main authentication class
├── config.php            # Default configuration
├── config.local.php      # Local configuration (ignored by git)
├── db.php                # Database operations
├── utils.php             # Utility functions
├── oauth.php             # OAuth social login
├── index.php             # Front controller example
├── example.php           # Comprehensive example
├── test2.php             # Test suite
├── start-server.sh       # Development server script
├── start-background.sh   # Background server manager
├── PROJECT-DESIGN.md     # Original design document
├── README.md             # This file
└── auth.db               # SQLite database (auto-created)
```

## 🚨 Troubleshooting

### Common Issues

#### "Session cannot be started after headers"
Make sure `AuthLite::init()` is called before any output.

#### Database Permission Errors
Ensure the web server has write permissions to the database directory.

#### OAuth Not Working
- Verify client ID and secret
- Check redirect URI configuration
- Ensure cURL extension is enabled

#### Email Confirmation Not Sending
Configure email settings in configuration, or disable confirmation:
```php
AuthLite::register($email, $password, $username, false);
```

### Debugging
1. Enable debug mode in configuration
2. Check the debug vault for system information
3. View server logs: `./start-background.sh logs`
4. Check PHP error log

## 📄 License

MIT License - see LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

### Development Setup
```bash
git clone https://github.com/yourusername/authlite.git
cd authlite
# Make your changes
php test2.php  # Run tests
```

## 📞 Support

- **Issues**: GitHub Issues
- **Documentation**: This README and code comments
- **Examples**: `example.php` and `index.php`

## 🏗️ Architecture Notes

### Design Principles
1. **Minimal Dependencies**: No external libraries required
2. **Simple API**: Easy to learn and use
3. **Secure by Default**: Safe defaults for security
4. **Modular**: Use only what you need
5. **Extensible**: Easy to customize and extend

### File Responsibilities
- `auth.php`: Core authentication logic
- `db.php`: Database abstraction
- `utils.php`: Helper functions
- `oauth.php`: Social login integration
- `config.php`: Configuration management

---

**AuthLite** - Lightweight authentication for PHP applications. Secure, simple, and dependency-free.