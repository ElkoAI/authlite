# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-02-07

### Added
- **Core Authentication System**
  - Complete user registration with email/password
  - Secure login/logout functionality
  - Session-based authentication with secure cookie settings
  - JWT support for stateless authentication (optional)
  - "Remember me" functionality with secure tokens
  - Email confirmation system with expiry tokens
  - CSRF protection on all forms

- **Database Layer**
  - SQLite database with automatic table creation
  - Four main tables: `users`, `sessions`, `tokens`, `oauth_connections`
  - PDO-based database abstraction with prepared statements
  - Database statistics and monitoring

- **Security Features**
  - BCrypt password hashing with configurable cost
  - Password strength validation
  - Secure random token generation
  - Session ID regeneration
  - HTTP security headers
  - Input validation and sanitization

- **Social Login (OAuth)**
  - Google OAuth integration without external SDKs
  - Facebook OAuth integration without external SDKs
  - Apple Sign-In integration without external SDKs
  - Raw cURL implementation for OAuth endpoints
  - Secure OAuth connection storage

- **Utility Classes**
  - `AuthLiteDB`: Database operations and connection management
  - `AuthLitePassword`: Password hashing, verification, and strength validation
  - `AuthLiteToken`: Token generation, JWT creation and validation
  - `AuthLiteValidator`: Input validation, email validation, CSRF verification
  - `AuthLiteHttp`: HTTP utilities, client IP detection, redirect handling
  - `AuthLiteDebug`: Debug logging and error handling
  - `AuthLiteTime`: Date/time utilities for SQLite compatibility
  - `AuthLiteOAuth`: OAuth social login handling

- **Configuration System**
  - Hierarchical configuration with `config.php` as defaults
  - Local configuration override via `config.local.php`
  - Environment-based configuration support
  - Sensible security defaults

- **Debug Interface**
  - Secure debug vault accessible with master key
  - Database statistics viewing
  - Configuration inspection
  - Direct user creation for testing
  - System information display

- **Examples and Documentation**
  - `example.php`: Comprehensive example application with UI
  - `index.php`: Front controller with routing
  - `README.md`: Complete documentation and usage guide
  - `CHANGELOG.md`: This changelog file
  - `LICENSE`: MIT License
  - `PROJECT-DESIGN.md`: Original project design document

- **Development Tools**
  - `test2.php`: Comprehensive test suite
  - `start-server.sh`: Interactive development server script
  - `start-background.sh`: Background server manager
  - `test-server.sh`: Automated web server testing
  - `.gitignore`: Comprehensive Git ignore file

- **API Reference**
  - Main `AuthLite` class with static methods
  - Convenience global functions: `authlite_init()`, `authlite_user()`, etc.
  - Comprehensive error handling and logging
  - Database connection access for advanced usage

### Technical Specifications
- **PHP Version**: 7.4+ compatible
- **Dependencies**: None (pure PHP)
- **Storage**: SQLite (no external database required)
- **Extensions**: PDO, OpenSSL, cURL (for OAuth)
- **Security**: CSRF protection, secure sessions, password hashing
- **Architecture**: Modular, single responsibility classes

### Initial Setup
- Automatic database creation on first run
- Self-contained installation (copy files and configure)
- No Composer or package manager required
- Works with any PHP 7.4+ environment

---

## Versioning

Given a version number MAJOR.MINOR.PATCH:

- **MAJOR**: Incompatible API changes
- **MINOR**: New functionality in a backwards-compatible manner
- **PATCH**: Backwards-compatible bug fixes

## Release Notes Format

Each version section includes:

- `Added` for new features
- `Changed` for changes in existing functionality
- `Deprecated` for soon-to-be removed features
- `Removed` for removed features
- `Fixed` for any bug fixes
- `Security` for vulnerabilities

## Links

[Unreleased]: https://github.com/yourusername/authlite/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/yourusername/authlite/releases/tag/v1.0.0