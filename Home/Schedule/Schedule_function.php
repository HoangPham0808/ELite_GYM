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
$sc = $conn->prepare("SELECT class_id, class_time FROM TrainingClass WHERE class_id = ? LIMIT 1");
$sc->bind_param("i", $class_id);
$sc->execute();
$cls = $sc->get_result()->fetch_assoc();
$sc->close();

if (!$cls) {
    echo json_encode(['success' => false, 'message' => 'Lớp không tồn tại']);
    exit;
}

// Không cho đăng ký / hủy lớp đã qua
if (strtotime($cls['class_time']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Lớp học đã diễn ra, không thể thay đổi']);
    exit;
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
