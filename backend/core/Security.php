<?php
declare(strict_types=1);

namespace App\Core;

class Security {
    /**
     * Apply security headers preventing Clickjacking, XSS, MIME sniffing
     */
    public static function applyHeaders(): void {
        if (!headers_sent()) {
            header("X-Frame-Options: SAMEORIGIN");
            header("X-XSS-Protection: 1; mode=block");
            header("X-Content-Type-Options: nosniff");
            header("Referrer-Policy: strict-origin-when-cross-origin");
            
            // Content Security Policy — restricts script/style/font sources and allows trusted Google avatars
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; img-src 'self' data: blob: https://*.googleusercontent.com https://lh3.googleusercontent.com https://images.unsplash.com https://*.gravatar.com; connect-src 'self'; frame-ancestors 'none'; object-src 'none'; base-uri 'self'; form-action 'self';");
            
            // Permissions Policy — disable unnecessary browser features
            header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()");
            
            // HSTS if HTTPS is active or reverse-proxied
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
            if ($isHttps) {
                header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
            }

            // Remove server fingerprint
            header("Server: HomeMed");
            // Remove PHP version header
            header_remove("X-Powered-By");
        }
    }

    /**
     * Escape HTML output against XSS
     */
    public static function e(?string $string): string {
        return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Generate / Retrieve CSRF Token
     */
    public static function generateCsrf(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Regenerate CSRF token (call after privilege elevation to prevent fixation)
     */
    public static function regenerateCsrf(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    /**
     * Prevent session fixation — regenerate session ID after login
     */
    public static function regenerateSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Validate CSRF Token
     */
    public static function validateCsrf(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitize general input string
     */
    public static function sanitizeString(?string $str): string {
        return trim(strip_tags((string)$str));
    }

    /**
     * Sanitize numeric float (Prices, weights)
     */
    public static function sanitizeFloat(mixed $val, float $default = 0.0): float {
        if (!is_numeric($val)) return $default;
        return max(0.0, (float)$val);
    }

    /**
     * Sanitize integer (Quantities, IDs)
     */
    public static function sanitizeInt(mixed $val, int $default = 0): int {
        if (!is_numeric($val)) return $default;
        return max(0, (int)$val);
    }

    /**
     * Mask phone number for public display (e.g. +91 98765 ****0)
     */
    public static function maskPhone(string $phone): string {
        $clean = preg_replace('/\s+/', '', $phone);
        $len = strlen($clean);
        if ($len <= 5) return '*****';
        return substr($clean, 0, $len - 5) . '****' . substr($clean, -1);
    }

    /**
     * Mask name for public tracking display (e.g. Ramesh K***)
     */
    public static function maskName(string $name): string {
        $parts = explode(' ', trim($name));
        $maskedParts = [];
        foreach ($parts as $part) {
            $l = mb_strlen($part);
            if ($l <= 2) {
                $maskedParts[] = $part;
            } else {
                $maskedParts[] = mb_substr($part, 0, 2) . str_repeat('*', min(4, $l - 2));
            }
        }
        return implode(' ', $maskedParts);
    }

    /**
     * Flash Message Helper (For Notifications across redirects)
     */
    public static function setFlash(string $type, string $message): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_message'] = [
            'type' => $type, // 'success', 'danger', 'warning', 'info'
            'text' => $message
        ];
    }

    public static function getFlash(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['flash_message'])) {
            $msg = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $msg;
        }
        return null;
    }

    /**
     * Session-based IP/Action Rate Limiter
     * Returns true if allowed, false if limit exceeded
     */
    public static function checkRateLimit(string $actionKey, int $maxRequests = 5, int $windowSeconds = 600): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $sessionKey = 'ratelimit_' . md5($ip . '_' . $actionKey);
        $now = time();

        if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [];
        }

        // Prune older timestamps outside window
        $_SESSION[$sessionKey] = array_filter($_SESSION[$sessionKey], fn($ts) => ($now - $ts) < $windowSeconds);

        if (count($_SESSION[$sessionKey]) >= $maxRequests) {
            return false;
        }

        $_SESSION[$sessionKey][] = $now;
        return true;
    }
}
