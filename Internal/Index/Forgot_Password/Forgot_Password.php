<?php
session_start();

// ── Lấy logo từ DB ───────────────────────────────────────────
require_once __DIR__ . '/../../../Database/db.php';
$logo_row = isset($conn) ? $conn->query("
    SELECT file_url FROM landing_images
    WHERE image_name = 'Logo_ELITY'
    LIMIT 1
")->fetch_assoc() : null;
$logo_url = $logo_row ? htmlspecialchars($logo_row['file_url']) : '';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Only allow access to step 2 if OTP session exists
if ($step === 2 && empty($_SESSION['otp_account_id'])) {
    $step = 1;
}

// Only allow access to step 3 if OTP is verified
if ($step === 3 && empty($_SESSION['otp_verified'])) {
    $step = 1;
}

// Display notification
$message = '';
$message_type = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'user_not_found':
            $message = 'Tài khoản không tồn tại!';
            $message_type = 'danger';
            break;
        case 'invalid_email':
            $message = 'Email không hợp lệ!';
            $message_type = 'danger';
            break;
        case 'email_not_match':
            $message = 'Tên đăng nhập và email không khớp!';
            $message_type = 'danger';
            break;
        case 'empty_fields':
            $message = 'Vui lòng nhập đầy đủ thông tin!';
            $message_type = 'danger';
            break;
        case 'invalid_otp':
            $message = 'Mã OTP không đúng hoặc đã hết hạn!';
            $message_type = 'danger';
            break;
        case 'otp_expired':
            $message = 'Mã OTP đã hết hạn! Vui lòng gửi lại yêu cầu.';
            $message_type = 'danger';
            break;
        case 'password_mismatch':
            $message = 'Mật khẩu nhập lại không khớp!';
            $message_type = 'danger';
            break;
        case 'weak_password':
            $message = 'Mật khẩu tối thiểu 6 ký tự!';
            $message_type = 'danger';
            break;
        case 'database_error':
            $message = 'Lỗi hệ thống! Vui lòng thử lại sau.';
            $message_type = 'danger';
            break;
        case 'send_fail':
            $message = 'Không thể gửi email! Vui lòng thử lại.';
            $message_type = 'danger';
            break;
    }
}

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'otp_sent':
            $message = 'Mã OTP đã được gửi về email của bạn! Vui lòng kiểm tra hộp thư.';
            $message_type = 'success';
            break;
        case 'otp_verified':
            $message = 'Xác thực thành công! Hãy đặt mật khẩu mới.';
            $message_type = 'success';
            break;
        case 'password_reset':
            $message = 'Mật khẩu đã được đặt lại thành công! Bạn có thể đăng nhập ngay.';
            $message_type = 'success';
            break;
    }
}

// Masked email for display at step 2
$masked_email = '';
if ($step === 2 && !empty($_SESSION['otp_email'])) {
    $email = $_SESSION['otp_email'];
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1] ?? '';
    $masked = substr($name, 0, 2) . str_repeat('*', max(strlen($name) - 2, 3));
    $masked_email = $masked . '@' . $domain;
}

