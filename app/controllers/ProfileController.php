<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once VENDOR_PATH . '/PHPMailer/Exception.php';
require_once VENDOR_PATH . '/PHPMailer/PHPMailer.php';
require_once VENDOR_PATH . '/PHPMailer/SMTP.php';

class ProfileController extends Controller
{
    private Account $accountModel;
    private Customer $customerModel;

    public function __construct()
    {
        $this->accountModel = new Account();
        $this->customerModel = new Customer();
    }

    // API dành cho Khách hàng (Customer) - Hỗ trợ cập nhật thông tin & đổi mật khẩu OTP qua form post đồng bộ
    public function api(): void
    {
        AuthMiddleware::requireRole('Customer');
        ensureSession();

        $account_id = (int)($_SESSION['account_id'] ?? 0);
        if (!$account_id) {
            header("Location: " . url("login"));
            exit;
        }

        $cid = $this->customerModel->findIdByAccount($account_id);
        if (!$cid) {
            header("Location: " . url('profile') . "?error=not_found");
            exit;
        }

        // Lấy thông tin email của customer
        $cusDetail = $this->customerModel->getCustomerDetail($cid);
        $email_to = $cusDetail['customer']['email'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: " . url('profile') . "");
            exit;
        }

        $action = trim($_POST['action'] ?? '');

        // 1. UPDATE PERSONAL INFO
        if ($action === 'update_info') {
            $full_name = trim($_POST['full_name']     ?? '');
            $dob       = trim($_POST['date_of_birth'] ?? '');
            $gender    = trim($_POST['gender']        ?? '');
            $phone     = trim($_POST['phone']         ?? '');
            $email     = trim($_POST['email']         ?? '');
            $address   = trim($_POST['address']       ?? '');

            if (empty($full_name)) {
                header("Location: " . url('profile') . "?tab=info&error=empty_name");
                exit;
            }
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                header("Location: " . url('profile') . "?tab=info&error=invalid_email");
                exit;
            }

            $dob_v  = $dob    ?: null;
            $gen_v  = $gender ?: null;
            $pho_v  = $phone  ?: null;
            $ema_v  = $email  ?: null;
            $adr_v  = $address?: null;

            $ok = $this->customerModel->updateCustomer($cid, $full_name, $dob_v, $gen_v, $pho_v, $ema_v, $adr_v);

            if ($ok) {
                $_SESSION['full_name'] = $full_name;
                header("Location: " . url('profile') . "?tab=info&success=info_updated");
            } else {
                header("Location: " . url('profile') . "?tab=info&error=db_error");
            }
            exit;
        }

        // 2. SEND OTP FOR PASSWORD CHANGE
        if ($action === 'send_change_pw_otp') {
            $old_pw  = $_POST['old_password']     ?? '';
            $new_pw  = $_POST['new_password']     ?? '';
            $conf_pw = $_POST['confirm_password'] ?? '';

            if (empty($old_pw) || empty($new_pw) || empty($conf_pw)) {
                header("Location: " . url('profile') . "?tab=info&pw_step=1&error=empty_fields");
                exit;
            }
            if (strlen($new_pw) < 6) {
                header("Location: " . url('profile') . "?tab=info&pw_step=1&error=weak_password");
                exit;
            }
            if ($new_pw !== $conf_pw) {
                header("Location: " . url('profile') . "?tab=info&pw_step=1&error=password_mismatch");
                exit;
            }

            // Xác thực mật khẩu cũ
            $accInfo = $this->accountModel->getAccountDetail($account_id);
            $acc = $accInfo['account'] ?? null;

            if (!$acc) {
                header("Location: " . url('profile') . "?tab=info&pw_step=1&error=db_error");
                exit;
            }

            $pw_ok = str_starts_with($acc['password'], '$')
                ? password_verify($old_pw, $acc['password'])
                : hash_equals($acc['password'], $old_pw);

            if (!$pw_ok) {
                header("Location: " . url('profile') . "?tab=info&pw_step=1&error=wrong_old_password");
                exit;
            }

            if (empty($email_to)) {
                header("Location: " . url('profile') . "?tab=info&pw_step=1&error=no_email");
                exit;
            }

            $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = time() + 300;

            $_SESSION['chpw_otp']          = $otp;
            $_SESSION['chpw_otp_expiry']   = $expiry;
            $_SESSION['chpw_account_id']   = $account_id;
            $_SESSION['chpw_new_password'] = $new_pw;
            $_SESSION['chpw_email']        = $email_to;

            if (!$this->sendChangePasswordOtp($email_to, $otp)) {
                header("Location: " . url('profile') . "?tab=info&pw_step=1&error=send_fail");
                exit;
            }

            header("Location: " . url('profile') . "?tab=info&pw_step=2&success=otp_sent");
            exit;
        }

