<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require_once '../../../Database/db.php';

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Cannot connect to DB']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

// ── STATS ──────────────────────────────────────────────────────────
case 'get_stats':
    $total      = $conn->query("SELECT COUNT(*) c FROM Account")->fetch_assoc()['c'];
    $active     = $conn->query("SELECT COUNT(*) c FROM Account WHERE is_active=1")->fetch_assoc()['c'];
    $admin      = $conn->query("SELECT COUNT(*) c FROM Account WHERE role='Admin'")->fetch_assoc()['c'];
    $employee   = $conn->query("SELECT COUNT(*) c FROM Account WHERE role='Employee'")->fetch_assoc()['c'];
    $customer   = $conn->query("SELECT COUNT(*) c FROM Account WHERE role='Customer'")->fetch_assoc()['c'];
    $today      = $conn->query("SELECT COUNT(*) c FROM LoginHistory WHERE DATE(login_time)=CURDATE() AND result='Success'")->fetch_assoc()['c'];
    $fail_today = $conn->query("SELECT COUNT(*) c FROM LoginHistory WHERE DATE(login_time)=CURDATE() AND result='Failed'")->fetch_assoc()['c'];
    echo json_encode([
        'success'     => true,
        'total'       => (int)$total,
        'active'      => (int)$active,
        'admin'       => (int)$admin,
        'employee'    => (int)$employee,
        'customer'    => (int)$customer,
        'today_login' => (int)$today,
        'fail_today'  => (int)$fail_today,
    ]);
    break;

// ── ACCOUNT LIST ───────────────────────────────────────────────────
case 'get_accounts':
    $page   = max(1, (int)($_GET['page']   ?? 1));
    $limit  = (int)($_GET['limit']  ?? 15);
    $search = trim($_GET['search']  ?? '');
    $role   = trim($_GET['role']    ?? '');
    $status = trim($_GET['status']  ?? '');
    $offset = ($page - 1) * $limit;

    $w = []; $p = []; $t = '';
    if ($search !== '') {
        $w[] = "(a.username LIKE ? OR COALESCE(e.full_name, c.full_name) LIKE ?)";
        $p[] = "%$search%"; $p[] = "%$search%"; $t .= 'ss';
    }
    if ($role   !== '') { $w[] = "a.role=?";      $p[] = $role;        $t .= 's'; }
    if ($status !== '') { $w[] = "a.is_active=?";  $p[] = (int)$status; $t .= 'i'; }
    $ws = $w ? 'WHERE ' . implode(' AND ', $w) : '';

    $base = "FROM Account a
             LEFT JOIN Employee e ON e.account_id = a.account_id
             LEFT JOIN Customer c ON c.account_id = a.account_id
             $ws";

    $cs = $conn->prepare("SELECT COUNT(*) total $base");
    if ($p) $cs->bind_param($t, ...$p);
    $cs->execute();
    $total = $cs->get_result()->fetch_assoc()['total'];

    $ds = $conn->prepare("
        SELECT
            a.account_id, a.username, a.role, a.is_active, a.created_at, a.last_login,
            COALESCE(e.full_name, c.full_name) AS full_name,
            COALESCE(e.email,     c.email)     AS email,
            COALESCE(e.phone,     c.phone)     AS phone,
            (SELECT COUNT(*) FROM LoginHistory WHERE account_id=a.account_id AND result='Success') AS login_count,
            (SELECT COUNT(*) FROM LoginHistory WHERE account_id=a.account_id AND result='Failed'
                AND DATE(login_time)=CURDATE()) AS fail_today
        $base ORDER BY a.account_id DESC LIMIT ? OFFSET ?");
    $dp = $p; $dt = $t; $dp[] = $limit; $dp[] = $offset; $dt .= 'ii';
    $ds->bind_param($dt, ...$dp);
    $ds->execute();
    $rows = $ds->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode([
        'success'    => true,
        'data'       => $rows,
        'total'      => (int)$total,
        'page'       => $page,
        'totalPages' => max(1, ceil($total / $limit)),
    ]);
    break;

// ── ACCOUNT DETAIL ─────────────────────────────────────────────────
case 'get_account_detail':
    $id = (int)($_GET['id'] ?? 0);
    $s  = $conn->prepare("
        SELECT a.*,
            COALESCE(e.full_name, c.full_name) AS full_name,
            COALESCE(e.email,     c.email)     AS email,
            COALESCE(e.phone,     c.phone)     AS phone,
            e.employee_id, c.customer_id
        FROM Account a
        LEFT JOIN Employee e ON e.account_id = a.account_id
        LEFT JOIN Customer c ON c.account_id = a.account_id
        WHERE a.account_id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $acc = $s->get_result()->fetch_assoc();

    $s2 = $conn->prepare("SELECT * FROM LoginHistory WHERE account_id=? ORDER BY login_time DESC LIMIT 10");
    $s2->bind_param('i', $id);
    $s2->execute();
    $hist = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'account' => $acc, 'history' => $hist]);
    break;

