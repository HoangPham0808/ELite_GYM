<?php
session_start();

require_once '../../../Database/db.php';

// PHPMailer - cài bằng: composer require phpmailer/phpmailer
// Hoặc tải thủ công: https://github.com/PHPMailer/PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../../PHPMailer/Exception.php';
require_once '../../../PHPMailer/PHPMailer.php';
require_once '../../../PHPMailer/SMTP.php';
// ============================================================
// EMAIL CONFIGURATION - MODIFY THESE VALUES
// ============================================================
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'pvhoang08082004@gmail.com');
define('MAIL_PASSWORD', 'ijub kmxb scip bggq');        // ← enter 16 character code here
define('MAIL_FROM',     'pvhoang08082004@gmail.com');
define('MAIL_FROM_NAME','Elite Fitness Gym');
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: Forgot_Password.php");
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

// ================================
// FUNCTION SEND EMAIL OTP
// ================================
function sendOtpEmail(string $toEmail, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = '[Elite Fitness] - Mã OTP Đặt Lại Mật Khẩu';
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; background:#111; color:#fff; padding:30px;'>
            <div style='max-width:480px; margin:auto; background:#1a1a1a; border-radius:12px; padding:32px; border:1px solid rgba(255,215,0,0.3);'>
                <div style='text-align:center; margin-bottom:24px;'>
                    <h2 style='color:#FFD700; font-size:1.5rem; margin:0;'>⚡ ELITE FITNESS GYM</h2>
                    <p style='color:rgba(255,255,255,0.6); margin:4px 0 0;'>Đặt lại mật khẩu</p>
                </div>
                <p style='color:rgba(255,255,255,0.85);'>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Sử dụng mã OTP bên dưới:</p>
                <div style='text-align:center; margin:28px 0;'>
                    <div style='display:inline-block; background:rgba(255,215,0,0.1); border:2px solid #FFD700; border-radius:12px; padding:16px 40px;'>
                        <span style='font-size:2.5rem; font-weight:900; letter-spacing:12px; color:#FFD700;'>{$otp}</span>
                    </div>
                </div>
                <p style='color:#FFD700; font-weight:bold; text-align:center;'>⏱ Mã có hiệu lực trong <strong>5 phút</strong>.</p>
                <hr style='border:none; border-top:1px solid rgba(255,255,255,0.1); margin:20px 0;'>
                <p style='color:rgba(255,255,255,0.4); font-size:0.8rem; text-align:center;'>
                    Nếu bạn không gửi yêu cầu này, hãy bỏ qua email này.<br>
                    © 2026 Elite Fitness Gym
                </p>
            </div>
        </body>
        </html>";
        $mail->AltBody = "Mã OTP của bạn là: {$otp} (có hiệu lực 5 phút)";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
// ================================
// STEP 1: SEND OTP
// ================================
if ($action === 'send_otp') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';

    if (empty($username) || empty($email)) {
        header("Location: Forgot_Password.php?error=empty_fields");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: Forgot_Password.php?error=invalid_email");
        exit;
    }

    try {
        // Tìm tài khoản KhachHang (join qua tai_khoan_id đã thêm ở bước trước)
        $stmt = $conn->prepare("
            SELECT a.account_id, c.email
            FROM Account a
            INNER JOIN Customer c ON c.account_id = a.account_id
            WHERE a.username = ?
              AND a.role = 'Customer'
              AND a.is_active = 1
        ");

        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            header("Location: Forgot_Password.php?error=user_not_found");
            exit;
        }

        $user = $result->fetch_assoc();
        $stmt->close();

        // Check if email matches
        if (strtolower($user['email']) !== strtolower($email)) {
            header("Location: Forgot_Password.php?error=email_not_match");
            exit;
        }

        // Create 6-digit OTP
        $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = time() + 300; // 5 minutes

        // Save to session (no need for additional DB table)
        $_SESSION['otp_code']        = $otp;
        $_SESSION['otp_expiry']      = $expiry;
        $_SESSION['otp_account_id']= $user['account_id'];
        $_SESSION['otp_email']       = $email;
        $_SESSION['otp_verified']    = false;

        // Gửi email
        if (!sendOtpEmail($email, $otp)) {
            header("Location: Forgot_Password.php?error=send_fail");
            exit;
        }

        header("Location: Forgot_Password.php?step=2&success=otp_sent");
        exit;

    } catch (Exception $e) {
        error_log("Send OTP error: " . $e->getMessage());
        header("Location: Forgot_Password.php?error=database_error");
        exit;
    }
}

