# Contributing to AuthLite

Thank you for your interest in contributing to AuthLite! This document provides guidelines and instructions for contributing to this project.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Code Standards](#code-standards)
- [Testing](#testing)
- [Submitting Changes](#submitting-changes)
- [Reporting Issues](#reporting-issues)
- [Requesting Features](#requesting-features)
- [License](#license)

## 👥 Code of Conduct

Please read and follow our [Code of Conduct](CODE_OF_CONDUCT.md) to foster an open and welcoming environment.

## 🚀 Getting Started

### Prerequisites
- PHP 7.4 or higher
- SQLite3 extension
- PDO extension
- OpenSSL extension (for token security)
- cURL extension (for OAuth functionality)

### Fork the Repository
1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/authlite.git
   cd authlite
   ```

## 🔧 Development Setup

### 1. Configure Environment
```bash
# Copy the default configuration
cp config.php config.local.php

# Edit configuration for development
# Set debug mode enabled and configure database path
```

### 2. Test the Setup
```bash
# Run the test suite
php test2.php

# Start the development server
./start-background.sh start 8080
```

### 3. Explore Examples
- `example.php` - Comprehensive example application
- `index.php` - Front controller with routing
- Visit `http://localhost:8080/` to see the application

## 📝 Code Standards

### PHP Code Style
- Follow PSR-12 coding standards
- Use type hints where possible (PHP 7.4+)
- Add PHPDoc comments for all classes and methods
- Keep methods focused and single-purpose

### Documentation
- Document all public APIs with PHPDoc
- Include examples in comments where helpful
- Update README.md for user-facing changes
- Update CHANGELOG.md for significant changes

### File Structure
```
authlite/
├── auth.php              # Main authentication class
├── config.php            # Default configuration
├── db.php                # Database operations
├── utils.php             # Utility functions
├── oauth.php             # OAuth social login
└── examples/             # Example implementations
```

### Naming Conventions
- Classes: `PascalCase` (e.g., `AuthLiteDB`)
- Methods: `camelCase` (e.g., `getUserById`)
- Variables: `snake_case` (e.g., `$user_data`)
- Constants: `UPPER_SNAKE_CASE` (e.g., `MAX_LOGIN_ATTEMPTS`)

## 🧪 Testing

### Running Tests
```bash
# Run the comprehensive test suite
php test2.php

# Test specific functionality
php -f test2.php -- --filter "registration"
```

### Writing Tests
- Test files should be named `test_*.php`
- Each test should be independent
- Clean up test data after each test
- Test both success and failure cases
- Include edge cases and error conditions

### Test Coverage
- User registration and validation
- Login and authentication
- Session management
- CSRF protection
- OAuth integration (when configured)
- Error handling

### Integration Testing
```bash
# Start test server
./test-server.sh

# Run automated web tests
./start-server.sh --test
```

## 📤 Submitting Changes

### 1. Create a Branch
```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/issue-description
```

### 2. Make Your Changes
- Write clear, focused commits
- Include tests for new functionality
- Update documentation as needed
- Follow the code standards

### 3. Commit Messages
Use conventional commit format:
```
type(scope): description

[optional body]

[optional footer]
```

Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

Example:
```
feat(auth): add password reset functionality

- Add password reset token generation
- Add email sending for reset links
- Add reset password endpoint

Closes #123
```

### 4. Push and Create Pull Request
```bash
git push origin your-branch-name
```

Then create a Pull Request on GitHub with:
- Clear title and description
- Reference to related issues
- Summary of changes
- Test results

### 5. PR Review Process
- All PRs require at least one review
- Address review comments promptly
- Keep PRs focused and manageable
- Update documentation if needed

## 🐛 Reporting Issues

### Before Reporting
1. Check if the issue already exists
2. Ensure you're using the latest version
3. Test with a clean configuration

### Issue Template
```
## Description
Clear and concise description of the issue.

## Steps to Reproduce
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

## Expected Behavior
What you expected to happen.

## Actual Behavior
What actually happened.

## Environment
- PHP Version: [e.g. 8.1.0]
- AuthLite Version: [e.g. 1.0.0]
- Server: [e.g. Apache, Nginx, Built-in PHP server]
- Database: [e.g. SQLite 3.34.0]

## Additional Context
Screenshots, error messages, configuration details.
```

## 💡 Requesting Features

### Feature Request Template
```
## Problem Statement
What problem would this feature solve?

## Proposed Solution
How should this feature work?

## Alternatives Considered
Other ways to solve the problem.

## Additional Context
Examples, use cases, or references.
```

## 📚 Documentation

### Updating Documentation
- Update `README.md` for user-facing changes
- Update `CHANGELOG.md` for releases
- Add inline documentation for code changes
- Create examples for new features

### Building Examples
- Keep examples simple and focused
- Include error handling
- Show best practices
- Test examples before committing

## 🛠️ Development Tools

### Available Scripts
```bash
# Start development server
./start-server.sh

# Start background server
./start-background.sh start

# Check server status
./start-background.sh status

# View server logs
./start-background.sh logs

# Clean test files
./start-background.sh clean
```

### Debugging
1. Enable debug mode in `config.local.php`
2. Access debug vault at `/auth/vault`
3. Check server logs with `./start-background.sh logs`
4. Use PHP error logging

## 📄 License

By contributing to AuthLite, you agree that your contributions will be licensed under the project's [MIT License](LICENSE).

## 🙏 Thank You!

Thank you for contributing to AuthLite! Your efforts help make this project better for everyone.
```

## Need Help?

- Read the [README.md](README.md) for usage instructions
- Check existing issues and discussions
- Ask questions in GitHub Discussions (if enabled)
- Contact maintainers for urgent issues

Happy coding! 🎉