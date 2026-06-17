<?php
/**
 * SecurityMiddleware.php
 * Vị trí: app/core/SecurityMiddleware.php
 *
 * Xử lý toàn bộ bảo mật:
 *   1. Security Headers
 *   2. Session timeout
 *   3. Brute force protection
 *   4. CSRF token
 *   5. XSS protection helpers
 *   6. Rate limiting
 */
class SecurityMiddleware
{
    // ── Cấu hình ─────────────────────────────────────────────
    private const SESSION_TIMEOUT   = 30 * 60;      // 30 phút không hoạt động
    private const MAX_LOGIN_ATTEMPT = 5;             // Số lần đăng nhập sai tối đa
    private const LOCKOUT_TIME      = 15 * 60;       // Khóa 15 phút
    private const RATE_LIMIT_MAX    = 60;            // Max 60 request/phút
    private const CSRF_TOKEN_NAME   = '_csrf_token';

    // ════════════════════════════════════════════════════════
    // 1. SECURITY HEADERS — gọi ở đầu mỗi response
    // ════════════════════════════════════════════════════════
    public static function setSecurityHeaders(): void
    {
        if (headers_sent()) return;

        // Chống clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        // Chống MIME sniffing
        header('X-Content-Type-Options: nosniff');
        // XSS Filter (trình duyệt cũ)
        header('X-XSS-Protection: 1; mode=block');
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // Permissions policy
        header('Permissions-Policy: geolocation=(self), camera=()');
        // Content Security Policy — cho phép inline script/style vì project dùng nhiều
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: blob:; connect-src 'self' https:;");
    }

