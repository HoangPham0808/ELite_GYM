<?php

class Controller
{
    protected function view(string $path, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $conn = Database::getInstance();
        $file = APP_PATH . '/views/' . str_replace('.', '/', $path) . '.php';
        if (!is_file($file)) {
            http_response_code(404);
            echo "View not found: {$path}";
            return;
        }
        require $file;
    }

    protected function redirect(string $path): void
    {
        $url = (str_starts_with($path, 'http') || str_starts_with($path, '/'))
            ? $path
            : url($path);
        header('Location: ' . $url);
        exit;
    }

    /** Load file xử lý API (tránh trùng tên với action route `api()`). */
    protected function runApi(string $apiFile): void
    {
        $file = APP_PATH . '/api/' . ltrim($apiFile, '/');
        if (!is_file($file)) {
            jsonResponse(['success' => false, 'message' => 'API not found'], 404);
        }
        $conn = Database::getInstance();
        require $file;
    }

    /**
     * Chuẩn bị cho API JSON response:
     * - Tắt Xdebug HTML output (chỉ log, không hiển thị)
     * - Bật output buffering để bắt mọi warning/notice lẫn vào
     */
    protected function prepareJson(): void
    {
        // Tắt Xdebug HTML output — chỉ áp dụng nếu Xdebug đang bật
        if (function_exists('xdebug_disable')) {
            xdebug_disable();
        }
        if (function_exists('ini_set')) {
            ini_set('xdebug.default_enable', '0');
            ini_set('html_errors', '0');
        }

        // Bắt đầu output buffer — bắt mọi warning/notice PHP
        // Gọi cleanJson() trước khi echo json để loại bỏ chúng
        if (!ob_get_level()) {
            ob_start();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    /**
     * Xóa output buffer (warning/notice) rồi echo JSON sạch
     */
    protected function jsonOut(array $data, int $code = 200): void
    {
        // Dọn buffer — bỏ mọi HTML warning/notice đã bị bắt
        if (ob_get_level()) {
            ob_end_clean();
        }

        if ($code !== 200) http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . ($path !== '' ? '/' . $path : '');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}