// ── ADD ACCOUNT ────────────────────────────────────────────────────
case 'add_account':
    $username  = trim($_POST['username']   ?? '');
    $pass      = trim($_POST['password']   ?? '');
    $role      = trim($_POST['role']       ?? 'Customer');
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

    if (!$username || !$pass) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']); exit;
    }
    if (strlen($pass) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']); exit;
    }
    $chk = $conn->prepare("SELECT account_id FROM Account WHERE username=?");
    $chk->bind_param('s', $username); $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']); exit;
    }
    $s = $conn->prepare("INSERT INTO Account (username, password, role, is_active) VALUES (?,?,?,?)");
    $s->bind_param('sssi', $username, $pass, $role, $is_active);
    echo json_encode($s->execute()
        ? ['success' => true,  'message' => 'Account created successfully', 'id' => $conn->insert_id]
        : ['success' => false, 'message' => $conn->error]);
    break;

// ── UPDATE ACCOUNT ─────────────────────────────────────────────────
case 'update_account':
    $id        = (int)($_POST['id']        ?? 0);
    $role      = trim($_POST['role']       ?? '');
    $is_active = (int)($_POST['is_active'] ?? 1);
    $pass      = trim($_POST['password']   ?? '');

    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
    if ($pass) {
        if (strlen($pass) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']); exit;
        }
        $s = $conn->prepare("UPDATE Account SET role=?, is_active=?, password=? WHERE account_id=?");
        $s->bind_param('sisi', $role, $is_active, $pass, $id);
    } else {
        $s = $conn->prepare("UPDATE Account SET role=?, is_active=? WHERE account_id=?");
        $s->bind_param('sii', $role, $is_active, $id);
    }
    echo json_encode($s->execute()
        ? ['success' => true,  'message' => 'Account updated successfully']
        : ['success' => false, 'message' => $conn->error]);
    break;

// ── TOGGLE STATUS ──────────────────────────────────────────────────
case 'toggle_status':
    $id  = (int)($_POST['id'] ?? 0);
    $cur = $conn->query("SELECT is_active FROM Account WHERE account_id=$id")->fetch_assoc();
    if (!$cur) { echo json_encode(['success' => false, 'message' => 'Account not found']); exit; }
    $new = $cur['is_active'] ? 0 : 1;
    $conn->query("UPDATE Account SET is_active=$new WHERE account_id=$id");
    echo json_encode([
        'success'   => true,
        'message'   => $new ? 'Account unlocked' : 'Account locked',
        'is_active' => $new,
    ]);
    break;

