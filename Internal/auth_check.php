<?php
/**
 * auth_check.php — Kiểm tra session đăng nhập dùng chung
 * Đặt tại: DATN/Internal/auth_check.php
 *
 * Cách dùng trong mỗi trang PHP:
 *   require_once __DIR__ . '/../auth_check.php';
 *   requireRole('Admin');           // chỉ cho Admin
 *   requireRole('Employee');        // chỉ cho NhanVien
 *   requireRole(['Admin','NhanVien']); // cho nhiều role
 *   requireChucVu('Receptionist');  // kiểm tra thêm chức vụ
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Đường dẫn tới trang login (tính từ Internal/)
define('LOGIN_URL', '../Index/Login/Login.php');

/**
 * Kiểm tra đã đăng nhập chưa
 */
function isLoggedIn(): bool {
    return isset($_SESSION['account_id']) && !empty($_SESSION['account_id']);
}

/**
 * Bắt buộc đăng nhập + đúng vai trò
 * @param string|array $roles  Vai trò được phép, VD: 'Admin' hoặc ['Admin','NhanVien']
 */
function requireRole($roles): void {
    if (!isLoggedIn()) {
        header('Location: ' . LOGIN_URL);
        exit;
    }
    $roles = (array)$roles;
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        // Đã login nhưng sai vai trò → redirect về trang phù hợp
        redirectByRole();
        exit;
    }
}

/**
 * Bắt buộc đăng nhập + đúng chức vụ (dành cho NhanVien)
 * @param string $chucVu  VD: 'Receptionist' hoặc 'Personal Trainer'
 */
function requireChucVu(string $chucVu): void {
    requireRole('Employee');
    if (($_SESSION['position'] ?? '') !== $chucVu) {
        redirectByRole();
        exit;
    }
}

/**
 * Redirect về đúng trang theo vai trò hiện tại trong session
 */
function redirectByRole(): void {
    $vai_tro = $_SESSION['role'] ?? '';
    $chuc_vu = $_SESSION['position'] ?? '';

    switch ($vai_tro) {
        case 'Customer':
            header('Location: ../../User/index.html');
            break;
        case 'Admin':
            header('Location: ../Admin/adm.php');
            break;
        case 'Employee':
            if ($chuc_vu === 'Receptionist') {
                header('Location: ../Staff/Receptionist/Receptionist.php');
            } elseif ($chuc_vu === 'Personal Trainer') {
                header('Location: ../Staff/HLV/HLV.php');
            } else {
                header('Location: ../Admin/adm.php');
            }
            break;
        default:
            header('Location: ' . LOGIN_URL);
    }
    exit;
}
