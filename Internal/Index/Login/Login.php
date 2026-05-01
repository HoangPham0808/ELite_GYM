<?php
ob_start();
session_start();
if (isset($_SESSION['account_id'])) {
    $role             = $_SESSION['role']     ?? '';
    $position_session = $_SESSION['position'] ?? '';

    // Guard: nếu role không hợp lệ → xóa session, hiển thị login thay vì redirect loop
    if (empty($role)) {
        session_unset();
        session_destroy();
    } else {
        if ($role === 'Customer') {
            header("Location: ../../../Home/index.php");
        } elseif ($role === 'Admin') {
            header("Location: ../../Admin/adm.php");
        } elseif ($role === 'Employee') {
            if ($position_session === 'Receptionist') {
                header("Location: ../../Staff/Receptionist/Receptionist.php");
            } elseif ($position_session === 'Personal Trainer') {
                header("Location: ../../Staff/HLV/HLV.php");
            } else {
                header("Location: ../../Admin/adm.php");
            }
        } else {
            // Role không xác định → xóa session, hiển thị login
            session_unset();
            session_destroy();
        }
        exit;
    }
}

// ── Lấy logo từ DB ───────────────────────────────────────────
require_once __DIR__ . '/../../../Database/db.php';
$logo_row = isset($conn) ? $conn->query("
    SELECT file_url FROM landing_images
    WHERE image_name = 'Logo_ELITY'
    LIMIT 1
")->fetch_assoc() : null;
$logo_url = $logo_row ? htmlspecialchars($logo_row['file_url']) : '';

$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_credentials':
            $error_message = 'Tên đăng nhập hoặc mật khẩu không chính xác!';
            break;
        case 'empty_fields':
            $error_message = 'Vui lòng nhập tên đăng nhập và mật khẩu!';
            break;
        case 'account_disabled':
            $error_message = 'Tài khoản đã bị vô hiệu hóa!';
            break;
        case 'database_error':
            $error_message = 'Lỗi hệ thống, vui lòng thử lại sau!';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Hệ Thống Quản Lý Phòng Tập Gym</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Login.css">
</head>
<body>
    <div class="particles-container" id="particles"></div>
    <div class="gradient-orbs">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <div class="login-container">
        <div class="login-box">
            <div class="corner-decoration corner-tl"></div>
            <div class="corner-decoration corner-tr"></div>
            <div class="corner-decoration corner-bl"></div>
            <div class="corner-decoration corner-br"></div>

            <div class="login-header">
                <div class="logo-container">
                    <div class="logo-icon">
                        <?php if ($logo_url): ?>
                            <img src="<?= $logo_url ?>" alt="Elite Gym Logo">
                        <?php else: ?>
                            <img src="../../../Home/ELITY.png" alt="Elite Gym Logo">
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <span class="alert-icon">⚠</span>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="Login_Function.php" class="login-form">
                <div class="form-group">
                    <label for="username">USERNAME</label>
                    <div class="input-wrapper">
                        <div class="input-glow"></div>
                        <input type="text" id="username" name="username"
                               placeholder="Enter your username" required autocomplete="username">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/>
                                <path d="M6 21C6 17.6863 8.68629 15 12 15C15.3137 15 18 17.6863 18 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </div>
                    <span class="error-message" id="usernameError"></span>
                </div>

                <div class="form-group">
                    <label for="password">PASSWORD</label>
                    <div class="input-wrapper">
                        <div class="input-glow"></div>
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" required autocomplete="current-password">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
                                <path d="M12 15V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </span>
                        <button type="button" class="toggle-password" id="togglePassword">
                            <svg class="eye-open" viewBox="0 0 24 24" fill="none">
                                <path d="M12 5C7 5 2.73 8.11 1 12.5C2.73 16.89 7 20 12 20C17 20 21.27 16.89 23 12.5C21.27 8.11 17 5 12 5Z" stroke="currentColor" stroke-width="2"/>
                                <circle cx="12" cy="12.5" r="3" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" fill="none">
                                <path d="M3 3L21 21M10.5 10.7C9.91 11.3 9.5 12.1 9.5 13C9.5 14.4 10.6 15.5 12 15.5C12.9 15.5 13.7 15.1 14.3 14.5M7.4 7.5C5.1 8.8 3.2 10.9 2 13.5C3.7 17.9 7.8 21 12 21C14.1 21 16 20.3 17.6 19.2M19.8 16.3C21.3 14.8 22.4 12.9 23 11C21.3 6.6 17.2 3.5 13 3.5C11.5 3.5 10.1 3.8 8.8 4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    <span class="error-message" id="passwordError"></span>
                </div>

                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" name="remember_me" id="rememberMe">
                        <span class="checkmark"></span>
                        <span class="checkbox-label">Remember me</span>
                    </label>
                    <a href="../Forgot_Password/Forgot_Password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login">
                    <span class="btn-content">
                        <span class="btn-text">LOGIN</span>
                        <svg class="btn-arrow" viewBox="0 0 24 24" fill="none">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div class="btn-shine"></div>
                </button>
            </form>

            <div class="divider"><span>or</span></div>
            <div class="login-footer">
                <p>Don't have an account? <a href="../Register/Register.php" class="register-link">Sign up now</a></p>

            </div>
        </div>
    </div>

    <script src="Login.js"></script>
</body>
</html>
