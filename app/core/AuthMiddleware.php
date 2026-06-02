<?php

class AuthMiddleware
{
    public static function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains($uri, '/api/')) {
            return true;
        }
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            || isset($_GET['ajax'])
            || isset($_POST['ajax']);
    }

    public static function requireLogin(): void
    {
        if (!isLoggedIn()) {
            if (self::isApiRequest()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(401);
                echo json_encode(['ok' => false, 'success' => false, 'msg' => 'Chưa đăng nhập'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: ' . url('login'));
            exit;
        }
    }

    public static function requireRole($roles): void
    {
        self::requireLogin();
        $roles = (array)$roles;
        if (!in_array(currentRole(), $roles, true)) {
            self::redirectByRole();
        }
    }

    public static function requireChucVu(string $chucVu): void
    {
        self::requireRole('Employee');
        if (currentPosition() !== $chucVu) {
            self::redirectByRole();
        }
    }

    public static function redirectByRole(): void
    {
        $role = currentRole();
        $pos  = currentPosition();

        switch ($role) {
            case 'Customer':
                header('Location: ' . url());
                break;
            case 'Admin':
                header('Location: ' . url('admin'));
                break;
            case 'Employee':
                if ($pos === 'Receptionist') {
                    header('Location: ' . url('staff/receptionist'));
                } elseif ($pos === 'Personal Trainer') {
                    header('Location: ' . url('staff/hlv'));
                } else {
                    header('Location: ' . url('admin'));
                }
                break;
        default:
            header('Location: ' . url('login'));
    }
    exit;
}
}

/** Tương thích module cũ (auth_check.php) */
function requireRole($roles): void
{
    AuthMiddleware::requireRole($roles);
}

function requireChucVu(string $chucVu): void
{
    AuthMiddleware::requireChucVu($chucVu);
}
