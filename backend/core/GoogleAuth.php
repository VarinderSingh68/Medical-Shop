<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Google OAuth 2.0 Client Service
 * Handles Google Login authorization redirects, token exchange, and user profile retrieval
 */
class GoogleAuth {
    private static ?array $config = null;

    /**
     * Load Google credentials from env/.env or environment
     */
    public static function getConfig(): array {
        if (self::$config !== null) {
            return self::$config;
        }

        $envFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'env' . DIRECTORY_SEPARATOR . '.env';
        $env = [];
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                if (str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    $env[trim($k)] = trim(trim($v), '"\'');
                }
            }
        }

        // Check website_settings in database (persisted configuration)
        $dbSettings = [];
        try {
            if (!class_exists('DatabaseConnection')) {
                $dbFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
                if (file_exists($dbFile)) {
                    require_once $dbFile;
                }
            }

            if (class_exists('DatabaseConnection')) {
                $db = \DatabaseConnection::getInstance();
                $stmt = $db->query("SELECT setting_key, setting_value FROM website_settings WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_redirect_uri')");
                if ($stmt) {
                    $dbSettings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
                }
            }
        } catch (\Throwable $e) {
            // Safe fallback
        }

        $clientId = getenv('GOOGLE_CLIENT_ID') 
            ?: (!empty($dbSettings['google_client_id']) ? $dbSettings['google_client_id'] : ($env['GOOGLE_CLIENT_ID'] ?? ''));
        $clientSecret = getenv('GOOGLE_CLIENT_SECRET') 
            ?: (!empty($dbSettings['google_client_secret']) ? $dbSettings['google_client_secret'] : ($env['GOOGLE_CLIENT_SECRET'] ?? ''));
        $configuredRedirect = getenv('GOOGLE_REDIRECT_URI') 
            ?: (!empty($dbSettings['google_redirect_uri']) ? $dbSettings['google_redirect_uri'] : ($env['GOOGLE_REDIRECT_URI'] ?? ''));

        $currentAppRedirect = app_url('auth/google-callback.php');
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
            $redirectUri = $currentAppRedirect;
        } else {
            $redirectUri = !empty($configuredRedirect) ? $configuredRedirect : $currentAppRedirect;
        }

        self::$config = [
            'client_id'     => trim($clientId),
            'client_secret' => trim($clientSecret),
            'redirect_uri'  => $redirectUri,
        ];

        return self::$config;
    }

    /**
     * Check whether Google OAuth credentials have been configured
     */
    public static function isConfigured(): bool {
        $cfg = self::getConfig();
        return !empty($cfg['client_id']) 
            && !empty($cfg['client_secret'])
            && !str_contains($cfg['client_id'], 'your-google-client-id');
    }

    /**
     * Generate Google OAuth 2.0 Authorization URL
     */
    public static function getAuthUrl(string $returnTo = ''): string {
        $cfg = self::getConfig();
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        if ($returnTo !== '') {
            $_SESSION['oauth_return_to'] = $returnTo;
        }

        $params = [
            'client_id'             => $cfg['client_id'],
            'redirect_uri'          => $cfg['redirect_uri'],
            'response_type'         => 'code',
            'scope'                 => 'openid email profile',
            'access_type'           => 'online',
            'include_granted_scopes'=> 'true',
            'state'                 => $state,
            'prompt'                => 'select_account',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for user profile
     */
    public static function handleCallback(string $code, string $receivedState): array {
        // Validate CSRF state
        $savedState = $_SESSION['oauth_state'] ?? '';
        unset($_SESSION['oauth_state']);

        if (empty($savedState) || !hash_equals($savedState, $receivedState)) {
            return ['success' => false, 'error' => 'Invalid or expired OAuth state parameter. Please try logging in again.'];
        }

        $cfg = self::getConfig();

        // 1. Exchange code for Access Token
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = http_build_query([
            'code'          => $code,
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri'  => $cfg['redirect_uri'],
            'grant_type'    => 'authorization_code',
        ]);

        $tokenResponse = self::httpPost($tokenUrl, $postData, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        if (!$tokenResponse['success']) {
            return ['success' => false, 'error' => 'Failed to connect to Google token endpoint: ' . $tokenResponse['error']];
        }

        $tokenData = json_decode($tokenResponse['body'], true);
        if (empty($tokenData['access_token'])) {
            $errDesc = $tokenData['error_description'] ?? ($tokenData['error'] ?? 'Unknown token exchange error');
            return ['success' => false, 'error' => 'Google authorization failed: ' . $errDesc];
        }

        $accessToken = $tokenData['access_token'];

        // 2. Fetch User Profile
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
        $userResponse = self::httpGet($userInfoUrl, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ]);

        if (!$userResponse['success']) {
            return ['success' => false, 'error' => 'Failed to fetch Google profile: ' . $userResponse['error']];
        }

        $userData = json_decode($userResponse['body'], true);
        if (empty($userData['email'])) {
            return ['success' => false, 'error' => 'Could not retrieve email address from your Google Account.'];
        }

        return [
            'success' => true,
            'user'    => [
                'google_id' => $userData['sub'] ?? '',
                'email'     => $userData['email'] ?? '',
                'name'      => $userData['name'] ?? ($userData['given_name'] ?? 'Google User'),
                'avatar'    => $userData['picture'] ?? null,
            ],
        ];
    }

    /**
     * HTTP GET helper using cURL with stream fallback
     */
    private static function httpGet(string $url, array $headers = []): array {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false) {
                return ['success' => false, 'error' => $err, 'status' => $code];
            }
            return ['success' => true, 'body' => (string)$body, 'status' => $code];
        }

        // Stream fallback
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($opts);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return ['success' => false, 'error' => 'file_get_contents failed', 'status' => 0];
        }
        return ['success' => true, 'body' => $body, 'status' => 200];
    }

    /**
     * HTTP POST helper using cURL with stream fallback
     */
    private static function httpPost(string $url, string $data, array $headers = []): array {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false) {
                return ['success' => false, 'error' => $err, 'status' => $code];
            }
            return ['success' => true, 'body' => (string)$body, 'status' => $code];
        }

        // Stream fallback
        $headers[] = 'Content-Length: ' . strlen($data);
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers),
                'content' => $data,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($opts);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return ['success' => false, 'error' => 'file_get_contents failed', 'status' => 0];
        }
        return ['success' => true, 'body' => $body, 'status' => 200];
    }
}
