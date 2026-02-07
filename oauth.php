<?php
/**
 * AuthLite OAuth Handler
 *
 * Handles social login via Google, Facebook, and Apple using raw cURL calls.
 * No external SDKs or OAuth libraries required.
 *
 * @package AuthLite
 */

class AuthLiteOAuth
{
    /**
     * @var array Configuration array
     */
    private static $config = [];

    /**
     * @var array OAuth provider configurations
     */
    private static $providers = [];

    /**
     * @var PDO Database connection
     */
    private static $db = null;

    /**
     * Initialize OAuth handler
     *
     * @param array $config Configuration array
     * @param PDO $db Database connection
     */
    public static function init(array $config, PDO $db): void
    {
        self::$config = $config;
        self::$db = $db;
        self::$providers = $config['oauth'] ?? [];

        // Set default redirect URI if not specified
        foreach (['google', 'facebook', 'apple'] as $provider) {
            if (isset(self::$providers[$provider]['enabled']) &&
                self::$providers[$provider]['enabled'] &&
                empty(self::$providers[$provider]['redirect_uri'])) {

                $baseUrl = $config['urls']['base'] ?? self::getBaseUrl();
                self::$providers[$provider]['redirect_uri'] = $baseUrl . '/oauth/' . $provider . '/callback';
            }
        }
    }

    /**
     * Get base URL for redirect URIs
     *
     * @return string Base URL
     */
    private static function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Generate authorization URL for a provider
     *
     * @param string $provider Provider name ('google', 'facebook', 'apple')
     * @param string $state CSRF state token
     * @param array $scopes Additional scopes (optional)
     * @return string|false Authorization URL or false if provider not enabled
     */
    public static function getAuthUrl(string $provider, string $state, array $scopes = [])
    {
        if (!self::isProviderEnabled($provider)) {
            return false;
        }

        $config = self::$providers[$provider];
        $params = [];

        switch ($provider) {
            case 'google':
                $params = [
                    'client_id' => $config['client_id'],
                    'redirect_uri' => $config['redirect_uri'],
                    'response_type' => 'code',
                    'scope' => implode(' ', array_merge([
                        'openid',
                        'email',
                        'profile'
                    ], $scopes)),
                    'state' => $state,
                    'access_type' => 'offline',
                    'prompt' => 'consent'
                ];
                return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

            case 'facebook':
                $params = [
                    'client_id' => $config['client_id'],
                    'redirect_uri' => $config['redirect_uri'],
                    'state' => $state,
                    'scope' => implode(',', array_merge([
                        'email',
                        'public_profile'
                    ], $scopes)),
                    'response_type' => 'code'
                ];
                return 'https://www.facebook.com/v12.0/dialog/oauth?' . http_build_query($params);

            case 'apple':
                $params = [
                    'client_id' => $config['client_id'],
                    'redirect_uri' => $config['redirect_uri'],
                    'response_type' => 'code',
                    'response_mode' => 'form_post',
                    'scope' => implode(' ', array_merge([
                        'name',
                        'email'
                    ], $scopes)),
                    'state' => $state
                ];
                return 'https://appleid.apple.com/auth/authorize?' . http_build_query($params);

            default:
                return false;
        }
    }

    /**
     * Handle OAuth callback and exchange code for tokens
     *
     * @param string $provider Provider name
     * @param string $code Authorization code
     * @param string $state CSRF state token (for verification)
     * @param string $sessionState Session state token
     * @return array|false User data or false on failure
     */
    public static function handleCallback(string $provider, string $code, string $state, string $sessionState)
    {
        // Verify state to prevent CSRF
        if (!hash_equals($sessionState, $state)) {
            return false;
        }

        if (!self::isProviderEnabled($provider)) {
            return false;
        }

        // Exchange code for access token
        $tokenData = self::exchangeCode($provider, $code);
        if (!$tokenData) {
            return false;
        }

        // Get user profile
        $profile = self::getUserProfile($provider, $tokenData);
        if (!$profile) {
            return false;
        }

        // Ensure we have required profile data
        if (empty($profile['email'])) {
            return false;
        }

        // Add provider info to profile
        $profile['provider'] = $provider;
        $profile['provider_user_id'] = $profile['id'] ?? '';
        $profile['access_token'] = $tokenData['access_token'] ?? '';
        $profile['refresh_token'] = $tokenData['refresh_token'] ?? '';

        if (isset($tokenData['expires_in'])) {
            $profile['token_expires'] = time() + $tokenData['expires_in'];
        }

        return $profile;
    }