// Title for each step
$titles = [
    1 => 'QUÊN MẬT KHẨU',
    2 => 'NHẬP MÃ OTP',
    3 => 'ĐẶT LẠI MẬT KHẨU',
];
$page_title = $titles[$step] ?? 'QUÊN MẬT KHẨU';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Elite Fitness Gym</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="Forgot_Password.css">
</head>
<body>
    <div class="particles-container" id="particles"></div>
    <div class="gradient-orbs">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <div class="forgot-container">
        <div class="forgot-box">
            <div class="forgot-header">
                <div class="logo-container">
                    <div class="logo-icon">
                        <?php if ($logo_url): ?>
                            <img src="<?= $logo_url ?>" alt="Elite Gym Logo">
                        <?php else: ?>
                            <img src="../../../Home/ELITY.png" alt="Elite Gym Logo">
                        <?php endif; ?>
                    </div>
                </div>
                <h1 class="glitch" data-text="<?php echo $page_title; ?>"><?php echo $page_title; ?></h1>
                <p class="subtitle">ELITE FITNESS GYM SECURITY</p>
                <div class="header-line"></div>
            </div>

            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step-dot <?php echo $step >= 1 ? ($step > 1 ? 'done' : 'active') : ''; ?>"></div>
                <div class="step-dot <?php echo $step >= 2 ? ($step > 2 ? 'done' : 'active') : ''; ?>"></div>
                <div class="step-dot <?php echo $step >= 3 ? 'active' : ''; ?>"></div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <span class="alert-icon"><?php echo $message_type === 'success' ? '✓' : '⚠'; ?></span>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <!-- ===== STEP 1: Enter username + email ===== -->
            <form id="forgotPasswordForm" method="POST" action="Forgot_Password_Function.php" class="forgot-form">
                <input type="hidden" name="action" value="send_otp">

                <p class="form-description">
                    Nhập tên đăng nhập và email đã đăng ký để nhận mã OTP xác thực.
                </p>

                <div class="form-group">
                    <label for="username">TÊN ĐĂNG NHẬP</label>
                    <div class="input-wrapper">
                        <div class="input-glow"></div>
                        <input type="text" id="username" name="username"
                            placeholder="Nhập tên đăng nhập" required>
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/>
                                <path d="M6 21C6 17.6863 8.68629 15 12 15C15.3137 15 18 17.6863 18 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </div>
                    <span class="error-message" id="usernameError"></span>
                </div>

                <div class="form-group">
                    <label for="email">EMAIL</label>
                    <div class="input-wrapper">
                        <div class="input-glow"></div>
                        <input type="email" id="email" name="email"
                            placeholder="email@example.com" required>
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                <path d="M3 7L12 13L21 7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </div>
                    <span class="error-message" id="emailError"></span>
                </div>

                <button type="submit" class="btn-submit">
                    <span class="btn-content">
                        <span class="btn-text">GỬI MÃ OTP</span>
                        <svg class="btn-arrow" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div class="btn-shine"></div>
                </button>
            </form>

            <?php elseif ($step === 2): ?>
            <!-- ===== BƯỚC 2: Nhập OTP ===== -->
            <form id="otpForm" method="POST" action="Forgot_Password_Function.php" class="forgot-form">
                <input type="hidden" name="action" value="verify_otp">
                <input type="hidden" name="otp_full" id="otp_full">

                <p class="otp-masked-email">
                    Mã OTP đã được gửi tới <strong><?php echo htmlspecialchars($masked_email); ?></strong>
                </p>

                <div class="otp-wrapper">
                    <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" id="otp1">
                    <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" id="otp2">
                    <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" id="otp3">
                    <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" id="otp4">
                    <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" id="otp5">
                    <input type="text" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" id="otp6">
                </div>

                <div class="otp-countdown">
                    Mã hết hạn sau: <span id="countdown">05:00</span>
                </div>

                <div style="text-align:center;">
                    <button type="button" class="resend-btn" id="resendBtn" onclick="document.getElementById('resendForm').submit()">Gửi lại mã OTP</button>
                </div>

                <button type="submit" class="btn-submit" id="verifyBtn">
                    <span class="btn-content">
                        <span class="btn-text">XÁC NHẬN OTP</span>
                        <svg class="btn-arrow" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div class="btn-shine"></div>
                </button>
            </form>

            <!-- Form gửi lại OTP đặt NGOÀI otpForm -->
            <form method="POST" action="Forgot_Password_Function.php" id="resendForm" style="display:none;">
                <input type="hidden" name="action" value="resend_otp">
            </form>

            <?php elseif ($step === 3): ?>
            <!-- ===== STEP 3: Set new password ===== -->
            <form id="resetPasswordForm" method="POST" action="Forgot_Password_Function.php" class="forgot-form">
                <input type="hidden" name="action" value="reset_password">

                <p class="form-description">Tạo mật khẩu mới cho tài khoản của bạn.</p>

                <div class="form-group">
                    <label for="new_password">MẬT KHẨU MỚI</label>
                    <div class="input-wrapper">
                        <div class="input-glow"></div>
                        <input type="password" id="new_password" name="new_password"
                            placeholder="Tối thiểu 6 ký tự" required>
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
                                <path d="M12 15V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </span>
                        <button type="button" class="toggle-password" id="togglePassword1">
                            <svg class="eye-open" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 5C7 5 2.73 8.11 1 12.5C2.73 16.89 7 20 12 20C17 20 21.27 16.89 23 12.5C21.27 8.11 17 5 12 5Z" stroke="currentColor" stroke-width="2"/>
                                <circle cx="12" cy="12.5" r="3" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
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

                <div class="form-group">
                    <label for="confirm_password">XÁC NHẬN MẬT KHẨU</label>
                    <div class="input-wrapper">
                        <div class="input-glow"></div>
                        <input type="password" id="confirm_password" name="confirm_password"
                            placeholder="Nhập lại mật khẩu" required>
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="5" y="11" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
                                <path d="M12 15V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11" stroke="currentColor" stroke-width="2"/>
                            </svg>
                        </span>
                        <button type="button" class="toggle-password" id="togglePassword2">
                            <svg class="eye-open" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 5C7 5 2.73 8.11 1 12.5C2.73 16.89 7 20 12 20C17 20 21.27 16.89 23 12.5C21.27 8.11 17 5 12 5Z" stroke="currentColor" stroke-width="2"/>
                                <circle cx="12" cy="12.5" r="3" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <svg class="eye-closed" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M3 3L21 21M10.5 10.7C9.91 11.3 9.5 12.1 9.5 13C9.5 14.4 10.6 15.5 12 15.5C12.9 15.5 13.7 15.1 14.3 14.5M7.4 7.5C5.1 8.8 3.2 10.9 2 13.5C3.7 17.9 7.8 21 12 21C14.1 21 16 20.3 17.6 19.2M19.8 16.3C21.3 14.8 22.4 12.9 23 11C21.3 6.6 17.2 3.5 13 3.5C11.5 3.5 10.1 3.8 8.8 4.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                    <span class="error-message" id="confirmPasswordError"></span>
                </div>

                <button type="submit" class="btn-submit">
                    <span class="btn-content">
                        <span class="btn-text">ĐẶT LẠI MẬT KHẨU</span>
                        <svg class="btn-arrow" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div class="btn-shine"></div>
                </button>
            </form>
            <?php endif; ?>

            <div class="divider"><span>hoặc</span></div>

            <div class="forgot-footer">
                <div class="footer-links">
                    <a href="../Login/Login.php" class="back-link">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19 12H5M5 12L12 19M5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Quay lại đăng nhập
                    </a>
                </div>
                
            </div>
        </div>
    </div>

    <script src="Forgot_Password.js"></script>
    <?php if ($step === 2): ?>
    <script>
    // ---- OTP input logic ----
    const inputs = document.querySelectorAll('.otp-input');
    const otpFull = document.getElementById('otp_full');
    const otpForm = document.getElementById('otpForm');

    inputs.forEach((input, i) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/[^0-9]/g, '');
            if (input.value && i < inputs.length - 1) inputs[i + 1].focus();
            input.classList.toggle('filled', input.value !== '');
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !input.value && i > 0) inputs[i - 1].focus();
        });
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const paste = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
            paste.split('').forEach((ch, j) => {
                if (inputs[j]) { inputs[j].value = ch; inputs[j].classList.add('filled'); }
            });
            if (inputs[Math.min(paste.length, 5)]) inputs[Math.min(paste.length, 5)].focus();
        });
    });

    otpForm.addEventListener('submit', function(e) {
        e.preventDefault();
        let otp = '';
        inputs.forEach(input => otp += input.value);
        otpFull.value = otp;

        if (otp.length !== 6 || !/^\d{6}$/.test(otp)) {
            alert('Vui lòng nhập đủ 6 chữ số OTP!');
            return;
        }

        this.submit();
    });

    // ---- Countdown timer (5 phút) ----
    let timeLeft = 300;
    const countdownEl = document.getElementById('countdown');
    const resendBtn = document.getElementById('resendBtn');

    const timer = setInterval(() => {
        timeLeft--;
        const m = String(Math.floor(timeLeft / 60)).padStart(2, '0');
        const s = String(timeLeft % 60).padStart(2, '0');
        countdownEl.textContent = m + ':' + s;
        if (timeLeft <= 0) {
            clearInterval(timer);
            countdownEl.textContent = '00:00';
            resendBtn.style.display = 'block';
        }
    }, 1000);

    inputs[0].focus();
    </script>
    <?php endif; ?>
</body>
</html>
