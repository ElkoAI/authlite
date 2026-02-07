#!/bin/bash

# AuthLite Development Server Starter
# Starts a PHP development server for testing AuthLite authentication system

set -e

# Configuration
PORT=8888
HOST="localhost:$PORT"
BASE_URL="http://$HOST"
TEST_CONFIG="config.local.php"
DEFAULT_CONFIG="config.php"
DB_FILE="auth.db"
TEST_DB_FILE="test_auth.db"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print colored message
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Cleanup function
cleanup() {
    print_info "Cleaning up..."

    # Kill PHP server if running
    if [ ! -z "$SERVER_PID" ]; then
        print_info "Stopping PHP server (PID: $SERVER_PID)..."
        kill $SERVER_PID 2>/dev/null || true
        wait $SERVER_PID 2>/dev/null || true
    fi

    # Remove test config if we created it
    if [ "$CREATED_TEST_CONFIG" = "true" ] && [ -f "$TEST_CONFIG" ]; then
        print_info "Removing test configuration..."
        rm -f "$TEST_CONFIG"
    fi

    # Remove test database if we created it
    if [ "$CREATED_TEST_DB" = "true" ] && [ -f "$TEST_DB_FILE" ]; then
        print_info "Removing test database..."
        rm -f "$TEST_DB_FILE"
    fi
}

# Trap signals for cleanup
trap cleanup EXIT INT TERM

# Check if PHP is available
check_php() {
    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed or not in PATH"
        print_info "Please install PHP 7.4 or higher to run AuthLite"
        exit 1
    fi

    # Check PHP version
    PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
    if [ $(echo "$PHP_VERSION < 7.4" | bc) -eq 1 ]; then
        print_warning "PHP version $PHP_VERSION detected. AuthLite requires PHP 7.4 or higher."
        print_info "Some features may not work correctly."
    else
        print_info "PHP version $PHP_VERSION detected (OK)"
    fi
}

