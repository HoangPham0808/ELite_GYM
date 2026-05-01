<?php
session_start();

// Hiển thị thông báo lỗi hoặc thành công
$message = '';
$message_type = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'username_exists':
            $message = 'Tên đăng nhập đã tồn tại!';
            $message_type = 'danger';
            break;
        case 'email_exists':
            $message = 'Email đã được đăng ký!';
            $message_type = 'danger';
            break;
        case 'password_mismatch':
            $message = 'Mật khẩu nhập lại không khớp!';
            $message_type = 'danger';
            break;
        case 'invalid_email':
            $message = 'Email không hợp lệ!';
            $message_type = 'danger';
            break;
        case 'empty_fields':
            $message = 'Vui lòng nhập đầy đủ thông tin!';
            $message_type = 'danger';
            break;
        case 'terms_not_accepted':
            $message = 'Vui lòng chấp nhận điều khoản và điều kiện!';
            $message_type = 'danger';
            break;
        case 'weak_password':
            $message = 'Mật khẩu phải có ít nhất 6 ký tự!';
            $message_type = 'danger';
            break;
        case 'invalid_username':
            $message = 'Tên đăng nhập không hợp lệ (3-50 ký tự, chỉ chữ/số/gạch dưới)!';
            $message_type = 'danger';
            break;
        case 'invalid_fullname':
            $message = 'Họ tên không hợp lệ!';
            $message_type = 'danger';
            break;
        case 'invalid_phone':
            $message = 'Số điện thoại không hợp lệ!';
            $message_type = 'danger';
            break;
        case 'database_error':
            $message = 'Lỗi hệ thống, vui lòng thử lại sau!';
            $message_type = 'danger';
            break;
    }
}

if (isset($_GET['success'])) {
    $message = 'Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.';
    $message_type = 'success';
}