        // 3. RESEND OTP
        if ($action === 'resend_change_pw_otp') {
            if (empty($_SESSION['chpw_account_id']) || empty($_SESSION['chpw_email'])) {
                header("Location: " . url('profile') . "?tab=info");
                exit;
            }

            $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = time() + 300;
            $_SESSION['chpw_otp']        = $otp;
            $_SESSION['chpw_otp_expiry'] = $expiry;

            if (!$this->sendChangePasswordOtp($_SESSION['chpw_email'], $otp)) {
                header("Location: " . url('profile') . "?tab=info&pw_step=2&error=send_fail");
                exit;
            }
            header("Location: " . url('profile') . "?tab=info&pw_step=2&success=otp_sent");
            exit;
        }

        // 4. VERIFY OTP & SAVE PASSWORD
        if ($action === 'verify_change_pw_otp') {
            if (empty($_SESSION['chpw_account_id'])) {
                header("Location: " . url('profile') . "?tab=info");
                exit;
            }

            $otp_input = trim($_POST['otp_full'] ?? '');

            if (strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
                header("Location: " . url('profile') . "?tab=info&pw_step=2&error=invalid_otp");
                exit;
            }
            if (time() > ($_SESSION['chpw_otp_expiry'] ?? 0)) {
                header("Location: " . url('profile') . "?tab=info&pw_step=2&error=otp_expired");
                exit;
            }
            if (!hash_equals($_SESSION['chpw_otp'], $otp_input)) {
                header("Location: " . url('profile') . "?tab=info&pw_step=2&error=invalid_otp");
                exit;
            }

            $new_pw = password_hash($_SESSION['chpw_new_password'], PASSWORD_DEFAULT);
            $aid    = (int)$_SESSION['chpw_account_id'];

            $ok = $this->accountModel->resetPassword($aid, $new_pw);

            unset(
                $_SESSION['chpw_otp'], $_SESSION['chpw_otp_expiry'],
                $_SESSION['chpw_account_id'], $_SESSION['chpw_new_password'], $_SESSION['chpw_email']
            );

            header("Location: " . url('profile') . ($ok
                ? "?tab=info&success=password_changed"
                : "?tab=info&error=db_error"
            ));
            exit;
        }

