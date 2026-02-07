<?php
/**
 * AuthLite Configuration
 *
 * Copy this file to config.local.php and customize for your environment.
 * The library will automatically load config.local.php if it exists.
 */

return [
    /**
     * Database Configuration
     */
    'database' => [
        'path' => __DIR__ . '/auth.db', // SQLite database path
        'create_tables' => true,        // Auto-create tables on first run
    ],

    /**
     * Application Security
     */
    'security' => [
        'app_secret' => 'change-this-to-a-random-secret-key', // For JWT signing and encryption
        'hash_algo' => 'sha256',        // Password hashing algorithm
        'cost' => 12,                   // Bcrypt cost factor
    ],

    /**
     * Session Configuration
     */
    'session' => [
        'name' => 'authlite_session',
        'lifetime' => 7200,             // Session lifetime in seconds (2 hours)
        'secure' => true,               // Only send cookies over HTTPS
        'httponly' => true,             // Prevent JavaScript access to cookies
        'samesite' => 'Lax',            // CSRF protection
    ],

    /**
     * JWT Configuration (for stateless authentication)
     */
    'jwt' => [
        'enabled' => false,             // Enable JWT-based stateless auth
        'issuer' => 'authlite',
        'audience' => 'authlite-client',
        'expiration' => 3600,           // Token expiration in seconds (1 hour)
        'leeway' => 60,                 // Clock skew leeway in seconds
    ],

    /**
     * Email Configuration (for confirmation emails)
     */
    'email' => [
        'from' => 'noreply@example.com',
        'from_name' => 'AuthLite System',
        'confirm_expiry' => 86400,      // Confirmation link expiry (24 hours)
        // SMTP settings would be added here if using SMTP
    ],

    /**
     * OAuth / Social Login Configuration
     */
    'oauth' => [
        'google' => [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => '',       // Will be auto-detected if empty
        ],
        'facebook' => [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => '',
        ],
        'apple' => [
            'enabled' => false,
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => '',
            'team_id' => '',
            'key_id' => '',
            'key_file' => '',           // Path to .p8 private key file
        ],
    ],

    /**
     * Debug & Development Settings
     */
    'debug' => [
        'enabled' => false,
        'master_key' => '',             // Master key for debug access to /auth/vault
        'log_errors' => true,
        'display_errors' => false,      // Never display errors in production!
    ],

    /**
     * URLs and Paths
     */
    'urls' => [
        'base' => '',                   // Base URL for your application
        'login' => '/login',
        'logout' => '/logout',
        'signup' => '/signup',
        'confirm' => '/confirm',        // Email confirmation endpoint
        'debug_vault' => '/auth/vault', // Debug interface (requires master key)
    ],
];

/**
 * Local configuration override
 *
 * Create a config.local.php file with your actual settings.
 * This file should NOT be committed to version control.
 */
if (file_exists(__DIR__ . '/config.local.php')) {
    $localConfig = require __DIR__ . '/config.local.php';
    $config = array_replace_recursive($config, $localConfig);
}

// Make configuration available as a constant
if (!defined('AUTHLITE_CONFIG')) {
    define('AUTHLITE_CONFIG', $config);
}
