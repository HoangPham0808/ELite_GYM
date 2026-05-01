<?php

ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_name('PHPSESSID');
    // Fix cookie để hoạt động qua ngrok (HTTPS cross-origin)
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'None',   // Bắt buộc khi dùng ngrok
    ]);
    @session_start();
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('ngrok-skip-browser-warning: true');
// Cho phép credentials (cookie/session) qua ngrok
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, ngrok-skip-browser-warning');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function jsonOut(array $data): void {
    ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Kiểm tra quyền — cho phép qua ngrok nếu session hợp lệ
// Nếu truy cập qua iframe trong adm.php, session đã được thiết lập từ domain gốc
$allowedRoles = ['Admin', 'Staff'];
$hasSession = isset($_SESSION['role']) && in_array($_SESSION['role'], $allowedRoles);

// Fallback: cho phép nếu referer từ cùng host (truy cập trực tiếp qua ngrok sau khi đăng nhập)
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host    = $_SERVER['HTTP_HOST'] ?? '';
$fromSameHost = strpos($referer, $host) !== false || strpos($referer, 'ngrok') !== false;

if (!$hasSession && !$fromSameHost) {
    jsonOut(['success' => false, 'message' => 'Không có quyền truy cập — vui lòng đăng nhập lại']);
}

require_once '../../../../Database/db.php';
if (!isset($conn) || $conn->connect_error) {
    jsonOut(['success' => false, 'message' => 'Lỗi kết nối DB']);
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

// ════════════════════════════════════════════════════════════
// GET CHECK-IN INFO — Lấy thông tin khách + trạng thái gói
// ════════════════════════════════════════════════════════════
if ($action === 'get_checkin_info') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) jsonOut(['success' => false, 'message' => 'ID không hợp lệ']);

    // Thông tin cơ bản
    $stmt = $conn->prepare("SELECT customer_id, full_name, phone, gender, email, date_of_birth FROM Customer WHERE customer_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    if (!$customer) jsonOut(['success' => false, 'message' => 'Không tìm thấy khách hàng #' . $id]);

    // Gói tập hiện tại
    $stmt = $conn->prepare("
        SELECT mr.end_date, mp.plan_name,
            CASE
                WHEN mr.end_date >= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'active'
                WHEN mr.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'expiring'
                WHEN mr.end_date < CURDATE() THEN 'expired'
                ELSE 'none'
            END AS pkg_status
        FROM MembershipRegistration mr
        JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
        WHERE mr.customer_id = ?
        ORDER BY mr.end_date DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $pkg = $stmt->get_result()->fetch_assoc();

    // Check-in gần nhất và check-out gần nhất HÔM NAY
    $stmt = $conn->prepare("
        SELECT
            MAX(CASE WHEN type = 'checkin'  THEN check_time END) AS last_checkin,
            MAX(CASE WHEN type = 'checkout' THEN check_time END) AS last_checkout
        FROM GymCheckIn
        WHERE customer_id = ? AND DATE(check_time) = CURDATE()
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $today = $stmt->get_result()->fetch_assoc();

    // Khách có đang trong gym không?
    // (đã check-in hôm nay nhưng chưa check-out, hoặc check-in sau checkout gần nhất)
    $currentlyIn = false;
    if (!empty($today['last_checkin'])) {
        if (empty($today['last_checkout'])) {
            $currentlyIn = true;
        } else {
            $currentlyIn = strtotime($today['last_checkin']) > strtotime($today['last_checkout']);
        }
    }

    // ── Lịch tập hôm nay của khách ──────────────────────────
    $stmt = $conn->prepare("
        SELECT
            tc.class_id,
            tc.class_name,
            tc.start_time,
            tc.end_time,
            gr.room_name,
            e.full_name AS trainer_name
        FROM ClassRegistration cr
        JOIN TrainingClass tc ON tc.class_id = cr.class_id
        LEFT JOIN GymRoom gr   ON gr.room_id   = tc.room_id
        LEFT JOIN Employee e   ON e.employee_id = tc.trainer_id
        WHERE cr.customer_id = ?
          AND DATE(tc.start_time) = CURDATE()
        ORDER BY tc.start_time ASC
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $today_classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // ── Server-side: tự động checkout nếu lớp đã kết thúc ──
    // Kiểm tra khách đang trong gym nhưng lớp cuối đã hết giờ
    if ($currentlyIn && !empty($today_classes)) {
        $latest_end = null;
        foreach ($today_classes as $cls) {
            if (!$latest_end || $cls['end_time'] > $latest_end) {
                $latest_end = $cls['end_time'];
            }
        }
        // Nếu giờ kết thúc lớp cuối đã qua → auto checkout
        if ($latest_end && strtotime($latest_end) < time()) {
            $auto = $conn->prepare("
                INSERT INTO GymCheckIn (customer_id, type, check_time, recorded_by)
                VALUES (?, 'checkout', ?, 0)
            ");
            $auto->bind_param('is', $id, $latest_end);
            $auto->execute();
            $currentlyIn = false;
            // Cập nhật last_checkout
            $today['last_checkout'] = $latest_end;
        }
    }

    jsonOut([
        'success'       => true,
        'customer'      => $customer,
        'pkg_name'      => $pkg['plan_name']  ?? null,
        'pkg_end'       => $pkg['end_date']   ?? null,
        'pkg_status'    => $pkg['pkg_status'] ?? 'none',
        'last_checkin'  => $today['last_checkin']  ?? null,
        'last_checkout' => $today['last_checkout'] ?? null,
        'currently_in'  => $currentlyIn,
        'today_classes' => $today_classes,
    ]);
}

// ════════════════════════════════════════════════════════════
// DO CHECK-IN / CHECK-OUT
// ════════════════════════════════════════════════════════════
if ($action === 'do_checkin') {
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $type        = trim($_POST['type'] ?? '');       // 'checkin' | 'checkout'
    $by          = intval($_SESSION['account_id'] ?? 0);

    if ($customer_id <= 0)              jsonOut(['success' => false, 'message' => 'ID không hợp lệ']);
    if (!in_array($type, ['checkin','checkout'])) jsonOut(['success' => false, 'message' => 'Loại không hợp lệ']);

    // Kiểm tra tồn tại khách
    $chk = $conn->prepare("SELECT customer_id, full_name FROM Customer WHERE customer_id = ?");
    $chk->bind_param('i', $customer_id);
    $chk->execute();
    $cust = $chk->get_result()->fetch_assoc();
    if (!$cust) jsonOut(['success' => false, 'message' => 'Không tìm thấy khách hàng']);

    if ($type === 'checkin') {
        // Kiểm tra xem đã check-in hôm nay chưa mà chưa check-out
        $dupChk = $conn->prepare("
            SELECT COUNT(*) AS c
            FROM GymCheckIn
            WHERE customer_id = ?
              AND type = 'checkin'
              AND DATE(check_time) = CURDATE()
              AND check_time > IFNULL(
                    (SELECT MAX(check_time) FROM GymCheckIn
                     WHERE customer_id = ? AND type = 'checkout' AND DATE(check_time) = CURDATE()),
                    '1970-01-01')
        ");
        $dupChk->bind_param('ii', $customer_id, $customer_id);
        $dupChk->execute();
        if ($dupChk->get_result()->fetch_assoc()['c'] > 0) {
            jsonOut(['success' => false, 'message' => $cust['full_name'] . ' đã check-in và chưa check-out!']);
        }

        // Kiểm tra gói tập còn hạn
        $pkgChk = $conn->prepare("
            SELECT COUNT(*) AS c FROM MembershipRegistration
            WHERE customer_id = ? AND end_date >= CURDATE()
        ");
        $pkgChk->bind_param('i', $customer_id);
        $pkgChk->execute();
        if ($pkgChk->get_result()->fetch_assoc()['c'] == 0) {
            jsonOut(['success' => false, 'message' => 'Gói tập đã hết hạn hoặc chưa đăng ký!']);
        }

        // ── Kiểm tra có lịch tập đang diễn ra không ──────────
        $clsChk = $conn->prepare("
            SELECT tc.class_id, tc.class_name, tc.start_time, tc.end_time
            FROM ClassRegistration cr
            JOIN TrainingClass tc ON tc.class_id = cr.class_id
            WHERE cr.customer_id = ?
              AND DATE(tc.start_time) = CURDATE()
              AND NOW() BETWEEN tc.start_time AND tc.end_time
            LIMIT 1
        ");
        $clsChk->bind_param('i', $customer_id);
        $clsChk->execute();
        $ongoingClass = $clsChk->get_result()->fetch_assoc();
        if (!$ongoingClass) {
            // Kiểm tra có lớp sắp tới không để đưa ra thông báo cụ thể
            $nextCls = $conn->prepare("
                SELECT TIME_FORMAT(tc.start_time, '%H:%i') AS start_hm
                FROM ClassRegistration cr
                JOIN TrainingClass tc ON tc.class_id = cr.class_id
                WHERE cr.customer_id = ?
                  AND DATE(tc.start_time) = CURDATE()
                  AND tc.start_time > NOW()
                ORDER BY tc.start_time ASC
                LIMIT 1
            ");
            $nextCls->bind_param('i', $customer_id);
            $nextCls->execute();
            $next = $nextCls->get_result()->fetch_assoc();
            if ($next) {
                jsonOut(['success' => false, 'message' => "Chưa đến giờ tập — lớp bắt đầu lúc {$next['start_hm']}"]);
            } else {
                jsonOut(['success' => false, 'message' => 'Không có lịch tập nào đang diễn ra hôm nay!']);
            }
        }
    } else {
        // checkout — phải đã check-in trước
        $inChk = $conn->prepare("
            SELECT COUNT(*) AS c
            FROM GymCheckIn
            WHERE customer_id = ?
              AND type = 'checkin'
              AND DATE(check_time) = CURDATE()
              AND check_time > IFNULL(
                    (SELECT MAX(check_time) FROM GymCheckIn
                     WHERE customer_id = ? AND type = 'checkout' AND DATE(check_time) = CURDATE()),
                    '1970-01-01')
        ");
        $inChk->bind_param('ii', $customer_id, $customer_id);
        $inChk->execute();
        if ($inChk->get_result()->fetch_assoc()['c'] == 0) {
            jsonOut(['success' => false, 'message' => $cust['full_name'] . ' chưa check-in hôm nay!']);
        }
    }

    // Ghi vào DB
    $stmt = $conn->prepare("
        INSERT INTO GymCheckIn (customer_id, type, check_time, recorded_by)
        VALUES (?, ?, NOW(), ?)
    ");
    $stmt->bind_param('isi', $customer_id, $type, $by);

    if (!$stmt->execute()) {
        jsonOut(['success' => false, 'message' => 'Lỗi ghi DB: ' . $conn->error]);
    }

    $label   = $type === 'checkin' ? 'Check-in' : 'Check-out';
    $timeStr = date('H:i:s');
    jsonOut([
        'success'  => true,
        'message'  => "$label thành công cho {$cust['full_name']} lúc $timeStr",
        'type'     => $type,
        'time'     => $timeStr,
        'customer' => $cust,
    ]);
}

// ════════════════════════════════════════════════════════════
// GET TODAY STATS (tùy chọn — cho dashboard)
// ════════════════════════════════════════════════════════════
if ($action === 'today_stats') {
    $total_in  = (int)$conn->query("SELECT COUNT(*) AS c FROM GymCheckIn WHERE type='checkin'  AND DATE(check_time)=CURDATE()")->fetch_assoc()['c'];
    $total_out = (int)$conn->query("SELECT COUNT(*) AS c FROM GymCheckIn WHERE type='checkout' AND DATE(check_time)=CURDATE()")->fetch_assoc()['c'];
    $inside    = $total_in - $total_out;
    jsonOut(['success' => true, 'checkin_today' => $total_in, 'checkout_today' => $total_out, 'currently_inside' => max(0, $inside)]);
}

jsonOut(['success' => false, 'message' => "Action '$action' không hợp lệ"]);
?>