        header("Location: " . url('profile') . "");
        exit;
    }

    // API dành cho Nhân viên/Admin (Employee/Admin) - Cung cấp AJAX profile, GPS checkin và Payroll
    public function apiAdmin(): void
    {
        ob_start();
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        ensureSession();
        header('Content-Type: application/json; charset=utf-8');

        $acc_id = (int)($_SESSION['account_id'] ?? 0);
        if (!$acc_id) {
            ob_end_clean();
            echo json_encode(['ok' => false, 'success' => false, 'msg' => 'Chưa đăng nhập']);
            exit;
        }

        $db = Database::getInstance();

        $action = trim($_GET['action'] ?? '');
        $body = [];
        if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $raw = file_get_contents('php://input');
            $body = $raw ? (json_decode($raw, true) ?? []) : [];
            $action = trim($body['action'] ?? $_POST['action'] ?? '');
        }

        ob_end_clean();

        switch ($action) {
            case 'get_profile':
                $st = $db->prepare("SELECT employee_id, full_name, date_of_birth, gender, phone, email, address, hire_date, position FROM Employee WHERE account_id=? LIMIT 1");
                $st->bind_param('i', $acc_id);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if (!$row) {
                    echo json_encode(['success' => false, 'message' => 'Không tìm thấy hồ sơ']);
                } else {
                    echo json_encode(['success' => true, 'data' => $row]);
                }
                break;

            case 'update_profile':
                $fn  = trim($body['full_name'] ?? '');
                if (!$fn) {
                    echo json_encode(['success' => false, 'message' => 'Họ và tên không được để trống']);
                    exit;
                }
                $ph  = trim($body['phone'] ?? '');
                $em  = trim($body['email'] ?? '');
                $dob = ($body['date_of_birth'] ?? '') ?: null;
                $gen = in_array($body['gender'] ?? '', ['Male', 'Female', 'Other']) ? $body['gender'] : null;
                $adr = trim($body['address'] ?? '');

                $st  = $db->prepare("UPDATE Employee SET full_name=?, phone=?, email=?, date_of_birth=?, gender=?, address=? WHERE account_id=?");
                $st->bind_param('ssssssi', $fn, $ph, $em, $dob, $gen, $adr, $acc_id);
                if ($st->execute()) {
                    $_SESSION['ho_ten'] = $_SESSION['full_name'] = $fn;
                    echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $db->error]);
                }
                $st->close();
                break;

            case 'change_password':
                $cur = $body['current_password'] ?? '';
                $npw = $body['new_password'] ?? '';
                if (strlen($npw) < 6) {
                    echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự']);
                    exit;
                }
                $st  = $db->prepare("SELECT password FROM Account WHERE account_id=? LIMIT 1");
                $st->bind_param('i', $acc_id);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if (!$row) {
                    echo json_encode(['success' => false, 'message' => 'Tài khoản không tồn tại']);
                    exit;
                }
                if (!password_verify($cur, $row['password']) && $cur !== $row['password']) {
                    echo json_encode(['success' => false, 'message' => 'Mật khẩu hiện tại không đúng']);
                    exit;
                }
                $h   = password_hash($npw, PASSWORD_DEFAULT);
                $st2 = $db->prepare("UPDATE Account SET password=? WHERE account_id=?");
                $st2->bind_param('si', $h, $acc_id);
                if ($st2->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Đổi mật khẩu thành công']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật']);
                }
                $st2->close();
                break;

            case 'get_gym_settings':
                $res = $db->query("SELECT setting_key, setting_value FROM gym_settings");
                $cfg = [];
                if ($res) {
                    while ($r = $res->fetch_assoc()) {
                        $cfg[$r['setting_key']] = $r['setting_value'];
                    }
                }
                echo json_encode([
                    'ok'     => true,
                    'lat'    => floatval($cfg['gym_lat']           ?? 21.0285),
                    'lng'    => floatval($cfg['gym_lng']           ?? 105.8542),
                    'radius' => intval( $cfg['gym_radius_m']       ?? 100),
                    'name'   =>         $cfg['gym_location_name']  ?? 'Elite Gym',
                    'check'  => intval( $cfg['location_check']     ?? 1),
                ]);
                break;

            case 'get_today_status':
                $eid = $this->getEid($acc_id);
                if (!$eid) {
                    echo json_encode(['ok' => false, 'msg' => "Không tìm thấy nhân viên (account_id=$acc_id)"]);
                    exit;
                }
                $today = date('Y-m-d');
                $st  = $db->prepare("SELECT status, check_in, check_out FROM Attendance WHERE employee_id=? AND work_date=? LIMIT 1");
                $st->bind_param('is', $eid, $today);
                $st->execute();
                $rec = $st->get_result()->fetch_assoc();
                $st->close();
                echo json_encode(['ok' => true, 'record' => $rec ?: null]);
                break;

            case 'checkin':
                $eid = $this->getEid($acc_id);
                if (!$eid) {
                    echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy nhân viên']);
                    exit;
                }
                $today = date('Y-m-d');
                $st  = $db->prepare("SELECT attendance_id, check_in FROM Attendance WHERE employee_id=? AND work_date=? LIMIT 1");
                $st->bind_param('is', $eid, $today);
                $st->execute();
                $ex  = $st->get_result()->fetch_assoc();
                $st->close();
                if ($ex && $ex['check_in']) {
                    echo json_encode(['ok' => false, 'msg' => 'Bạn đã check in hôm nay rồi!']);
                    exit;
                }

                $now    = date('H:i:s');
                $lat    = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
                $lng    = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
                $status = (intval(date('H')) >= 9) ? 'Late' : 'Present';

                // Đảm bảo cột GPS tồn tại
                $this->ensureGpsColumns();

                if ($ex) {
                    $s = $db->prepare("UPDATE Attendance SET check_in=?, status=?, checkin_lat=?, checkin_lng=? WHERE attendance_id=?");
                    $s->bind_param('ssddi', $now, $status, $lat, $lng, $ex['attendance_id']);
                    $s->execute();
                    $s->close();
                } else {
                    $s = $db->prepare("INSERT INTO Attendance (employee_id, work_date, check_in, status, checkin_lat, checkin_lng) VALUES (?, ?, ?, ?, ?, ?)");
                    $s->bind_param('isssdd', $eid, $today, $now, $status, $lat, $lng);
                    $s->execute();
                    $s->close();
                }
                echo json_encode(['ok' => true, 'time' => substr($now, 0, 5), 'status' => $status]);
                break;

            case 'checkout':
                $eid = $this->getEid($acc_id);
                if (!$eid) {
                    echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy nhân viên']);
                    exit;
                }
                $today = date('Y-m-d');
                $st  = $db->prepare("SELECT attendance_id, check_in, check_out FROM Attendance WHERE employee_id=? AND work_date=? LIMIT 1");
                $st->bind_param('is', $eid, $today);
                $st->execute();
                $rec = $st->get_result()->fetch_assoc();
                $st->close();
                if (!$rec || !$rec['check_in']) {
                    echo json_encode(['ok' => false, 'msg' => 'Bạn chưa check in hôm nay!']);
                    exit;
                }
                if ($rec['check_out']) {
                    echo json_encode(['ok' => false, 'msg' => 'Bạn đã check out rồi!']);
                    exit;
                }

                $now  = date('H:i:s');
                $lat  = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
                $lng  = isset($_POST['lng']) ? floatval($_POST['lng']) : null;
                $cin  = explode(':', $rec['check_in']);
                $cout = explode(':', $now);
                $mins = (intval($cout[0]) * 60 + intval($cout[1])) - (intval($cin[0]) * 60 + intval($cin[1]));
                $h    = floor(max(0, $mins) / 60);
                $m    = max(0, $mins) % 60;
                $hrs  = "{$h}h" . str_pad($m, 2, '0', STR_PAD_LEFT) . "p";

                // Đảm bảo cột GPS tồn tại
                $this->ensureGpsColumns();

                $s = $db->prepare("UPDATE Attendance SET check_out=?, checkout_lat=?, checkout_lng=? WHERE attendance_id=?");
                $s->bind_param('sddi', $now, $lat, $lng, $rec['attendance_id']);
                $s->execute();
                $s->close();
                echo json_encode(['ok' => true, 'time' => substr($now, 0, 5), 'hours' => $hrs]);
                break;

            case 'get_history':
                $eid   = $this->getEid($acc_id);
                if (!$eid) {
                    echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy nhân viên']);
                    exit;
                }
                $limit = max(1, min(30, intval($_GET['limit'] ?? 7)));
                $st    = $db->prepare("SELECT work_date, check_in, check_out, status FROM Attendance WHERE employee_id=? ORDER BY work_date DESC LIMIT ?");
                $st->bind_param('ii', $eid, $limit);
                $st->execute();
                $rows  = $st->get_result()->fetch_all(MYSQLI_ASSOC);
                $st->close();
                echo json_encode(['ok' => true, 'records' => $rows]);
                break;

            case 'get_payroll':
                $eid = $this->getEid($acc_id);
                if (!$eid) {
                    echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy nhân viên']);
                    exit;
                }
                $limit = max(1, min(12, intval($_GET['limit'] ?? 6)));
                $st = $db->prepare("
                    SELECT p.month, p.year, p.base_salary, p.allowance, p.bonus,
                           p.deduction, p.net_salary,
                           e.monthly_salary
                    FROM Payroll p
                    JOIN Employee e ON e.employee_id = p.employee_id
                    WHERE p.employee_id = ?
                    ORDER BY p.year DESC, p.month DESC
                    LIMIT ?
                ");
                $st->bind_param('ii', $eid, $limit);
                $st->execute();
                $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
                $st->close();
                echo json_encode(['ok' => true, 'records' => $rows]);
                break;

            default:
                echo json_encode(['ok' => false, 'success' => false, 'msg' => "Action '$action' không hợp lệ"]);
        }
    }

    private function getEid(int $aid): ?int
    {
        $db = Database::getInstance();
        $st = $db->prepare("SELECT employee_id FROM Employee WHERE account_id=? LIMIT 1");
        $st->bind_param('i', $aid);
        $st->execute();
        $r  = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ? (int)$r['employee_id'] : null;
    }

    private function ensureGpsColumns(): void
    {
        $db = Database::getInstance();
        $gps_cols = ['checkin_lat', 'checkin_lng', 'checkout_lat', 'checkout_lng'];
        $exist_res = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='datn' AND TABLE_NAME='Attendance'");
        $exist_cols = [];
        if ($exist_res) {
            while ($ec = $exist_res->fetch_assoc()) {
                $exist_cols[] = $ec['COLUMN_NAME'];
            }
        }
        foreach ($gps_cols as $gc) {
            if (!in_array($gc, $exist_cols)) {
                $db->query("ALTER TABLE Attendance ADD COLUMN `$gc` DOUBLE NULL");
            }
        }
    }

    private function sendChangePasswordOtp(string $toEmail, string $otp): bool
    {
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
        } catch (\Exception $e) {
            error_log("PHPMailer (change pw): " . $mail->ErrorInfo);
            return false;
        }
    }
}