// ── RESET PASSWORD ─────────────────────────────────────────────────
case 'reset_password':
    $id   = (int)($_POST['id']       ?? 0);
    $pass = trim($_POST['password']  ?? '');
    if (!$id || strlen($pass) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']); exit;
    }
    $s = $conn->prepare("UPDATE Account SET password=? WHERE account_id=?");
    $s->bind_param('si', $pass, $id);
    echo json_encode($s->execute()
        ? ['success' => true,  'message' => 'Password reset successfully']
        : ['success' => false, 'message' => $conn->error]);
    break;

// ── DELETE ACCOUNT ─────────────────────────────────────────────────
case 'delete_account':
    $id  = (int)($_POST['id'] ?? 0);
    $emp = $conn->query("SELECT COUNT(*) c FROM Employee WHERE account_id=$id")->fetch_assoc()['c'];
    $cus = $conn->query("SELECT COUNT(*) c FROM Customer WHERE account_id=$id")->fetch_assoc()['c'];
    if ($emp > 0 || $cus > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete: account is linked to an employee/customer profile']); exit;
    }
    $conn->query("DELETE FROM LoginHistory WHERE account_id=$id");
    $s = $conn->prepare("DELETE FROM Account WHERE account_id=?");
    $s->bind_param('i', $id);
    echo json_encode($s->execute()
        ? ['success' => true,  'message' => 'Account deleted']
        : ['success' => false, 'message' => $conn->error]);
    break;

// ── LOGIN HISTORY ──────────────────────────────────────────────────
case 'get_login_history':
    $page   = max(1, (int)($_GET['page']   ?? 1));
    $limit  = (int)($_GET['limit']  ?? 20);
    $offset = ($page - 1) * $limit;
    $search = trim($_GET['search']  ?? '');
    $result = trim($_GET['result']  ?? '');
    $date   = trim($_GET['date']    ?? '');
    $acc_id = (int)($_GET['acc_id'] ?? 0);

    $w = []; $p = []; $t = '';
    if ($search !== '') { $w[] = "a.username LIKE ?";       $p[] = "%$search%"; $t .= 's'; }
    if ($result !== '') { $w[] = "lh.result=?";             $p[] = $result;     $t .= 's'; }
    if ($date   !== '') { $w[] = "DATE(lh.login_time)=?";  $p[] = $date;       $t .= 's'; }
    if ($acc_id  > 0)   { $w[] = "lh.account_id=?";        $p[] = $acc_id;     $t .= 'i'; }
    $ws = $w ? 'WHERE ' . implode(' AND ', $w) : '';

    $base = "FROM LoginHistory lh
             JOIN Account a ON a.account_id = lh.account_id
             LEFT JOIN Employee e ON e.account_id = a.account_id
             LEFT JOIN Customer c ON c.account_id = a.account_id
             $ws";

    $cs = $conn->prepare("SELECT COUNT(*) c $base");
    if ($p) $cs->bind_param($t, ...$p);
    $cs->execute();
    $total = $cs->get_result()->fetch_assoc()['c'];

    $ds = $conn->prepare("
        SELECT lh.*, a.username, a.role,
            COALESCE(e.full_name, c.full_name) AS full_name
        $base ORDER BY lh.login_time DESC LIMIT ? OFFSET ?");
    $dp = $p; $dt = $t; $dp[] = $limit; $dp[] = $offset; $dt .= 'ii';
    $ds->bind_param($dt, ...$dp);
    $ds->execute();
    $rows = $ds->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode([
        'success'    => true,
        'data'       => $rows,
        'total'      => (int)$total,
        'page'       => $page,
        'totalPages' => max(1, ceil($total / $limit)),
    ]);
    break;

// ── CLEAR LOGIN HISTORY ────────────────────────────────────────────
case 'clear_login_history':
    $id = (int)($_POST['account_id'] ?? 0);
    if ($id) {
        $conn->query("DELETE FROM LoginHistory WHERE account_id=$id");
    } else {
        $conn->query("DELETE FROM LoginHistory WHERE login_time < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    }
    echo json_encode(['success' => true, 'message' => 'Login history cleared']);
    break;

default:
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
