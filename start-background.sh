#!/bin/bash

# AuthLite Background Server Manager
# Manages a PHP development server for AuthLite authentication system

set -e

# Configuration
DEFAULT_PORT=8888
HOST="localhost"
PID_FILE=".authlite-server.pid"
LOG_FILE=".authlite-server.log"
CONFIG_FILE="config.local.php"
TEST_DB="test_auth.db"
DEFAULT_DB="auth.db"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
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

print_debug() {
    echo -e "${PURPLE}[DEBUG]${NC} $1"
}

print_status() {
    echo -e "${CYAN}[STATUS]${NC} $1"
}

# Show help
show_help() {
    echo "AuthLite Background Server Manager"
    echo ""
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Commands:"
    echo "  start [port]    Start server (default port: $DEFAULT_PORT)"
    echo "  stop            Stop running server"
    echo "  restart [port]  Restart server"
    echo "  status          Check server status"
    echo "  logs            Show server logs"
    echo "  clean           Clean up test files and stop server"
    echo "  help            Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 start        # Start server on port $DEFAULT_PORT"
    echo "  $0 start 8080   # Start server on port 8080"
    echo "  $0 stop         # Stop running server"
    echo "  $0 status       # Check if server is running"
    echo "  $0 logs         # Show server logs"
    echo ""
    echo "Background server runs with test configuration."
    echo "Access URLs will be displayed when starting."
}

# Check if PHP is available
check_php() {
    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed or not in PATH"
        print_info "Please install PHP 7.4 or higher to run AuthLite"
        exit 1
    fi
    print_debug "PHP version: $(php -v | head -n1)"
}