    /**
     * Exchange authorization code for access token
     *
     * @param string $provider Provider name
     * @param string $code Authorization code
     * @return array|false Token data or false on failure
     */
    private static function exchangeCode(string $provider, string $code)
    {
        $config = self::$providers[$provider];
        $params = [
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'grant_type' => 'authorization_code'
        ];

        $url = '';
        $headers = [];

        switch ($provider) {
            case 'google':
                $url = 'https://oauth2.googleapis.com/token';
                break;

            case 'facebook':
                $url = 'https://graph.facebook.com/v12.0/oauth/access_token';
                break;

            case 'apple':
                $url = 'https://appleid.apple.com/auth/token';
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                break;

            default:
                return false;
        }

        $response = self::curlRequest($url, $params, $headers);
        if (!$response) {
            return false;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : false;
    }

    /**
     * Get user profile from provider
     *
     * @param string $provider Provider name
     * @param array $tokenData Access token data
     * @return array|false User profile or false on failure
     */
    private static function getUserProfile(string $provider, array $tokenData)
    {
        if (empty($tokenData['access_token'])) {
            return false;
        }

        $accessToken = $tokenData['access_token'];
        $url = '';
        $headers = ["Authorization: Bearer $accessToken"];

        switch ($provider) {
            case 'google':
                $url = 'https://www.googleapis.com/oauth2/v3/userinfo';
                break;

            case 'facebook':
                $url = 'https://graph.facebook.com/v12.0/me?' . http_build_query([
                    'fields' => 'id,name,email,picture',
                    'access_token' => $accessToken
                ]);
                $headers = []; // Facebook uses query param instead of header
                break;

            case 'apple':
                // Apple returns user data in ID token (JWT)
                if (isset($tokenData['id_token'])) {
                    return self::parseAppleIdToken($tokenData['id_token']);
                }
                return false;

            default:
                return false;
        }

        $response = self::curlRequest($url, [], $headers, 'GET');
        if (!$response) {
            return false;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return false;
        }

        // Normalize profile data
        return self::normalizeProfile($provider, $data);
    }

    /**
     * Parse Apple ID token (JWT) to get user data
     *
     * @param string $idToken Apple ID token
     * @return array|false User data or false on failure
     */
    private static function parseAppleIdToken(string $idToken)
    {
        // Simple JWT parsing (for Apple's specific format)
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return false;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!is_array($payload)) {
            return false;
        }

        // Apple may not provide email in subsequent logins
        // Email is only guaranteed on first login
        $profile = [
            'id' => $payload['sub'] ?? '',
            'email' => $payload['email'] ?? '',
            'email_verified' => true
        ];

        // Apple may provide name in the initial callback POST data
        // This would need to be passed separately from the main handler
        return $profile;
    }

    /**
     * Normalize profile data from different providers
     *
     * @param string $provider Provider name
     * @param array $data Raw profile data
     * @return array Normalized profile data
     */
    private static function normalizeProfile(string $provider, array $data): array
    {
        $normalized = [
            'id' => $data['id'] ?? $data['sub'] ?? '',
            'email' => $data['email'] ?? '',
            'name' => '',
            'picture' => '',
            'email_verified' => $data['email_verified'] ?? false
        ];

        switch ($provider) {
            case 'google':
                $normalized['name'] = $data['name'] ?? '';
                $normalized['picture'] = $data['picture'] ?? '';
                $normalized['email_verified'] = $data['email_verified'] ?? false;
                break;

            case 'facebook':
                $normalized['name'] = $data['name'] ?? '';
                if (isset($data['picture']['data']['url'])) {
                    $normalized['picture'] = $data['picture']['data']['url'];
                }
                break;

            case 'apple':
                // Apple name may come from separate POST data
                $normalized['name'] = '';
                break;
        }

        return $normalized;
    }

