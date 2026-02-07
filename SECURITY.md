# Security Policy

## 🔒 Overview

AuthLite is designed with security as a primary consideration. This document outlines our security policies, procedures for reporting vulnerabilities, and best practices for securing your AuthLite implementation.

## 🚨 Reporting a Vulnerability

**Please do NOT report security vulnerabilities through public GitHub issues.**

If you believe you have found a security vulnerability in AuthLite, please report it privately to:

**security@yourdomain.com** (replace with actual security contact)

### What to Include
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)
- Affected versions

### Response Process
1. **Acknowledgement**: We will acknowledge receipt of your report within 48 hours
2. **Investigation**: Our security team will investigate and validate the report
3. **Fix Development**: If confirmed, we will develop a fix
4. **Disclosure**: We will coordinate disclosure with the reporter
5. **Release**: We will release a security update

### Timeline
- Critical vulnerabilities: Patch within 7 days
- High severity: Patch within 14 days
- Medium severity: Patch within 30 days
- Low severity: Patch in next regular release

## 🛡️ Security Best Practices

### Configuration Security

1. **App Secret**
   ```php
   // config.local.php
   'security' => [
       'app_secret' => bin2hex(random_bytes(32)), // Generate strong random secret
   ],
   ```
   - Generate a cryptographically secure random string
   - Minimum 32 characters
   - Never use default or weak secrets

2. **Session Security**
   ```php
   'session' => [
       'secure' => true,    // REQUIRED for production (HTTPS only)
       'httponly' => true,  // Prevent JavaScript access
       'samesite' => 'Lax', // CSRF protection
   ],
   ```
   - Always use HTTPS in production
   - Set `secure` to `true` when using HTTPS

3. **Password Security**
   ```php
   'security' => [
       'cost' => 12, // Bcrypt cost factor
   ],
   ```
   - Use cost factor 12 or higher for production
   - AuthLite automatically validates password strength

### Deployment Security

1. **File Permissions**
   ```
   # Database file permissions
   chmod 640 auth.db
   chown www-data:www-data auth.db
   
   # Configuration files
   chmod 640 config.local.php
   ```

2. **Environment Configuration**
   - Never commit `config.local.php` to version control
   - Store sensitive data in environment variables
   - Use `.gitignore` to exclude sensitive files

3. **Debug Mode**
   ```php
   'debug' => [
       'enabled' => false, // DISABLE in production
   ],
   ```
   - **Always disable debug mode in production**
   - Remove or secure debug vault access

### Code Security

1. **Input Validation**
   - AuthLite automatically sanitizes and validates inputs
   - Always use `AuthLiteValidator` for custom validation

2. **CSRF Protection**
   ```php
   // All forms must include CSRF token
   <input type="hidden" name="csrf_token" value="<?php echo AuthLite::csrfToken(); ?>">
   ```
   - AuthLite validates CSRF tokens automatically
   - Never disable CSRF protection

3. **SQL Injection Prevention**
   - AuthLite uses PDO prepared statements
   - Never use raw SQL queries with user input

## ⚠️ Known Security Considerations

### 1. OAuth Implementation
- AuthLite uses raw cURL for OAuth (no external SDKs)
- Ensure OAuth redirect URIs are properly configured
- Validate OAuth state tokens to prevent CSRF

### 2. Email Confirmation
- Confirmation tokens expire after 24 hours by default
- Tokens are single-use and invalidated after confirmation
- Consider rate limiting confirmation attempts

### 3. Session Management
- Session IDs regenerate every 5 minutes
- Sessions expire after 2 hours by default
- "Remember me" tokens expire after 30 days

### 4. JWT Tokens (if enabled)
- Tokens expire after 1 hour by default
- Use HTTPS when transmitting JWT tokens
- Store tokens securely on client side

## 🔄 Update Policy

### Security Updates
- Critical security updates: Immediate release
- Regular security patches: Monthly
- End-of-life versions: 6 months notice

### Supported Versions
| Version | Security Support | End of Life |
|---------|-----------------|-------------|
| 1.x.x   | ✅ Full support  | TBD         |
| 0.x.x   | ❌ None         | Immediate   |

### Update Checklist
1. **Backup** your database before updating
2. **Test** in staging environment
3. **Review** changelog for breaking changes
4. **Update** configuration if needed
5. **Verify** functionality after update

## 🧪 Security Testing

### Recommended Tools
- **Static Analysis**: PHPStan, Psalm
- **Dynamic Analysis**: OWASP ZAP, Burp Suite
- **Dependency Scanning**: Composer security check

### Testing Checklist
- [ ] Password strength validation
- [ ] CSRF token validation
- [ ] Session fixation prevention
- [ ] SQL injection prevention
- [ ] XSS protection
- [ ] Rate limiting (if implemented)
- [ ] HTTPS enforcement

## 📚 Security Resources

### OWASP Resources
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP Cheat Sheets](https://cheatsheetseries.owasp.org/)
- [Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)

### PHP Security
- [PHP Security Guide](https://www.php.net/manual/en/security.php)
- [PHP The Right Way - Security](https://phptherightway.com/#security)

## 🤝 Acknowledgments

We thank the security researchers and community members who help improve AuthLite's security.

### Responsible Disclosure Hall of Fame
- (Names will be added with permission)

## 📞 Contact

### Security Issues
**security@yourdomain.com** (replace with actual contact)

### General Questions
GitHub Issues: https://github.com/yourusername/authlite/issues

### Maintainers
- Your Name <you@example.com>

---

**Last Updated**: February 2025  
**Policy Version**: 1.0

---

*This security policy is adapted from best practices and may be updated as needed. Always refer to the latest version in the repository.*