// ================================
// STEP 1b: RESEND OTP
// ================================
if ($action === 'resend_otp') {
    if (empty($_SESSION['otp_account_id']) || empty($_SESSION['otp_email'])) {
        header("Location: Forgot_Password.php");
        exit;
    }

    $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = time() + 300;

    $_SESSION['otp_code']   = $otp;
    $_SESSION['otp_expiry'] = $expiry;

    if (!sendOtpEmail($_SESSION['otp_email'], $otp)) {
        header("Location: Forgot_Password.php?step=2&error=send_fail");
        exit;
    }

    header("Location: Forgot_Password.php?step=2&success=otp_sent");
    exit;
}

// ================================
// STEP 2: VERIFY OTP
// ================================
if ($action === 'verify_otp') {
    if (empty($_SESSION['otp_account_id'])) {
        header("Location: Forgot_Password.php");
        exit;
    }

    $otp_input = isset($_POST['otp_full']) ? trim($_POST['otp_full']) : '';

    if (strlen($otp_input) !== 6) {
        header("Location: Forgot_Password.php?step=2&error=invalid_otp");
        exit;
    }

    // Check if expired
    if (time() > $_SESSION['otp_expiry']) {
        header("Location: Forgot_Password.php?step=2&error=otp_expired");
        exit;
    }

    // Verify OTP (using hash_equals to prevent timing attacks)
    if (!hash_equals($_SESSION['otp_code'], $otp_input)) {
        header("Location: Forgot_Password.php?step=2&error=invalid_otp");
        exit;
    }

    // OTP valid - proceed to step 3
    $_SESSION['otp_verified'] = true;
    unset($_SESSION['otp_code']); // Delete OTP after verification

    header("Location: Forgot_Password.php?step=3&success=otp_verified");
    exit;
}

// ================================
// STEP 3: RESET PASSWORD
// ================================
if ($action === 'reset_password') {
    if (empty($_SESSION['otp_verified']) || empty($_SESSION['otp_account_id'])) {
        header("Location: Forgot_Password.php");
        exit;
    }

    $new_password     = isset($_POST['new_password'])      ? $_POST['new_password']      : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (empty($new_password) || empty($confirm_password)) {
        header("Location: Forgot_Password.php?step=3&error=empty_fields");
        exit;
    }

    if (strlen($new_password) < 6) {
        header("Location: Forgot_Password.php?step=3&error=weak_password");
        exit;
    }

    if ($new_password !== $confirm_password) {
        header("Location: Forgot_Password.php?step=3&error=password_mismatch");
        exit;
    }

    try {
        $account_id      = $_SESSION['otp_account_id'];

        $stmt = $conn->prepare("UPDATE Account SET password = ? WHERE account_id = ?");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

        $stmt->bind_param("si", $new_password, $account_id);
        $stmt->execute();
        $stmt->close();

        // Delete all OTP session data
        unset(
            $_SESSION['otp_code'],
            $_SESSION['otp_expiry'],
            $_SESSION['otp_account_id'],
            $_SESSION['otp_email'],
            $_SESSION['otp_verified']
        );

        header("Location: Forgot_Password.php?success=password_reset");
        exit;

    } catch (Exception $e) {
        error_log("Reset password error: " . $e->getMessage());
        header("Location: Forgot_Password.php?step=3&error=database_error");
        exit;
    }
}

// Action không hợp lệ
header("Location: Forgot_Password.php");
exit;
?>