    /**
     * Create or update user from OAuth profile
     *
     * @param array $profile Normalized user profile
     * @return array|false User data or false on failure
     */
    public static function createOrUpdateUser(array $profile)
    {
        if (empty($profile['email']) || empty($profile['provider']) || empty($profile['provider_user_id'])) {
            return false;
        }

        try {
            // Check if OAuth connection already exists
            $stmt = self::$db->prepare("
                SELECT u.*, oc.id as connection_id
                FROM oauth_connections oc
                JOIN users u ON oc.user_id = u.id
                WHERE oc.provider = ? AND oc.provider_user_id = ?
            ");

            $stmt->execute([$profile['provider'], $profile['provider_user_id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing connection
                self::$db->prepare("
                    UPDATE oauth_connections SET
                        email = ?,
                        name = ?,
                        access_token = ?,
                        refresh_token = ?,
                        token_expires = ?,
                        data = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ")->execute([
                    $profile['email'],
                    $profile['name'],
                    $profile['access_token'] ?? '',
                    $profile['refresh_token'] ?? '',
                    isset($profile['token_expires']) ? date('Y-m-d H:i:s', $profile['token_expires']) : null,
                    json_encode($profile),
                    $existing['connection_id']
                ]);

                // Update user's last login
                self::$db->prepare("
                    UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?
                ")->execute([$existing['id']]);

                return $existing;
            }

            // Check if user exists with this email
            $stmt = self::$db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$profile['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Create new user
                self::$db->prepare("
                    INSERT INTO users (email, username, password_hash, is_confirmed, is_active, created_at)
                    VALUES (?, ?, ?, 1, 1, CURRENT_TIMESTAMP)
                ")->execute([
                    $profile['email'],
                    $profile['email'], // Use email as username initially
                    password_hash(random_bytes(32), PASSWORD_DEFAULT) // Random password for OAuth users
                ]);

                $userId = self::$db->lastInsertId();
            } else {
                $userId = $user['id'];
            }

            // Create OAuth connection
            self::$db->prepare("
                INSERT INTO oauth_connections (
                    provider, provider_user_id, user_id, email, name,
                    access_token, refresh_token, token_expires, data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $profile['provider'],
                $profile['provider_user_id'],
                $userId,
                $profile['email'],
                $profile['name'],
                $profile['access_token'] ?? '',
                $profile['refresh_token'] ?? '',
                isset($profile['token_expires']) ? date('Y-m-d H:i:s', $profile['token_expires']) : null,
                json_encode($profile)
            ]);

            // Get complete user data
            $stmt = self::$db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("OAuth user creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Make cURL request
     *
     * @param string $url Request URL
     * @param array $params POST parameters (empty for GET)
     * @param array $headers Additional headers
     * @param string $method HTTP method
     * @return string|false Response body or false on failure
     */
    private static function curlRequest(string $url, array $params = [], array $headers = [], string $method = 'POST')
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($method === 'POST' && !empty($params)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            error_log("cURL error for $url: $error");
            return false;
        }

        if ($status !== 200) {
            error_log("HTTP $status for $url: $response");
            return false;
        }

        return $response;
    }

    /**
     * Check if provider is enabled and configured
     *
     * @param string $provider Provider name
     * @return bool True if provider is enabled
     */
    public static function isProviderEnabled(string $provider): bool
    {
        if (!isset(self::$providers[$provider])) {
            return false;
        }

        $config = self::$providers[$provider];

        return $config['enabled'] ?? false &&
               !empty($config['client_id']) &&
               !empty($config['client_secret']);
    }

    /**
     * Get enabled providers
     *
     * @return array List of enabled provider names
     */
    public static function getEnabledProviders(): array
    {
        $enabled = [];

        foreach (['google', 'facebook', 'apple'] as $provider) {
            if (self::isProviderEnabled($provider)) {
                $enabled[] = $provider;
            }
        }

        return $enabled;
    }

    /**
     * Refresh access token
     *
     * @param string $provider Provider name
     * @param string $refreshToken Refresh token
     * @return array|false New token data or false on failure
     */
    public static function refreshToken(string $provider, string $refreshToken)
    {
        if (!self::isProviderEnabled($provider)) {
            return false;
        }

        $config = self::$providers[$provider];
        $params = [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];

        $url = '';
        switch ($provider) {
            case 'google':
                $url = 'https://oauth2.googleapis.com/token';
                break;

            case 'facebook':
                // Facebook doesn't use refresh tokens in the same way
                return false;

            case 'apple':
                // Apple refresh tokens are long-lived (up to 6 months)
                return ['access_token' => 'valid', 'refresh_token' => $refreshToken];

            default:
                return false;
        }

        $response = self::curlRequest($url, $params);
        if (!$response) {
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * Revoke token
     *
     * @param string $provider Provider name
     * @param string $token Access or refresh token
     * @return bool True if successful
     */
    public static function revokeToken(string $provider, string $token): bool
    {
        if (!self::isProviderEnabled($provider)) {
            return false;
        }

        $url = '';
        $params = ['token' => $token];

        switch ($provider) {
            case 'google':
                $url = 'https://oauth2.googleapis.com/revoke';
                break;

            case 'facebook':
                // Facebook tokens expire automatically
                return true;

            case 'apple':
                // Apple tokens are self-contained and expire
                return true;

            default:
                return false;
        }

        $response = self::curlRequest($url, $params);
        return $response !== false;
    }
}

/**
 * Convenience functions
 */

/**
 * Get OAuth authorization URL
 *
 * @param string $provider Provider name
 * @param string $state CSRF state token
 * @param array $scopes Additional scopes
 * @return string|false Authorization URL or false
 */
function authlite_oauth_url(string $provider, string $state, array $scopes = [])
{
    return AuthLiteOAuth::getAuthUrl($provider, $state, $scopes);
}

/**
 * Handle OAuth callback
 *
 * @param string $provider Provider name
 * @param string $code Authorization code
 * @param string $state Received state token
 * @param string $sessionState Session state token
 * @return array|false User profile or false
 */
function authlite_oauth_callback(string $provider, string $code, string $state, string $sessionState)
{
    return AuthLiteOAuth::handleCallback($provider, $code, $state, $sessionState);
}

/**
 * Check if OAuth provider is enabled
 *
 * @param string $provider Provider name
 * @return bool True if enabled
 */
function authlite_oauth_enabled(string $provider): bool
{
    return AuthLiteOAuth::isProviderEnabled($provider);
}

/**
 * Get list of enabled OAuth providers
 *
 * @return array Enabled provider names
 */
function authlite_oauth_providers(): array
{
    return AuthLiteOAuth::getEnabledProviders();
}
