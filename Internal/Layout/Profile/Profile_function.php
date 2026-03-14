<?php
// ============================================================
//  ELITE GYM — Profile_function.php
//  Đặt tại: ELITE_GYM/Internal/Layout/Profile/Profile_function.php
// ============================================================

require_once __DIR__ . '/../../auth_check.php';
requireRole('Employee');

header('Content-Type: application/json; charset=utf-8');

$db = new mysqli('localhost', 'root', '', 'datn');
if ($db->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $db->connect_error]);
    exit;
}
$db->set_charset('utf8mb4');

$account_id = (int)($_SESSION['account_id'] ?? 0);

// Support GET and POST JSON
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $body   = [];
} else {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
}

// ============================================================
//  GET PROFILE
// ============================================================
if ($action === 'get_profile') {

    $sql = "SELECT e.employee_id, e.full_name, e.date_of_birth, e.gender,
                   e.phone, e.email, e.address, e.hire_date, e.position
            FROM Employee e
            WHERE e.account_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hồ sơ']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $row]);
    exit;
}

// ============================================================
//  UPDATE PROFILE
// ============================================================
if ($action === 'update_profile') {

    $full_name     = trim($body['full_name']     ?? '');
    $phone         = trim($body['phone']         ?? '');
    $email         = trim($body['email']         ?? '');
    $date_of_birth = trim($body['date_of_birth'] ?? '');
    $gender        = trim($body['gender']        ?? '');
    $address       = trim($body['address']       ?? '');

    if (empty($full_name)) {
        echo json_encode(['success' => false, 'message' => 'Họ và tên không được để trống']);
        exit;
    }

    $dob = (!empty($date_of_birth)) ? $date_of_birth : null;
    $gen = in_array($gender, ['Male','Female','Other']) ? $gender : null;

    $sql = "UPDATE Employee
            SET full_name = ?, phone = ?, email = ?,
                date_of_birth = ?, gender = ?, address = ?
            WHERE account_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ssssssi', $full_name, $phone, $email, $dob, $gen, $address, $account_id);

    if ($stmt->execute()) {
        // Cập nhật session
        $_SESSION['ho_ten']    = $full_name;
        $_SESSION['full_name'] = $full_name;
        echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật: ' . $db->error]);
    }
    exit;
}

// ============================================================
//  CHANGE PASSWORD
// ============================================================
if ($action === 'change_password') {

    $current = $body['current_password'] ?? '';
    $new_pw  = $body['new_password']     ?? '';

    if (strlen($new_pw) < 6) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự']);
        exit;
    }

    $stmt = $db->prepare("SELECT password FROM Account WHERE account_id = ?");
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Tài khoản không tồn tại']);
        exit;
    }

    // Verify: hỗ trợ cả password_hash và plain text
    $valid = password_verify($current, $row['password']) || ($current === $row['password']);

    if (!$valid) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu hiện tại không đúng']);
        exit;
    }

    $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
    $stmt2  = $db->prepare("UPDATE Account SET password = ? WHERE account_id = ?");
    $stmt2->bind_param('si', $hashed, $account_id);

    if ($stmt2->execute()) {
        echo json_encode(['success' => true, 'message' => 'Đổi mật khẩu thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật mật khẩu']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
