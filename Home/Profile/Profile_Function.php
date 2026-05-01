<?php
ob_start();
session_start();

if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header("Location: ../../Internal/Index/Login/Login.php");
    exit;
}

require_once '../../Database/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../../PHPMailer/Exception.php';
require_once '../../PHPMailer/PHPMailer.php';
require_once '../../PHPMailer/SMTP.php';

define('PF_MAIL_HOST',      'smtp.gmail.com');
define('PF_MAIL_PORT',      587);
define('PF_MAIL_USERNAME',  'pvhoang08082004@gmail.com');
define('PF_MAIL_PASSWORD',  'ijub kmxb scip bggq');
define('PF_MAIL_FROM',      'pvhoang08082004@gmail.com');
define('PF_MAIL_FROM_NAME', 'Elite Fitness Gym');

$account_id = (int)$_SESSION['account_id'];

$r = $conn->prepare("SELECT customer_id, email FROM Customer WHERE account_id = ? LIMIT 1");
$r->bind_param("i", $account_id);
$r->execute();
$cus = $r->get_result()->fetch_assoc();
$r->close();

if (!$cus) { header("Location: Profile.php?error=not_found"); exit; }
$cid = (int)$cus['customer_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: Profile.php"); exit; }

$action = trim($_POST['action'] ?? '');

// ════════════════════════════════════════
// 1. UPDATE PERSONAL INFO
// ════════════════════════════════════════
if ($action === 'update_info') {
    $full_name = trim($_POST['full_name']     ?? '');
    $dob       = trim($_POST['date_of_birth'] ?? '');
    $gender    = trim($_POST['gender']        ?? '');
    $phone     = trim($_POST['phone']         ?? '');
    $email     = trim($_POST['email']         ?? '');
    $address   = trim($_POST['address']       ?? '');

    if (empty($full_name)) { header("Location: Profile.php?tab=info&error=empty_name"); exit; }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: Profile.php?tab=info&error=invalid_email"); exit;
    }

    $stmt = $conn->prepare("
        UPDATE Customer SET full_name=?, date_of_birth=?, gender=?, phone=?, email=?, address=?
        WHERE customer_id=?
    ");
    $dob_v  = $dob    ?: null;
    $gen_v  = $gender ?: null;
    $pho_v  = $phone  ?: null;
    $ema_v  = $email  ?: null;
    $adr_v  = $address?: null;
    $stmt->bind_param("ssssssi", $full_name, $dob_v, $gen_v, $pho_v, $ema_v, $adr_v, $cid);

    if ($stmt->execute()) {
        $_SESSION['full_name'] = $full_name;
        $stmt->close();
        header("Location: Profile.php?tab=info&success=info_updated");
    } else {
        $stmt->close();
        header("Location: Profile.php?tab=info&error=db_error");
    }
    exit;
}

// ════════════════════════════════════════
// 2. SEND OTP FOR PASSWORD CHANGE
// ════════════════════════════════════════
if ($action === 'send_change_pw_otp') {
    $old_pw  = $_POST['old_password']     ?? '';
    $new_pw  = $_POST['new_password']     ?? '';
    $conf_pw = $_POST['confirm_password'] ?? '';

    if (empty($old_pw) || empty($new_pw) || empty($conf_pw)) {
        header("Location: Profile.php?tab=info&pw_step=1&error=empty_fields"); exit;
    }
    if (strlen($new_pw) < 6) {
        header("Location: Profile.php?tab=info&pw_step=1&error=weak_password"); exit;
    }
    if ($new_pw !== $conf_pw) {
        header("Location: Profile.php?tab=info&pw_step=1&error=password_mismatch"); exit;
    }

    // Verify old password
    $stmt = $conn->prepare("SELECT password FROM Account WHERE account_id=? LIMIT 1");
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $acc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$acc) { header("Location: Profile.php?tab=info&pw_step=1&error=db_error"); exit; }

    $pw_ok = str_starts_with($acc['password'], '$')
        ? password_verify($old_pw, $acc['password'])
        : hash_equals($acc['password'], $old_pw);

    if (!$pw_ok) {
        header("Location: Profile.php?tab=info&pw_step=1&error=wrong_old_password"); exit;
    }

    $email_to = $cus['email'] ?? '';
    if (empty($email_to)) {
        header("Location: Profile.php?tab=info&pw_step=1&error=no_email"); exit;
    }

    $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = time() + 300;

    $_SESSION['chpw_otp']          = $otp;
    $_SESSION['chpw_otp_expiry']   = $expiry;
    $_SESSION['chpw_account_id']   = $account_id;
    $_SESSION['chpw_new_password'] = $new_pw;
    $_SESSION['chpw_email']        = $email_to;

    if (!sendChangePasswordOtp($email_to, $otp)) {
        header("Location: Profile.php?tab=info&pw_step=1&error=send_fail"); exit;
    }

    header("Location: Profile.php?tab=info&pw_step=2&success=otp_sent");
    exit;
}