// Lấy logo từ DB
$logo_url = '';
try {
    require_once __DIR__ . '/../../../Database/db.php';
    if (isset($conn)) {
        $logo_row = $conn->query("
            SELECT file_url FROM landing_images
            WHERE image_name = 'Logo_ELITY'
            LIMIT 1
        ")->fetch_assoc();
        $logo_url = $logo_row ? htmlspecialchars($logo_row['file_url']) : '';
    }
} catch (Throwable $e) {
    // fallback gracefully
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký - Hệ Thống Quản Lý Phòng Tập Gym</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Register.css">
</head>
<body>
    <div class="particles-container" id="particles"></div>
    <div class="gradient-orbs">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <div class="register-container">
        <div class="register-box">
            <div class="corner-decoration corner-tl"></div>
            <div class="corner-decoration corner-tr"></div>
            <div class="corner-decoration corner-bl"></div>
            <div class="corner-decoration corner-br"></div>

            <!-- Header -->
            <div class="register-header">
                <div class="logo-container">
                    <div class="logo-icon">
                        <?php if ($logo_url): ?>
                            <img src="<?= $logo_url ?>" alt="Elite Gym Logo">
                        <?php else: ?>
                            <img src="../../../Home/ELITY.png" alt="Elite Gym Logo">
                        <?php endif; ?>
                    </div>
                </div>
                <p class="subtitle">TẠO TÀI KHOẢN MỚI</p>
                <div class="header-line"></div>
            </div>

            <!-- Alert -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <span class="alert-icon"><?php echo $message_type === 'success' ? '✓' : '⚠'; ?></span>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form id="registerForm" method="POST" action="Register_Function.php" class="register-form">
                <div class="form-grid">

                    <!-- Username -->
                    <div class="form-group">
                        <label for="username">TÊN ĐĂNG NHẬP</label>
                        <div class="input-wrapper">
                            <div class="input-glow"></div>
                            <input type="text" id="username" name="username"
                                   placeholder="3-50 ký tự" required autocomplete="username">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/>
                                    <path d="M6 21C6 17.6863 8.68629 15 12 15C15.3137 15 18 17.6863 18 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </span>
                        </div>
                        <span class="error-message" id="usernameError"></span>
                        <span class="help-text">Chỉ chữ, số và dấu gạch dưới</span>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label for="email">EMAIL</label>
                        <div class="input-wrapper">
                            <div class="input-glow"></div>
                            <input type="email" id="email" name="email"
                                   placeholder="email@example.com" required autocomplete="email">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <path d="M3 7L12 13L21 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </span>
                        </div>
                        <span class="error-message" id="emailError"></span>
                    </div>

                    <!-- Full Name -->
                    <div class="form-group">
                        <label for="fullname">HỌ VÀ TÊN</label>
                        <div class="input-wrapper">
                            <div class="input-glow"></div>
                            <input type="text" id="fullname" name="full_name"
                                   placeholder="Nhập họ và tên đầy đủ" required>
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M9 5H7C5.89543 5 5 5.89543 5 7V19C5 20.1046 5.89543 21 7 21H17C18.1046 21 19 20.1046 19 19V7C19 5.89543 18.1046 5 17 5H15" stroke="currentColor" stroke-width="2"/>
                                    <rect x="9" y="3" width="6" height="4" rx="1" stroke="currentColor" stroke-width="2"/>
                                    <path d="M9 12H15M9 16H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </span>
                        </div>
                        <span class="error-message" id="fullnameError"></span>
                    </div>

                    <!-- Phone -->
                    <div class="form-group">
                        <label for="phone">SỐ ĐIỆN THOẠI</label>
                        <div class="input-wrapper">
                            <div class="input-glow"></div>
                            <input type="tel" id="phone" name="phone"
                                   placeholder="0123456789 (không bắt buộc)" autocomplete="tel">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="5" y="2" width="14" height="20" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <path d="M12 18H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </span>
                        </div>
                        <span class="error-message" id="phoneError"></span>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password">MẬT KHẨU</label>
                        <div class="input-wrapper">
                            <div class="input-glow"></div>
                            <input type="password" id="password" name="password"
                                   placeholder="Tối thiểu 6 ký tự" required autocomplete="new-password">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <path d="M12 15V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            </span>
                            <button type="button" class="toggle-password" id="togglePassword1">
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
                        <div class="password-strength-container">
                            <div class="password-strength-bar" id="passwordStrength"></div>
                            <span class="strength-text" id="strengthText"></span>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label for="confirm_password">XÁC NHẬN MẬT KHẨU</label>
                        <div class="input-wrapper">
                            <div class="input-glow"></div>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   placeholder="Nhập lại mật khẩu" required autocomplete="new-password">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <path d="M12 15V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            </span>
                            <button type="button" class="toggle-password" id="togglePassword2">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 5C7 5 2.73 8.11 1 12.5C2.73 16.89 7 20 12 20C17 20 21.27 16.89 23 12.5C21.27 8.11 17 5 12 5Z" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="12" cy="12.5" r="3" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                <svg class="eye-closed" viewBox="0 0 24 24" fill="none">
                                    <path d="M3 3L21 21M10.5 10.7C9.91 11.3 9.5 12.1 9.5 13C9.5 14.4 10.6 15.5 12 15.5C12.9 15.5 13.7 15.1 14.3 14.5M7.4 7.5C5.1 8.8 3.2 10.9 2 13.5C3.7 17.9 7.8 21 12 21C14.1 21 16 20.3 17.6 19.2M19.8 16.3C21.3 14.8 22.4 12.9 23 11C21.3 6.6 17.2 3.5 13 3.5C11.5 3.5 10.1 3.8 8.8 4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                        <span class="error-message" id="confirmPasswordError"></span>
                    </div>

                    <!-- Terms -->
                    <div class="form-group terms-group">
                        <label class="checkbox-container">
                            <input type="checkbox" name="accept_terms" id="acceptTerms" required>
                            <span class="checkmark"></span>
                            <span class="checkbox-label">
                                Tôi đồng ý với
                                <a href="#" class="terms-link" onclick="event.preventDefault()">điều khoản và điều kiện</a>
                            </span>
                        </label>
                        <span class="error-message" id="termsError"></span>
                    </div>

                    <!-- Submit -->
                    <div class="btn-submit-wrapper">
                        <button type="submit" class="btn-register">
                            <span class="btn-content">
                                <span class="btn-text">ĐĂNG KÝ NGAY</span>
                                <svg class="btn-arrow" viewBox="0 0 24 24" fill="none">
                                    <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <div class="btn-shine"></div>
                        </button>
                    </div>

                </div><!-- /.form-grid -->
            </form>

            <div class="divider"><span>hoặc</span></div>
            <div class="register-footer">
                <p>Đã có tài khoản? <a href="../Login/Login.php" class="login-link">Đăng nhập ngay</a></p>
            </div>
        </div>
    </div>

    <script src="Register.js"></script>
</body>
</html>
