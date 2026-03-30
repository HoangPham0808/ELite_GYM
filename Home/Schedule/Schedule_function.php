<?php
// Home/Schedule/Schedule_function.php
// Xử lý đăng ký / hủy đăng ký lớp tập qua AJAX
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');

// ── Auth ──────────────────────────────────────────────────────
if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}
// ── Chỉ nhận POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method không hợp lệ']);
    exit;
}
require_once '../../Database/db.php';

$account_id = (int)$_SESSION['account_id'];
$action     = trim($_POST['action']   ?? '');   // 'register' | 'cancel'
$class_id   = (int)($_POST['class_id'] ?? 0);

// ── Lấy customer_id ───────────────────────────────────────────
$r = $conn->prepare("SELECT customer_id FROM Customer WHERE account_id = ? LIMIT 1");
$r->bind_param("i", $account_id);
$r->execute();
$cid = (int)($r->get_result()->fetch_assoc()['customer_id'] ?? 0);
$r->close();

if (!$cid || !$class_id || !in_array($action, ['register', 'cancel'], true)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

// ── Kiểm tra lớp tồn tại & lấy thời gian ─────────────────────
$sc = $conn->prepare("
    SELECT tc.class_id, tc.start_time,
           gr.package_type_id AS room_pkg_type_id,
           pt.sort_order      AS room_sort_order,
           pt.type_name       AS room_type_name
    FROM TrainingClass tc
    LEFT JOIN GymRoom gr    ON gr.room_id   = tc.room_id
    LEFT JOIN PackageType pt ON pt.type_id  = gr.package_type_id
    WHERE tc.class_id = ? LIMIT 1
");
$sc->bind_param("i", $class_id);
$sc->execute();
$cls = $sc->get_result()->fetch_assoc();
$sc->close();

if (!$cls) {
    echo json_encode(['success' => false, 'message' => 'Lớp không tồn tại']);
    exit;
}

// Không cho đăng ký / hủy lớp đã qua
if (strtotime($cls['start_time']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Lớp học đã diễn ra, không thể thay đổi']);
    exit;
}

// ── Kiểm tra PackageType khi đăng ký ─────────────────────────
if ($action === 'register' && $cls['room_sort_order'] !== null) {
    // Lấy sort_order của gói active cao nhất của khách
    $pkg = $conn->prepare("
        SELECT pt.sort_order
        FROM MembershipRegistration mr
        JOIN MembershipPlan mp    ON mp.plan_id   = mr.plan_id
        JOIN PackageType pt       ON pt.type_id   = mp.package_type_id
        WHERE mr.customer_id = ?
          AND mr.status      = 'active'
          AND mr.end_date   >= CURDATE()
        ORDER BY pt.sort_order DESC
        LIMIT 1
    ");
    $pkg->bind_param("i", $cid);
    $pkg->execute();
    $cust_sort = $pkg->get_result()->fetch_assoc()['sort_order'] ?? null;
    $pkg->close();

    if ($cust_sort === null) {
        echo json_encode(['success' => false, 'message' => 'Bạn chưa có gói tập. Vui lòng mua gói để đăng ký lớp.']);
        exit;
    }
    if ((int)$cust_sort < (int)$cls['room_sort_order']) {
        echo json_encode(['success' => false, 'message' => 'Gói tập của bạn không đủ để đăng ký lớp tại phòng ' . $cls['room_type_name'] . '. Vui lòng nâng cấp gói.']);
        exit;
    }
}

// ── Kiểm tra đã đăng ký chưa ─────────────────────────────────
$chk = $conn->prepare("SELECT class_registration_id FROM ClassRegistration WHERE class_id = ? AND customer_id = ? LIMIT 1");
$chk->bind_param("ii", $class_id, $cid);
$chk->execute();
$existing = $chk->get_result()->fetch_assoc();
$chk->close();

if ($action === 'register') {
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã đăng ký lớp này rồi']);
        exit;
    }

    // ── Kiểm tra trùng time slot (cùng giờ bắt đầu) ──────────
    $slot_ts   = strtotime($cls['start_time']);
    $slot_date = date('Y-m-d', $slot_ts);
    $slot_time = date('H:i',   $slot_ts);

    $dup = $conn->prepare("
        SELECT cr.class_id
        FROM ClassRegistration cr
        JOIN TrainingClass tc ON tc.class_id = cr.class_id
        WHERE cr.customer_id   = ?
          AND DATE(tc.start_time) = ?
          AND TIME_FORMAT(tc.start_time, '%H:%i') = ?
        LIMIT 1
    ");
    $dup->bind_param("iss", $cid, $slot_date, $slot_time);
    $dup->execute();
    $dup_row = $dup->get_result()->fetch_assoc();
    $dup->close();

    if ($dup_row) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã đăng ký một lớp khác vào cùng giờ ' . $slot_time . ' ngày ' . date('d/m', $slot_ts) . '. Hủy lớp đó trước nếu muốn đổi sang lớp này.']);
        exit;
    }

    // Thêm đăng ký
    $ins = $conn->prepare("INSERT INTO ClassRegistration (class_id, customer_id) VALUES (?, ?)");
    $ins->bind_param("ii", $class_id, $cid);
    if ($ins->execute()) {
        $ins->close();
        echo json_encode(['success' => true, 'message' => 'Đăng ký lớp thành công!', 'action' => 'registered']);
    } else {
        $ins->close();
        echo json_encode(['success' => false, 'message' => 'Đăng ký thất bại, vui lòng thử lại']);
    }

} elseif ($action === 'cancel') {
    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng ký lớp này']);
        exit;
    }
    // Xóa đăng ký
    $del = $conn->prepare("DELETE FROM ClassRegistration WHERE class_id = ? AND customer_id = ?");
    $del->bind_param("ii", $class_id, $cid);
    if ($del->execute()) {
        $del->close();
        echo json_encode(['success' => true, 'message' => 'Đã hủy đăng ký lớp', 'action' => 'cancelled']);
    } else {
        $del->close();
        echo json_encode(['success' => false, 'message' => 'Hủy thất bại, vui lòng thử lại']);
    }
}

$conn->close();
?>