// ════════════════════════════════════════
// 3. RESEND OTP
// ════════════════════════════════════════
if ($action === 'resend_change_pw_otp') {
    if (empty($_SESSION['chpw_account_id']) || empty($_SESSION['chpw_email'])) {
        header("Location: Profile.php?tab=info"); exit;
    }

    $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = time() + 300;
    $_SESSION['chpw_otp']        = $otp;
    $_SESSION['chpw_otp_expiry'] = $expiry;

    if (!sendChangePasswordOtp($_SESSION['chpw_email'], $otp)) {
        header("Location: Profile.php?tab=info&pw_step=2&error=send_fail"); exit;
    }
    header("Location: Profile.php?tab=info&pw_step=2&success=otp_sent");
    exit;
}

// ════════════════════════════════════════
// 4. VERIFY OTP & SAVE PASSWORD
// ════════════════════════════════════════
if ($action === 'verify_change_pw_otp') {
    if (empty($_SESSION['chpw_account_id'])) {
        header("Location: Profile.php?tab=info"); exit;
    }

    $otp_input = trim($_POST['otp_full'] ?? '');

    if (strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
        header("Location: Profile.php?tab=info&pw_step=2&error=invalid_otp"); exit;
    }
    if (time() > ($_SESSION['chpw_otp_expiry'] ?? 0)) {
        header("Location: Profile.php?tab=info&pw_step=2&error=otp_expired"); exit;
    }
    if (!hash_equals($_SESSION['chpw_otp'], $otp_input)) {
        header("Location: Profile.php?tab=info&pw_step=2&error=invalid_otp"); exit;
    }

    $new_pw = $_SESSION['chpw_new_password'];
    $aid    = (int)$_SESSION['chpw_account_id'];

    $stmt = $conn->prepare("UPDATE Account SET password=? WHERE account_id=?");
    $stmt->bind_param("si", $new_pw, $aid);
    $ok = $stmt->execute();
    $stmt->close();

    unset(
        $_SESSION['chpw_otp'], $_SESSION['chpw_otp_expiry'],
        $_SESSION['chpw_account_id'], $_SESSION['chpw_new_password'], $_SESSION['chpw_email']
    );

    header($ok
        ? "Location: Profile.php?tab=info&success=password_changed"
        : "Location: Profile.php?tab=info&error=db_error"
    );
    exit;
}

header("Location: Profile.php");
exit;

// ════════════════════════════════════════
// HELPER
// ════════════════════════════════════════
function sendChangePasswordOtp(string $toEmail, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = PF_MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = PF_MAIL_USERNAME;
        $mail->Password   = PF_MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = PF_MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(PF_MAIL_FROM, PF_MAIL_FROM_NAME);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = '[Elite Fitness] - Mã OTP Đổi Mật Khẩu';
        $mail->Body = "
        <html>
        <body style='font-family:Arial,sans-serif;background:#0a0a0a;color:#fff;padding:30px;margin:0;'>
          <div style='max-width:480px;margin:auto;background:#111111;border-radius:4px;padding:0;overflow:hidden;border:1px solid rgba(204,0,0,.3);'>
            <!-- Header -->
            <div style='background:#cc0000;padding:20px 32px;'>
              <h2 style='color:#fff;font-size:1.6rem;margin:0;font-weight:900;letter-spacing:2px;text-transform:uppercase;'>ELITE GYM</h2>
              <p style='color:rgba(255,255,255,.75);margin:4px 0 0;font-size:.85rem;letter-spacing:1px;text-transform:uppercase;'>Xác nhận đổi mật khẩu</p>
            </div>
            <!-- Body -->
            <div style='padding:32px;'>
              <p style='color:rgba(255,255,255,.85);margin:0 0 24px;line-height:1.6;'>Chúng tôi nhận được yêu cầu đổi mật khẩu từ tài khoản của bạn. Sử dụng mã OTP bên dưới để xác nhận.</p>
              <div style='text-align:center;margin:28px 0;'>
                <div style='display:inline-block;background:rgba(204,0,0,.1);border:2px solid #cc0000;border-radius:4px;padding:18px 44px;'>
                  <span style='font-size:2.6rem;font-weight:900;letter-spacing:14px;color:#fff;font-family:monospace;'>{$otp}</span>
                </div>
              </div>
              <div style='background:rgba(204,0,0,.08);border-left:3px solid #cc0000;border-radius:2px;padding:12px 16px;margin-bottom:24px;'>
                <p style='color:#ff6666;font-weight:700;margin:0;font-size:.875rem;'>⏱ Mã có hiệu lực trong <strong>5 phút</strong>. Không chia sẻ mã này cho bất kỳ ai.</p>
              </div>
              <hr style='border:none;border-top:1px solid rgba(255,255,255,.08);margin:0 0 20px;'>
              <p style='color:rgba(255,255,255,.35);font-size:.78rem;margin:0;'>
                Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email này.<br>
                © 2026 Elite Fitness Gym. All rights reserved.
              </p>
            </div>
          </div>
        </body></html>";
        $mail->AltBody = "Mã OTP đổi mật khẩu: {$otp} (hiệu lực 5 phút)";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer (change pw): " . $mail->ErrorInfo);
        return false;
    }
}
?>
