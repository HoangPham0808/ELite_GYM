<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (isLoggedIn()) {
            AuthMiddleware::redirectByRole();
        }
        $this->view('auth/login');
    }

    public function login(): void
    {
        ob_start();
        if (isset($_POST['remember_me'])) {
            $lifetime = 30 * 24 * 60 * 60;
            ini_set('session.cookie_lifetime', $lifetime);
            ini_set('session.gc_maxlifetime',  $lifetime);
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => '/',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        ensureSession();

        if (isset($_SESSION['account_id'])) {
            AuthMiddleware::redirectByRole();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('login');
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $this->redirect('login?error=empty_fields');
        }

        if (strlen($username) < 3 || strlen($password) < 6) {
            $this->redirect('login?error=invalid_credentials');
        }

        $accountModel = new Account();
        $user = $accountModel->findByUsername($username);

        if (!$user) {
            $this->redirect('login?error=invalid_credentials');
        }

        if (!(bool)$user['is_active']) {
            $this->redirect('login?error=account_disabled');
        }

        if (!in_array($user['role'], ['Admin', 'Employee', 'Customer'], true)) {
            $this->redirect('login?error=invalid_credentials');
        }

        $password_valid = str_starts_with($user['password'], '$')
            ? password_verify($password, $user['password'])
            : hash_equals($user['password'], $password);

        if (!$password_valid) {
            $this->redirect('login?error=invalid_credentials');
        }

        $full_name = $user['username'];
        $position = null;

        if ($user['role'] === 'Employee') {
            $employeeModel = new Employee();
            $emp = $employeeModel->getByAccountId($user['account_id']);
            if ($emp) {
                $full_name = $emp['full_name'];
                $position  = $emp['position'];
            }
        } elseif ($user['role'] === 'Admin') {
            $employeeModel = new Employee();
            $emp = $employeeModel->getByAccountId($user['account_id']);
            if ($emp) {
                $full_name = $emp['full_name'];
            }
        } else {
            $customerModel = new Customer();
            $cus = $customerModel->getByAccountId($user['account_id']);
            if ($cus) {
                $full_name = $cus['full_name'];
            }
        }

        session_regenerate_id(false);

        $_SESSION['account_id'] = (int)$user['account_id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['full_name']  = $full_name;
        $_SESSION['position']   = $position;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $accountModel->logLogin((int)$user['account_id'], $ip, $ua, 'Success');

        AuthMiddleware::redirectByRole();
    }

    public function registerForm(): void
    {
        $this->view('auth/register');
    }

    public function register(): void
    {
        ensureSession();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('register');
        }

        $username         = trim($_POST['username'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $fullname         = trim($_POST['full_name'] ?? '');
        $phone            = trim($_POST['phone'] ?? '');
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $accept_terms     = isset($_POST['accept_terms']);

        if (empty($username) || empty($email) || empty($fullname) || empty($password) || empty($confirm_password)) {
            $this->redirect('register?error=empty_fields');
        }

        if (!$accept_terms) {
            $this->redirect('register?error=terms_not_accepted');
        }

        if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $this->redirect('register?error=invalid_username');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('register?error=invalid_email');
        }

        if (strlen($fullname) < 3 || strlen($fullname) > 150) {
            $this->redirect('register?error=invalid_fullname');
        }

        if (!empty($phone)) {
            if (!preg_match('/^(0|\+84)[0-9]{9,10}$/', str_replace(' ', '', $phone))) {
                $this->redirect('register?error=invalid_phone');
            }
        }

        if (strlen($password) < 6) {
            $this->redirect('register?error=weak_password');
        }

        if ($password !== $confirm_password) {
            $this->redirect('register?error=password_mismatch');
        }

        $accountModel = new Account();
        if ($accountModel->usernameExists($username)) {
            $this->redirect('register?error=username_exists');
        }

        $customerModel = new Customer();
        if ($customerModel->emailExists($email)) {
            $this->redirect('register?error=email_exists');
        }

        $db = Database::getInstance();
        $db->begin_transaction();
        try {
            $accountId = $accountModel->createAccount($username, $password, 'Customer', 1);
            if (!$accountId) {
                throw new Exception("Create account failed");
            }
            $registeredAt = date('Y-m-d');
            $customerId = $customerModel->createCustomer($fullname, $email, $phone, $registeredAt, $accountId);
            if (!$customerId) {
                throw new Exception("Create customer failed");
            }
            $db->commit();
            $this->redirect('register?success=1');
        } catch (Exception $e) {
            $db->rollback();
            error_log("Registration error: " . $e->getMessage());
            $this->redirect('register?error=database_error');
        }
    }

    public function forgotForm(): void
    {
        $this->view('auth/forgot_password');
    }

    public function forgot(): void
    {
        ensureSession();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('forgot-password');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'send_otp') {
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');

            if (empty($username) || empty($email)) {
                $this->redirect('forgot-password?error=empty_fields');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->redirect('forgot-password?error=invalid_email');
            }

            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT a.account_id, c.email
                FROM Account a
                INNER JOIN Customer c ON c.account_id = a.account_id
                WHERE a.username = ?
                  AND a.role = 'Customer'
                  AND a.is_active = 1
            ");
            if (!$stmt) {
                $this->redirect('forgot-password?error=database_error');
            }
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                $stmt->close();
                $this->redirect('forgot-password?error=user_not_found');
            }
            $user = $res->fetch_assoc();
            $stmt->close();

            if (strtolower($user['email']) !== strtolower($email)) {
                $this->redirect('forgot-password?error=email_not_match');
            }

            $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = time() + 300;

            $_SESSION['otp_code']       = $otp;
            $_SESSION['otp_expiry']     = $expiry;
            $_SESSION['otp_account_id'] = $user['account_id'];
            $_SESSION['otp_email']      = $email;
            $_SESSION['otp_verified']   = false;

            if (!$this->sendOtpEmail($email, $otp)) {
                $this->redirect('forgot-password?error=send_fail');
            }

            $this->redirect('forgot-password?step=2&success=otp_sent');
        }

        if ($action === 'resend_otp') {
            if (empty($_SESSION['otp_account_id']) || empty($_SESSION['otp_email'])) {
                $this->redirect('forgot-password');
            }

            $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = time() + 300;

            $_SESSION['otp_code']   = $otp;
            $_SESSION['otp_expiry'] = $expiry;

            if (!$this->sendOtpEmail($_SESSION['otp_email'], $otp)) {
                $this->redirect('forgot-password?step=2&error=send_fail');
            }

            $this->redirect('forgot-password?step=2&success=otp_sent');
        }

        if ($action === 'verify_otp') {
            if (empty($_SESSION['otp_account_id'])) {
                $this->redirect('forgot-password');
            }

            $otp_input = trim($_POST['otp_full'] ?? '');

            if (strlen($otp_input) !== 6) {
                $this->redirect('forgot-password?step=2&error=invalid_otp');
            }

            if (time() > $_SESSION['otp_expiry']) {
                $this->redirect('forgot-password?step=2&error=otp_expired');
            }

            if (!hash_equals($_SESSION['otp_code'], $otp_input)) {
                $this->redirect('forgot-password?step=2&error=invalid_otp');
            }

            $_SESSION['otp_verified'] = true;
            unset($_SESSION['otp_code']);

            $this->redirect('forgot-password?step=3&success=otp_verified');
        }

        if ($action === 'reset_password') {
            if (empty($_SESSION['otp_verified']) || empty($_SESSION['otp_account_id'])) {
                $this->redirect('forgot-password');
            }

            $new_password     = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($new_password) || empty($confirm_password)) {
                $this->redirect('forgot-password?step=3&error=empty_fields');
            }

            if (strlen($new_password) < 6) {
                $this->redirect('forgot-password?step=3&error=weak_password');
            }

            if ($new_password !== $confirm_password) {
                $this->redirect('forgot-password?step=3&error=password_mismatch');
            }

            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE Account SET password = ? WHERE account_id = ?");
            if (!$stmt) {
                $this->redirect('forgot-password?step=3&error=database_error');
            }
            $stmt->bind_param("si", $new_password, $_SESSION['otp_account_id']);
            $stmt->execute();
            $stmt->close();

            unset(
                $_SESSION['otp_code'],
                $_SESSION['otp_expiry'],
                $_SESSION['otp_account_id'],
                $_SESSION['otp_email'],
                $_SESSION['otp_verified']
            );

            $this->redirect('forgot-password?success=password_reset');
        }

        $this->redirect('forgot-password');
    }

    private function sendOtpEmail(string $toEmail, string $otp): bool
    {
        require_once VENDOR_PATH . '/PHPMailer/Exception.php';
        require_once VENDOR_PATH . '/PHPMailer/PHPMailer.php';
        require_once VENDOR_PATH . '/PHPMailer/SMTP.php';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'pvhoang08082004@gmail.com';
            $mail->Password   = 'ijub kmxb scip bggq';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom('pvhoang08082004@gmail.com', 'Elite Fitness Gym');
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = '[Elite Fitness] - Mã OTP Đặt Lại Mật Khẩu';
            $mail->Body = "
            <html>
            <body style='font-family:Arial,sans-serif;background:#0a0a0a;color:#fff;padding:30px;margin:0;'>
                <div style='max-width:480px;margin:auto;background:#111111;border-radius:4px;overflow:hidden;border:1px solid rgba(204,0,0,.3);'>
                    <div style='background:#cc0000;padding:20px 32px;'>
                        <h2 style='color:#fff;font-size:1.6rem;margin:0;font-weight:900;letter-spacing:2px;text-transform:uppercase;'>ELITE GYM</h2>
                        <p style='color:rgba(255,255,255,.75);margin:4px 0 0;font-size:.85rem;letter-spacing:1px;text-transform:uppercase;'>Đặt lại mật khẩu</p>
                    </div>
                    <div style='padding:32px;'>
                        <p style='color:rgba(255,255,255,.85);margin:0 0 24px;line-height:1.6;'>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Sử dụng mã OTP bên dưới:</p>
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
                            Nếu bạn không gửi yêu cầu này, hãy bỏ qua email này.<br>
                            © 2026 Elite Fitness Gym. All rights reserved.
                        </p>
                    </div>
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

    public function logout(): void
    {
        ensureSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        $this->redirect('login');
    }
}
