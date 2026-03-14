<?php
// ============================================================
//  ELITE GYM — Profile_function.php
//  Đặt tại: DATN/Internal/Staff/layout/Profile/Profile_function.php
// ============================================================

require_once __DIR__ . '/../../../../auth_check.php';
requireRole('Customer');

header('Content-Type: application/json; charset=utf-8');

$db = new mysqli('localhost', 'root', '', 'datn');
if ($db->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}
$db->set_charset('utf8mb4');

$account_id = (int)$_SESSION['account_id'];
$action     = '';

// Support both GET and POST (JSON body)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
} else {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
}

// ============================================================
//  GET PROFILE
// ============================================================
if ($action === 'get_profile') {

    $sql = "SELECT c.customer_id, c.full_name, c.date_of_birth, c.gender,
                   c.phone, c.email, c.address, c.registered_at
            FROM Customer c
            WHERE c.account_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hồ sơ']);
        exit;
    }

    // Active membership
    $membership = null;
    $sql2 = "SELECT mp.plan_name, mr.start_date, mr.end_date
             FROM MembershipRegistration mr
             JOIN MembershipPlan mp ON mr.plan_id = mp.plan_id
             WHERE mr.customer_id = ?
               AND mr.end_date >= CURDATE()
             ORDER BY mr.end_date DESC
             LIMIT 1";
    $stmt2 = $db->prepare($sql2);
    $stmt2->bind_param('i', $row['customer_id']);
    $stmt2->execute();
    $mRow = $stmt2->get_result()->fetch_assoc();
    if ($mRow) $membership = $mRow;

    echo json_encode([
        'success'    => true,
        'data'       => $row,
        'membership' => $membership
    ]);
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

    $sql = "UPDATE Customer
            SET full_name = ?, phone = ?, email = ?,
                date_of_birth = ?, gender = ?, address = ?
            WHERE account_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ssssssi', $full_name, $phone, $email, $dob, $gen, $address, $account_id);

    if ($stmt->execute()) {
        $_SESSION['ho_ten'] = $full_name;
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

    $current  = $body['current_password'] ?? '';
    $new_pw   = $body['new_password']     ?? '';

    if (strlen($new_pw) < 6) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự']);
        exit;
    }

    // Get current hashed password
    $stmt = $db->prepare("SELECT password FROM Account WHERE account_id = ?");
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Tài khoản không tồn tại']);
        exit;
    }

    // Verify current password (support both plain and hashed)
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

// Unknown action
echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
