<?php

class App
{
    public static function boot(): void
    {
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 2));
        }
        if (!defined('APP_PATH')) {
            define('APP_PATH', ROOT_PATH . '/app');
        }

        require_once APP_PATH . '/config/config.php';
        require_once APP_PATH . '/core/Database.php';
        require_once APP_PATH . '/helpers/session_helper.php';
        require_once APP_PATH . '/helpers/format_helper.php';
        require_once APP_PATH . '/helpers/upload_helper.php';
        require_once APP_PATH . '/helpers/qr_helper.php';
        require_once APP_PATH . '/core/Model.php';
        require_once APP_PATH . '/core/Controller.php';
        require_once APP_PATH . '/core/AuthMiddleware.php';
        require_once APP_PATH . '/core/Router.php';

        ensureSession();
        ensureUploadDirs();

        spl_autoload_register(function (string $class) {
            $paths = [
                APP_PATH . '/controllers/' . $class . '.php',
                APP_PATH . '/models/' . $class . '.php',
            ];
            foreach ($paths as $file) {
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }

    public static function run(): void
    {
        self::boot();
        $router = require ROOT_PATH . '/routes/web.php';
        $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', self::resolveUri());
    }

    /**
     * Lấy path tương đối so với thư mục public (bỏ /PHP/ELite_GYM/public).
     */
    private static function resolveUri(): string
    {
        if (isset($_GET['url']) && $_GET['url'] !== '') {
            $uri = '/' . trim((string) $_GET['url'], '/');
            return $uri === '/' ? '/' : $uri;
        }


        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Loại bỏ thư mục script (vd: /PHP/ELite_GYM/public) nếu có —
        // trước đây code so sánh với BASE_URL (có scheme+host) nên không khớp.
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $scriptDir = rtrim($scriptDir, '/');
        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir)) ?: '/';
        }

        if ($path === '/index.php' || str_ends_with($path, '/index.php')) {
            $path = preg_replace('#/index\.php$#', '', $path) ?: '/';
        }

        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