# Check for required files
check_required_files() {
    local missing_files=()

    for file in "config.php" "auth.php" "db.php" "utils.php" "oauth.php"; do
        if [ ! -f "$file" ]; then
            missing_files+=("$file")
        fi
    done

    if [ ${#missing_files[@]} -gt 0 ]; then
        print_error "Missing required files: ${missing_files[*]}"
        print_info "Make sure you're in the AuthLite directory and all files are present"
        exit 1
    fi

    print_info "All required files found"
}

# Create test configuration
create_test_config() {
    if [ -f "$TEST_CONFIG" ]; then
        print_info "Test configuration already exists: $TEST_CONFIG"
        return 0
    fi

    print_info "Creating test configuration..."

    cat > "$TEST_CONFIG" << 'EOF'
<?php
/**
 * AuthLite Test Configuration
 *
 * This configuration is automatically generated for testing purposes.
 * For production use, create your own config.local.php with secure values.
 */
return [
    'database' => [
        'path' => __DIR__ . '/test_auth.db',
        'create_tables' => true,
    ],
    'security' => [
        'app_secret' => 'test-secret-key-change-this-in-production',
        'hash_algo' => 'sha256',
        'cost' => 10,
    ],
    'session' => [
        'name' => 'authlite_test_session',
        'lifetime' => 7200,
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    'jwt' => [
        'enabled' => false,
        'issuer' => 'authlite-test',
        'audience' => 'authlite-test-client',
        'expiration' => 3600,
        'leeway' => 60,
    ],
    'email' => [
        'from' => 'noreply@example.com',
        'from_name' => 'AuthLite Test',
        'confirm_expiry' => 86400,
    ],
    'oauth' => [
        'google' => ['enabled' => false],
        'facebook' => ['enabled' => false],
        'apple' => ['enabled' => false],
    ],
    'debug' => [
        'enabled' => true,
        'master_key' => 'test-master-key-12345',
        'log_errors' => true,
        'display_errors' => true, // Only for testing!
    ],
    'urls' => [
        'base' => 'http://localhost:8888',
        'login' => '/login',
        'logout' => '/logout',
        'signup' => '/signup',
        'confirm' => '/confirm',
        'debug_vault' => '/auth/vault',
    ],
];
EOF

    CREATED_TEST_CONFIG="true"
    print_success "Test configuration created: $TEST_CONFIG"
}

# Check if test database exists
check_test_database() {
    if [ ! -f "$TEST_DB_FILE" ]; then
        CREATED_TEST_DB="true"
        print_info "Test database will be created on first run: $TEST_DB_FILE"
    else
        print_info "Test database already exists: $TEST_DB_FILE"
    fi
}

# Start PHP development server
start_server() {
    print_info "Starting PHP development server on $BASE_URL"
    print_info "Press Ctrl+C to stop the server"
    echo ""

    # Display access information
    echo "================================================"
    echo "         AuthLite Development Server"
    echo "================================================"
    echo ""
    echo "🌐 Access URLs:"
    echo "   Home Page:        $BASE_URL/"
    echo "   Login:            $BASE_URL/login"
    echo "   Sign Up:          $BASE_URL/signup"
    echo "   Debug Vault:      $BASE_URL/auth/vault?key=test-master-key-12345"
    echo "   Example App:      $BASE_URL/example.php"
    echo ""
    echo "🔐 Debug Vault Key: test-master-key-12345"
    echo ""
    echo "📝 Note: This is a development server only."
    echo "   Do not use for production."
    echo "================================================"
    echo ""

    # Check if index.php exists, if not, use example.php as default
    if [ -f "index.php" ]; then
        ENTRY_POINT="index.php"
    elif [ -f "example.php" ]; then
        ENTRY_POINT="example.php"
        print_warning "index.php not found, using example.php as entry point"
    else
        print_error "No entry point found (index.php or example.php)"
        print_info "Starting server without router script"
        ENTRY_POINT=""
    fi

    # Start the server
    if [ -z "$ENTRY_POINT" ]; then
        php -S "$HOST" &
    else
        php -S "$HOST" "$ENTRY_POINT" &
    fi

    SERVER_PID=$!

    print_info "Server started with PID: $SERVER_PID"
    print_info "Logs will appear below..."
    echo ""

    # Wait for server and show logs
    wait $SERVER_PID
}

# Run basic test
run_basic_test() {
    print_info "Running basic system test..."

    if php -l config.php 2>&1 | grep -q "No syntax errors"; then
        print_success "config.php syntax OK"
    else
        print_error "config.php has syntax errors"
        php -l config.php
    fi

    if php -l auth.php 2>&1 | grep -q "No syntax errors"; then
        print_success "auth.php syntax OK"
    else
        print_error "auth.php has syntax errors"
        php -l auth.php
    fi

    echo ""
}

# Main function
main() {
    echo ""
    print_success "🚀 Starting AuthLite Development Server"
    echo ""

    # Initialize variables
    CREATED_TEST_CONFIG="false"
    CREATED_TEST_DB="false"
    SERVER_PID=""

    # Run checks and setup
    check_php
    check_required_files
    run_basic_test
    create_test_config
    check_test_database

    # Start server
    start_server
}

# Show help
show_help() {
    echo "AuthLite Development Server Starter"
    echo ""
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  -h, --help     Show this help message"
    echo "  -p, --port     Port to use (default: 8888)"
    echo "  --clean        Clean up test files and exit"
    echo ""
    echo "Examples:"
    echo "  $0              # Start server on port 8888"
    echo "  $0 -p 8080      # Start server on port 8080"
    echo "  $0 --clean      # Clean test files"
}

# Handle command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            exit 0
            ;;
        -p|--port)
            PORT="$2"
            HOST="localhost:$PORT"
            BASE_URL="http://$HOST"
            shift 2
            ;;
        --clean)
            print_info "Cleaning up test files..."
            rm -f "$TEST_CONFIG" "$TEST_DB_FILE" "auth.db" 2>/dev/null || true
            print_success "Test files cleaned up"
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Run main function
main "$@"