    // ════════════════════════════════════════════════════════
    // 2. SESSION TIMEOUT — gọi sau ensureSession()
    // ════════════════════════════════════════════════════════
    public static function checkSessionTimeout(): void
    {
        if (!isset($_SESSION['account_id'])) return;

        $last = $_SESSION['last_activity'] ?? 0;
        if ($last && (time() - $last) > self::SESSION_TIMEOUT) {
            // Session hết hạn — xóa và redirect login
            $_SESSION = [];
            session_destroy();
            $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
            if ($isApi) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['ok' => false, 'msg' => 'Phiên đăng nhập đã hết hạn', 'expired' => true]);
                exit;
            }
            header('Location: ' . url('login?error=session_expired'));
            exit;
        }
        $_SESSION['last_activity'] = time();
    }

    // ════════════════════════════════════════════════════════
    // 3. BRUTE FORCE PROTECTION
    // ════════════════════════════════════════════════════════

    /**
     * Kiểm tra IP có bị khóa không
     * Trả về số giây còn lại nếu bị khóa, 0 nếu không
     */
    public static function checkBruteForce(string $ip): int
    {
        $key      = 'bf_' . md5($ip);
        $data     = self::readCache($key);
        if (!$data) return 0;

        if ($data['attempts'] >= self::MAX_LOGIN_ATTEMPT) {
            $remaining = ($data['locked_at'] + self::LOCKOUT_TIME) - time();
            if ($remaining > 0) return $remaining;
            // Hết thời gian khóa → reset
            self::deleteCache($key);
        }
        return 0;
    }

    /** Ghi nhận lần đăng nhập thất bại */
    public static function recordFailedLogin(string $ip): void
    {
        $key  = 'bf_' . md5($ip);
        $data = self::readCache($key) ?? ['attempts' => 0, 'locked_at' => 0];
        $data['attempts']++;
        if ($data['attempts'] >= self::MAX_LOGIN_ATTEMPT) {
            $data['locked_at'] = time();
        }
        self::writeCache($key, $data, self::LOCKOUT_TIME + 60);
    }

    /** Xóa record sau khi đăng nhập thành công */
    public static function clearFailedLogin(string $ip): void
    {
        self::deleteCache('bf_' . md5($ip));
    }

    /** Lấy số lần đăng nhập sai còn lại */
    public static function getRemainingAttempts(string $ip): int
    {
        $data = self::readCache('bf_' . md5($ip));
        if (!$data) return self::MAX_LOGIN_ATTEMPT;
        return max(0, self::MAX_LOGIN_ATTEMPT - $data['attempts']);
    }

    // ════════════════════════════════════════════════════════
    // 4. CSRF TOKEN
    // ════════════════════════════════════════════════════════

    /** Tạo hoặc lấy CSRF token hiện tại */
    public static function getCsrfToken(): string
    {
        if (empty($_SESSION[self::CSRF_TOKEN_NAME])) {
            $_SESSION[self::CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_TOKEN_NAME];
    }

    /** Render hidden input CSRF cho form */
    public static function csrfField(): string
    {
        $token = self::getCsrfToken();
        return '<input type="hidden" name="' . self::CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }

    /** Xác thực CSRF token — gọi ở đầu POST handler */
    public static function verifyCsrf(): bool
    {
        $token = $_POST[self::CSRF_TOKEN_NAME]
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';
        if (empty($_SESSION[self::CSRF_TOKEN_NAME])) return false;
        return hash_equals($_SESSION[self::CSRF_TOKEN_NAME], $token);
    }

    /** Xác thực và throw nếu sai (dùng trong controller) */
    public static function requireCsrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        // Bỏ qua API JSON requests (dùng auth header thay thế)
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) return;

        if (!self::verifyCsrf()) {
            http_response_code(403);
            $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
            if ($isApi) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'msg' => 'CSRF token không hợp lệ']);
            } else {
                echo '<h3>403 - Yêu cầu không hợp lệ (CSRF)</h3>';
            }
            exit;
        }
        // Rotate token sau mỗi request
        $_SESSION[self::CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }

    // ════════════════════════════════════════════════════════
    // 5. XSS PROTECTION HELPERS
    // ════════════════════════════════════════════════════════

    /** Escape output HTML — dùng thay htmlspecialchars */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Sanitize input string */
    public static function sanitize(string $input): string
    {
        return trim(strip_tags($input));
    }

    /** Validate và sanitize integer */
    public static function int(mixed $value, int $default = 0): int
    {
        $v = filter_var($value, FILTER_VALIDATE_INT);
        return $v === false ? $default : (int)$v;
    }

    // ════════════════════════════════════════════════════════
    // 6. RATE LIMITING — giới hạn request API
    // ════════════════════════════════════════════════════════
    public static function checkRateLimit(string $ip, string $endpoint = 'global'): bool
    {
        $key  = 'rl_' . md5($ip . $endpoint);
        $data = self::readCache($key) ?? ['count' => 0, 'window' => time()];

        // Reset sau 1 phút
        if (time() - $data['window'] > 60) {
            $data = ['count' => 0, 'window' => time()];
        }

        $data['count']++;
        self::writeCache($key, $data, 120);

        return $data['count'] <= self::RATE_LIMIT_MAX;
    }

    public static function requireRateLimit(string $endpoint = 'global'): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!self::checkRateLimit($ip, $endpoint)) {
            http_response_code(429);
            header('Retry-After: 60');
            $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
            if ($isApi) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'msg' => 'Quá nhiều request. Vui lòng thử lại sau.']);
            } else {
                echo '<h3>429 - Too Many Requests</h3>';
            }
            exit;
        }
    }

    // ════════════════════════════════════════════════════════
    // PRIVATE: File-based cache (không cần Redis/Memcached)
    // ════════════════════════════════════════════════════════
    private static function cacheDir(): string
    {
        $dir = (defined('STORAGE_PATH') ? STORAGE_PATH : ROOT_PATH . '/storage') . '/cache/security';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir;
    }

    private static function cacheFile(string $key): string
    {
        return self::cacheDir() . '/' . $key . '.json';
    }

    private static function readCache(string $key): ?array
    {
        $file = self::cacheFile($key);
        if (!file_exists($file)) return null;
        $data = json_decode(file_get_contents($file), true);
        if (!$data || (isset($data['_exp']) && time() > $data['_exp'])) {
            @unlink($file);
            return null;
        }
        return $data;
    }

    private static function writeCache(string $key, array $data, int $ttl = 3600): void
    {
        $data['_exp'] = time() + $ttl;
        file_put_contents(self::cacheFile($key), json_encode($data), LOCK_EX);
    }

    private static function deleteCache(string $key): void
    {
        @unlink(self::cacheFile($key));
    }
}
