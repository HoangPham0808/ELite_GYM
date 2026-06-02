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
