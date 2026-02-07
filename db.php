<?php
/**
 * AuthLite Database Handler
 *
 * Manages SQLite database operations for AuthLite.
 * Creates necessary tables on first run and provides CRUD operations.
 *
 * @package AuthLite
 */

class AuthLiteDB
{
    /**
     * @var PDO|null Database connection instance
     */
    private static $pdo = null;

    /**
     * @var array Database configuration
     */
    private static $config = [];

    /**
     * @var bool Debug mode flag
     */
    private static $debug = false;

    /**
     * Initialize the database connection
     *
     * @param array $config Configuration array
     * @return PDO Database connection
     * @throws Exception If database connection fails
     */
    public static function init(array $config): PDO
    {
        self::$config = $config;
        self::$debug = $config['debug']['enabled'] ?? false;

        if (self::$pdo === null) {
            try {
                $dbPath = $config['database']['path'] ?? __DIR__ . '/auth.db';

                // Ensure directory exists
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                self::$pdo = new PDO("sqlite:" . $dbPath);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

                // Create tables if configured to do so
                if ($config['database']['create_tables'] ?? true) {
                    self::createTables();
                }

            } catch (PDOException $e) {
                self::logError("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed", 0, $e);
            }
        }

        return self::$pdo;
    }

    /**
     * Create necessary tables
     */
    private static function createTables(): void
    {
        $pdo = self::$pdo;

        // Users table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                username TEXT,
                password_hash TEXT NOT NULL,
                is_confirmed BOOLEAN DEFAULT 0,
                confirmation_token TEXT,
                confirmation_expires DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_login DATETIME,
                is_active BOOLEAN DEFAULT 1
            )
        ");

        // Sessions table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT UNIQUE NOT NULL,
                user_id INTEGER,
                ip_address TEXT,
                user_agent TEXT,
                data TEXT,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // Tokens table (for JWT, confirmation, password reset, etc.)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT UNIQUE NOT NULL,
                type TEXT NOT NULL, -- 'jwt', 'confirm', 'reset', 'refresh'
                user_id INTEGER NOT NULL,
                data TEXT, -- Additional token data as JSON
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                revoked BOOLEAN DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // OAuth connections table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS oauth_connections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                provider TEXT NOT NULL, -- 'google', 'facebook', 'apple'
                provider_user_id TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                email TEXT,
                name TEXT,
                access_token TEXT,
                refresh_token TEXT,
                token_expires DATETIME,
                data TEXT, -- Additional provider data as JSON
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(provider, provider_user_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        // Create indexes
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessions_session_id ON sessions(session_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions(expires_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tokens_token ON tokens(token)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tokens_expires ON tokens(expires_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_oauth_provider_user ON oauth_connections(provider, provider_user_id)");
    }

    /**
     * Get the database connection
     *
     * @return PDO Database connection
     * @throws Exception If database not initialized
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            throw new Exception("Database not initialized. Call AuthLiteDB::init() first.");
        }
        return self::$pdo;
    }

    /**
     * Execute a prepared statement
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for placeholders
     * @return PDOStatement|false Prepared statement
     */
    public static function execute(string $sql, array $params = [])
    {
        try {
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            self::logError("Query execution failed: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }

    /**
     * Insert a record and return the last insert ID
     *
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return int|false Last insert ID or false on failure
     */
    public static function insert(string $table, array $data)
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = self::execute($sql, array_values($data));
        return $stmt ? self::$pdo->lastInsertId() : false;
    }

    /**
     * Update records
     *
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @param string $where WHERE clause (without WHERE keyword)
     * @param array $whereParams Parameters for WHERE clause
     * @return int|false Number of affected rows or false on failure
     */
    public static function update(string $table, array $data, string $where, array $whereParams = [])
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "$column = ?";
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setParts),
            $where
        );

        $params = array_merge(array_values($data), $whereParams);
        $stmt = self::execute($sql, $params);
        return $stmt ? $stmt->rowCount() : false;
    }

    /**
     * Select records
     *
     * @param string $table Table name
     * @param string $columns Columns to select (default: *)
     * @param string|null $where WHERE clause (without WHERE keyword)
     * @param array $whereParams Parameters for WHERE clause
     * @param string|null $order ORDER BY clause
     * @param int|null $limit LIMIT clause
     * @param int|null $offset OFFSET clause
     * @return array|false Array of records or false on failure
     */
    public static function select(
        string $table,
        string $columns = '*',
        string $where = null,
        array $whereParams = [],
        string $order = null,
        int $limit = null,
        int $offset = null
    ) {
        $sql = "SELECT $columns FROM $table";

        if ($where) {
            $sql .= " WHERE $where";
        }

        if ($order) {
            $sql .= " ORDER BY $order";
        }

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }

        $stmt = self::execute($sql, $whereParams);
        return $stmt ? $stmt->fetchAll() : false;
    }

    /**
     * Delete records
     *
     * @param string $table Table name
     * @param string $where WHERE clause (without WHERE keyword)
     * @param array $whereParams Parameters for WHERE clause
     * @return int|false Number of affected rows or false on failure
     */
    public static function delete(string $table, string $where, array $whereParams = [])
    {
        $sql = "DELETE FROM $table WHERE $where";
        $stmt = self::execute($sql, $whereParams);
        return $stmt ? $stmt->rowCount() : false;
    }

    /**
     * Execute raw SQL (for debug/admin purposes)
     *
     * @param string $sql Raw SQL query
     * @return array|false Query results or false on failure
     */
    public static function raw(string $sql)
    {
        try {
            $stmt = self::$pdo->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            self::logError("Raw query failed: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }

    /**
     * Log error messages
     *
     * @param string $message Error message
     */
    private static function logError(string $message): void
    {
        if (self::$debug) {
            error_log("[AuthLiteDB] " . $message);
        }
    }

    /**
     * Get database statistics
     *
     * @return array Database statistics
     */
    public static function getStats(): array
    {
        $stats = [];

        try {
            $tables = ['users', 'sessions', 'tokens', 'oauth_connections'];
            foreach ($tables as $table) {
                $stmt = self::$pdo->query("SELECT COUNT(*) as count FROM $table");
                $stats[$table] = $stmt->fetch()['count'];
            }

            // Get database size
            $dbPath = self::$config['database']['path'] ?? __DIR__ . '/auth.db';
            if (file_exists($dbPath)) {
                $stats['size'] = filesize($dbPath);
            }

        } catch (Exception $e) {
            self::logError("Failed to get stats: " . $e->getMessage());
        }

        return $stats;
    }
}
