<?php
/**
 * notification_handler.php — Elite Gym
 * Đặt cùng thư mục với index.php
 *
 * Actions GET : get_notifications | get_unread_count
 * Actions POST: mark_read | mark_all_read | push_reply_notif
 */
session_start();
require_once '../../Database/db.php';

header('Content-Type: application/json; charset=utf-8');

/* ── Fix: xoá FK constraint cũ nếu còn ── */
try { $conn->query("ALTER TABLE `CustomerNotification` DROP FOREIGN KEY `fk_cn_customer`"); }
catch (\mysqli_sql_exception $_e) { /* không tồn tại — bỏ qua */ }

/* ── Auto-migrate (safe on all MySQL/MariaDB + Xdebug) ────────── */
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `CustomerNotification` (
            `notif_id`    INT          NOT NULL AUTO_INCREMENT,
            `customer_id` INT          NOT NULL,
            `type`        VARCHAR(50)  NOT NULL DEFAULT 'review_reply',
            `title`       VARCHAR(200) NOT NULL,
            `message`     VARCHAR(500) NOT NULL,
            `related_id`  INT          NULL,
            `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
            `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`notif_id`),
            KEY `idx_cust_read` (`customer_id`, `is_read`),
            KEY `idx_created`   (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (\mysqli_sql_exception $_e) { /* Table already exists — safe to ignore */ }

function json_ok($data=[]) { echo json_encode(['ok'=>true,  'data'=>$data]); exit; }
function json_err($msg)    { echo json_encode(['ok'=>false, 'msg' =>$msg]);  exit; }

function get_cid($conn, $aid) {
    $st = $conn->prepare("SELECT customer_id FROM Customer WHERE account_id=? LIMIT 1");
    $st->bind_param('i', $aid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? (int)$row['customer_id'] : 0;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$role   = $_SESSION['role'] ?? '';
$aid    = (int)($_SESSION['account_id'] ?? 0);

/* ── 1. Danh sách thông báo ──────────────────────────────── */
if ($action === 'get_notifications') {
    if ($role !== 'Customer' || !$aid) json_err('Chưa đăng nhập.');
    $cid = get_cid($conn, $aid);
    if (!$cid) json_err('Không tìm thấy khách hàng.');

    $limit  = min(20, (int)($_GET['limit']  ?? 10));
    $offset = max(0,  (int)($_GET['offset'] ?? 0));

    $rows = $conn->query("
        SELECT notif_id, type, title, message, related_id, is_read,
               DATE_FORMAT(created_at,'%d/%m/%Y %H:%i') AS time_fmt
        FROM CustomerNotification
        WHERE customer_id = $cid
        ORDER BY created_at DESC
        LIMIT $limit OFFSET $offset
    ")->fetch_all(MYSQLI_ASSOC);

    $unread = (int)$conn->query(
        "SELECT COUNT(*) c FROM CustomerNotification WHERE customer_id=$cid AND is_read=0"
    )->fetch_assoc()['c'];

    json_ok(['notifications'=>$rows, 'unread'=>$unread]);
}

/* ── 2. Số chưa đọc (polling) ────────────────────────────── */
if ($action === 'get_unread_count') {
    if ($role !== 'Customer' || !$aid) json_ok(['count'=>0]);
    $cid = get_cid($conn, $aid);
    if (!$cid) json_ok(['count'=>0]);
    $c = (int)$conn->query(
        "SELECT COUNT(*) c FROM CustomerNotification WHERE customer_id=$cid AND is_read=0"
    )->fetch_assoc()['c'];
    json_ok(['count'=>$c]);
}

/* ── 3. Đánh dấu 1 đã đọc ───────────────────────────────── */
if ($action === 'mark_read') {
    if ($role !== 'Customer' || !$aid) json_err('Chưa đăng nhập.');
    $nid = (int)($_POST['notif_id'] ?? 0);
    $cid = get_cid($conn, $aid);
    if (!$nid || !$cid) json_err('Dữ liệu không hợp lệ.');
    $st = $conn->prepare(
        "UPDATE CustomerNotification SET is_read=1 WHERE notif_id=? AND customer_id=?"
    );
    $st->bind_param('ii', $nid, $cid);
    $st->execute(); $st->close();
    json_ok();
}

/* ── 4. Đánh dấu tất cả đã đọc ──────────────────────────── */
if ($action === 'mark_all_read') {
    if ($role !== 'Customer' || !$aid) json_err('Chưa đăng nhập.');
    $cid = get_cid($conn, $aid);
    if (!$cid) json_err('Không tìm thấy khách hàng.');
    $conn->query("UPDATE CustomerNotification SET is_read=1 WHERE customer_id=$cid");
    json_ok();
}

/* ── 5. Tạo/cập nhật thông báo khi nhân viên reply ─────── */
if ($action === 'push_reply_notif') {
    if (!in_array($role, ['Admin','Employee'])) json_err('Không có quyền.');

    $review_id  = (int)($_POST['review_id']  ?? 0);
    $reply_text = trim($_POST['reply_text']  ?? '');
    $staff_name = trim($_POST['staff_name']  ?? ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Nhân viên Elite Gym'));

    if (!$review_id) json_err('review_id không hợp lệ.');

    $rv = $conn->query("SELECT customer_id FROM Review WHERE review_id=$review_id LIMIT 1")->fetch_assoc();
    if (!$rv) json_err('Không tìm thấy đánh giá.');
    $cid = (int)$rv['customer_id'];

    $title   = 'Elite Gym đã phản hồi đánh giá của bạn';
    $preview = mb_strlen($reply_text) > 80 ? mb_substr($reply_text,0,80).'…' : $reply_text;
    $msg     = htmlspecialchars($staff_name).' phản hồi: "'.htmlspecialchars($preview).'"';

    /* Nếu đã có thông báo cho review này → update (tránh spam) */
    $exist = $conn->query("
        SELECT notif_id FROM CustomerNotification
        WHERE customer_id=$cid AND type='review_reply' AND related_id=$review_id LIMIT 1
    ")->fetch_assoc();

    if ($exist) {
        $nid = (int)$exist['notif_id'];
        $st  = $conn->prepare(
            "UPDATE CustomerNotification SET title=?,message=?,is_read=0,created_at=NOW() WHERE notif_id=?"
        );
        $st->bind_param('ssi',$title,$msg,$nid);
    } else {
        $st = $conn->prepare(
            "INSERT INTO CustomerNotification (customer_id,type,title,message,related_id) VALUES (?,?,?,?,?)"
        );
        $t = 'review_reply';
        $st->bind_param('isssi',$cid,$t,$title,$msg,$review_id);
    }
    $st->execute(); $st->close();
    json_ok(['msg'=>'Thông báo đã gửi tới khách hàng.']);
}

json_err('Hành động không hợp lệ.');