# Check for required files
check_required_files() {
    local missing_files=()
    local required_files=("config.php" "auth.php" "db.php" "utils.php" "oauth.php")

    for file in "${required_files[@]}"; do
        if [ ! -f "$file" ]; then
            missing_files+=("$file")
        fi
    done

    if [ ${#missing_files[@]} -gt 0 ]; then
        print_error "Missing required files: ${missing_files[*]}"
        print_info "Make sure you're in the AuthLite directory"
        exit 1
    fi
}

# Create test configuration
create_test_config() {
    if [ -f "$CONFIG_FILE" ]; then
        print_debug "Test configuration already exists: $CONFIG_FILE"
        return 0
    fi

    print_info "Creating test configuration..."

    cat > "$CONFIG_FILE" << 'EOF'
<?php
/**
 * AuthLite Test Configuration (Auto-generated)
 * For testing only - create your own config.local.php for production
 */
return [
    'database' => [
        'path' => __DIR__ . '/test_auth.db',
        'create_tables' => true,
    ],
    'security' => [
        'app_secret' => 'test-secret-key-for-authlite-background-server',
        'hash_algo' => 'sha256',
        'cost' => 10,
    ],
    'session' => [
        'name' => 'authlite_bg_server',
        'lifetime' => 7200,
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    'jwt' => [
        'enabled' => false,
        'issuer' => 'authlite-bg-test',
        'audience' => 'authlite-bg-client',
        'expiration' => 3600,
        'leeway' => 60,
    ],
    'email' => [
        'from' => 'noreply@example.com',
        'from_name' => 'AuthLite Background Test',
        'confirm_expiry' => 86400,
    ],
    'oauth' => [
        'google' => ['enabled' => false],
        'facebook' => ['enabled' => false],
        'apple' => ['enabled' => false],
    ],
    'debug' => [
        'enabled' => true,
        'master_key' => 'test-master-key-bg-12345',
        'log_errors' => true,
        'display_errors' => true,
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

    print_success "Test configuration created: $CONFIG_FILE"
}

# Get server PID
get_pid() {
    if [ -f "$PID_FILE" ]; then
        cat "$PID_FILE" 2>/dev/null
    else
        echo ""
    fi
}

# Check if server is running
is_server_running() {
    local pid=$(get_pid)
    if [ -z "$pid" ]; then
        return 1
    fi

    if kill -0 "$pid" 2>/dev/null; then
        return 0
    else
        # Clean up stale PID file
        rm -f "$PID_FILE"
        return 1
    fi
}

# Get server port from PID file or command
get_server_port() {
    if [ -f "$PID_FILE" ]; then
        # Try to extract port from PID file (last line)
        tail -1 "$PID_FILE" | grep -o '[0-9]*$' || echo "$DEFAULT_PORT"
    else
        echo "$DEFAULT_PORT"
    fi
}

# Start server
start_server() {
    local port=${1:-$DEFAULT_PORT}
    local url="http://$HOST:$port"

    print_info "Starting AuthLite server on port $port..."

    # Check if already running
    if is_server_running; then
        local running_port=$(get_server_port)
        print_warning "Server is already running on port $running_port"
        print_info "Use '$0 stop' to stop it first, or '$0 restart $port' to restart"
        return 1
    fi

    # Check PHP and files
    check_php
    check_required_files
    create_test_config

    # Determine entry point
    local entry_point=""
    if [ -f "index.php" ]; then
        entry_point="index.php"
    elif [ -f "example.php" ]; then
        entry_point="example.php"
        print_warning "index.php not found, using example.php"
    else
        print_error "No entry point found (index.php or example.php)"
        return 1
    fi

    # Start server in background
    print_debug "Starting: php -S $HOST:$port $entry_point"
    php -S "$HOST:$port" "$entry_point" > "$LOG_FILE" 2>&1 &
    local server_pid=$!

    # Save PID and port to file
    echo "$server_pid" > "$PID_FILE"
    echo "$port" >> "$PID_FILE"

    # Wait a moment to see if server starts successfully
    sleep 2

    # Check if server is still running
    if kill -0 "$server_pid" 2>/dev/null; then
        print_success "Server started with PID: $server_pid"
        print_success "Log file: $LOG_FILE"
        print_success "PID file: $PID_FILE"

        # Display access URLs
        echo ""
        echo "================================================"
        echo "     AuthLite Background Server Started"
        echo "================================================"
        echo "🌐 Access URLs:"
        echo "   Home Page:        $url/"
        echo "   Login:            $url/login"
        echo "   Sign Up:          $url/signup"
        echo "   Debug Vault:      $url/auth/vault?key=test-master-key-bg-12345"
        echo "   Example App:      $url/example.php"
        echo ""
        echo "🔐 Debug Vault Key: test-master-key-bg-12345"
        echo "📁 Log File: $LOG_FILE"
        echo "🆔 Process ID: $server_pid"
        echo ""
        echo "💡 Use '$0 status' to check server status"
        echo "💡 Use '$0 stop' to stop the server"
        echo "================================================"
        echo ""

        return 0
    else
        print_error "Server failed to start"
        print_info "Check log file: $LOG_FILE"
        rm -f "$PID_FILE"
        return 1
    fi
}

# Stop server
stop_server() {
    print_info "Stopping AuthLite server..."

    if ! is_server_running; then
        print_warning "Server is not running"
        return 0
    fi

    local pid=$(get_pid)
    if [ -n "$pid" ]; then
        print_debug "Stopping process $pid..."
        kill "$pid" 2>/dev/null

        # Wait for process to terminate
        local max_wait=10
        local waited=0
        while kill -0 "$pid" 2>/dev/null && [ $waited -lt $max_wait ]; do
            sleep 1
            ((waited++))
            print_debug "Waiting for process to stop... ($waited/$max_wait)"
        done

        # Force kill if still running
        if kill -0 "$pid" 2>/dev/null; then
            print_warning "Process did not stop gracefully, forcing kill..."
            kill -9 "$pid" 2>/dev/null
            sleep 1
        fi

        # Clean up PID file
        rm -f "$PID_FILE"

        if is_server_running; then
            print_error "Failed to stop server"
            return 1
        else
            print_success "Server stopped"
            return 0
        fi
    fi

    print_warning "No PID file found"
    return 0
}

# Check server status
check_status() {
    print_status "AuthLite Server Status"
    echo ""

    if is_server_running; then
        local pid=$(get_pid)
        local port=$(get_server_port)
        local url="http://$HOST:$port"

        print_success "✅ Server is RUNNING"
        echo "   PID:          $pid"
        echo "   Port:         $port"
        echo "   URL:          $url"
        echo "   Log File:     $LOG_FILE"
        echo "   PID File:     $PID_FILE"
        echo ""
        echo "   Access URLs:"
        echo "     • $url/"
        echo "     • $url/login"
        echo "     • $url/signup"
        echo "     • $url/auth/vault?key=test-master-key-bg-12345"
        echo ""

        # Try to check if server is responding
        print_debug "Checking if server is responding..."
        if curl -s -o /dev/null -w "%{http_code}" "$url/" 2>/dev/null | grep -q "200\|404\|500"; then
            print_success "   Server is responding to requests"
        else
            print_warning "   Server may not be responding (check logs)"
        fi

        return 0
    else
        print_error "❌ Server is NOT RUNNING"
        echo ""
        echo "   To start server: $0 start [port]"
        echo "   Default port: $DEFAULT_PORT"
        return 1
    fi
}

# Show server logs
show_logs() {
    print_info "Showing server logs from: $LOG_FILE"
    echo ""

    if [ ! -f "$LOG_FILE" ]; then
        print_warning "Log file does not exist"
        print_info "Start the server first to generate logs"
        return 1
    fi

    if [ ! -s "$LOG_FILE" ]; then
        print_warning "Log file is empty"
        print_info "The server may not have generated any output yet"
        return 0
    fi

    # Show last 50 lines of logs
    echo "=== Last 50 lines of server logs ==="
    tail -50 "$LOG_FILE"
    echo ""
    echo "=== End of logs ==="
    echo ""
    print_info "Full log file: $LOG_FILE"
    print_info "Use 'tail -f $LOG_FILE' to follow logs in real-time"
}

# Restart server
restart_server() {
    local port=${1:-$DEFAULT_PORT}

    print_info "Restarting AuthLite server..."

    # Stop if running
    if is_server_running; then
        stop_server
        sleep 1
    fi

    # Start with new port
    start_server "$port"
}

# Clean up files
cleanup_files() {
    print_info "Cleaning up AuthLite files..."

    # Stop server first
    if is_server_running; then
        stop_server
    fi

    # Remove control files
    rm -f "$PID_FILE" "$LOG_FILE" 2>/dev/null || true

    # Remove test configuration
    if [ -f "$CONFIG_FILE" ]; then
        rm -f "$CONFIG_FILE"
        print_success "Removed: $CONFIG_FILE"
    fi

    # Remove test databases
    if [ -f "$TEST_DB" ]; then
        rm -f "$TEST_DB"
        print_success "Removed: $TEST_DB"
    fi

    if [ -f "$DEFAULT_DB" ]; then
        rm -f "$DEFAULT_DB"
        print_success "Removed: $DEFAULT_DB"
    fi

    print_success "Cleanup complete"
}

# Main execution
main() {
    local command=$1
    local option=$2

    case "$command" in
        start)
            start_server "$option"
            ;;
        stop)
            stop_server
            ;;
        status)
            check_status
            ;;
        restart)
            restart_server "$option"
            ;;
        logs)
            show_logs
            ;;
        clean)
            cleanup_files
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            if [ -z "$command" ]; then
                print_error "No command specified"
                echo ""
                show_help
                exit 1
            else
                print_error "Unknown command: $command"
                echo ""
                show_help
                exit 1
            fi
            ;;
    esac
}

# Run main function
main "$@"
