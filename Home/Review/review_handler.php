<?php
/**
 * review_handler.php — Elite Gym
 * Đặt cùng thư mục với index.php
 *
 * Actions POST: submit_review | reply_review
 * Actions GET : get_reviews
 *
 * Khi nhân viên reply → tự động gọi push_reply_notif
 * → tạo thông báo cho khách + bắn toast realtime
 */
session_start();
require_once '../../Database/db.php';

header('Content-Type: application/json; charset=utf-8');

/* ── Fix: xoá FK constraint cũ trên CustomerNotification nếu còn ── */
try { $conn->query("ALTER TABLE `CustomerNotification` DROP FOREIGN KEY `fk_cn_customer`"); }
catch (\mysqli_sql_exception $_e) { /* không tồn tại — bỏ qua */ }

/* ── Auto-migrate cột reply (safe on all MySQL/MariaDB + Xdebug) ── */
foreach ([
    "ALTER TABLE `Review` ADD COLUMN `staff_reply`      VARCHAR(300) NULL DEFAULT NULL",
    "ALTER TABLE `Review` ADD COLUMN `staff_reply_by`   VARCHAR(100) NULL DEFAULT NULL",
    "ALTER TABLE `Review` ADD COLUMN `staff_replied_at` DATETIME     NULL DEFAULT NULL",
] as $_sql) {
    try { $conn->query($_sql); } catch (\mysqli_sql_exception $_e) { /* 1060 duplicate column — already exists, safe to ignore */ }
}

function json_ok($data=[]) { echo json_encode(['ok'=>true,  'data'=>$data]); exit; }
function json_err($msg)    { echo json_encode(['ok'=>false, 'msg' =>$msg]);  exit; }

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$role   = $_SESSION['role'] ?? '';

/* ══ 1. Gửi đánh giá (Customer) ════════════════════════════ */
if ($action === 'submit_review') {
    $aid = (int)($_SESSION['account_id'] ?? 0);
    if ($role !== 'Customer' || !$aid) json_err('Bạn cần đăng nhập với tài khoản khách hàng.');

    $content = trim($_POST['content'] ?? '');
    $rating  = (int)($_POST['rating'] ?? 0);

    if ($rating < 1 || $rating > 5)    json_err('Vui lòng chọn số sao (1–5).');
    if (mb_strlen($content) < 10)       json_err('Nội dung tối thiểu 10 ký tự.');
    if (mb_strlen($content) > 500)      json_err('Nội dung tối đa 500 ký tự.');

    $st = $conn->prepare("SELECT customer_id FROM Customer WHERE account_id=? LIMIT 1");
    $st->bind_param('i',$aid); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    if (!$row) json_err('Không tìm thấy thông tin khách hàng.');
    $cid = (int)$row['customer_id'];

    /* Giới hạn 1 review / 30 ngày */
    $chk = $conn->prepare(
        "SELECT review_id FROM Review WHERE customer_id=? AND review_date >= DATE_SUB(CURDATE(),INTERVAL 30 DAY) LIMIT 1"
    );
    $chk->bind_param('i',$cid); $chk->execute();
    if ($chk->get_result()->fetch_assoc()) json_err('Bạn đã gửi đánh giá trong 30 ngày qua.');
    $chk->close();

    $date = date('Y-m-d');
    $ins  = $conn->prepare("INSERT INTO Review (customer_id,content,rating,review_date) VALUES (?,?,?,?)");
    $ins->bind_param('isis',$cid,$content,$rating,$date);
    if ($ins->execute()) { $new_id = $conn->insert_id; $ins->close(); json_ok(['review_id'=>$new_id, 'msg'=>'Cảm ơn bạn đã gửi đánh giá!']); }
    json_err('Lỗi lưu đánh giá.');
}

/* ══ 2. Nhân viên phản hồi ═════════════════════════════════ */
if ($action === 'reply_review') {
    if (!in_array($role,['Admin','Employee'])) json_err('Không có quyền phản hồi.');

    $review_id = (int)($_POST['review_id'] ?? 0);
    $reply     = trim($_POST['reply'] ?? '');
    $username  = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Nhân viên';

    if ($review_id <= 0)        json_err('Review không hợp lệ.');
    if (mb_strlen($reply) < 5)  json_err('Phản hồi tối thiểu 5 ký tự.');
    if (mb_strlen($reply) > 300) json_err('Phản hồi tối đa 300 ký tự.');

    $up = $conn->prepare(
        "UPDATE Review SET staff_reply=?,staff_reply_by=?,staff_replied_at=NOW() WHERE review_id=?"
    );
    $up->bind_param('ssi',$reply,$username,$review_id);
    if (!$up->execute()) json_err('Lỗi lưu phản hồi.');
    $up->close();

    /* ── Tự động tạo thông báo trực tiếp (không dùng cURL) ── */
    push_reply_notif($conn, $review_id, $reply, $username);

    json_ok(['msg' => 'Phản hồi đã được lưu và thông báo cho khách hàng.']);
}


/* ══ Helper: tạo/cập nhật thông báo cho khách hàng ════════ */
function push_reply_notif($conn, $review_id, $reply_text, $staff_name) {
    /* Bảng CustomerNotification — tạo nếu chưa có (không FK để tránh constraint lỗi) */
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
                KEY `idx_cust_read` (`customer_id`, `is_read`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\mysqli_sql_exception $_e) { /* already exists */ }

    /* Xoá FK cũ nếu tồn tại (từ lần migrate trước có FK gây lỗi insert) */
    try { $conn->query("ALTER TABLE `CustomerNotification` DROP FOREIGN KEY `fk_cn_customer`"); }
    catch (\mysqli_sql_exception $_e) { /* FK không tồn tại — bỏ qua */ }

    /* Lấy customer_id từ review */
    $rv = $conn->query("SELECT customer_id FROM Review WHERE review_id=" . (int)$review_id . " LIMIT 1")->fetch_assoc();
    if (!$rv) return;
    $cid = (int)$rv['customer_id'];

    $title   = 'Elite Gym đã phản hồi đánh giá của bạn';
    $preview = mb_strlen($reply_text) > 80 ? mb_substr($reply_text, 0, 80) . '…' : $reply_text;
    $msg     = htmlspecialchars($staff_name) . ' phản hồi: "' . htmlspecialchars($preview) . '"';

    /* Update nếu đã có, insert nếu chưa có */
    $exist = $conn->query("
        SELECT notif_id FROM CustomerNotification
        WHERE customer_id=$cid AND type='review_reply' AND related_id=" . (int)$review_id . " LIMIT 1
    ")->fetch_assoc();

    if ($exist) {
        $nid = (int)$exist['notif_id'];
        $st  = $conn->prepare(
            "UPDATE CustomerNotification SET title=?, message=?, is_read=0, created_at=NOW() WHERE notif_id=?"
        );
        $st->bind_param('ssi', $title, $msg, $nid);
    } else {
        $st = $conn->prepare(
            "INSERT INTO CustomerNotification (customer_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)"
        );
        $t = 'review_reply';
        $st->bind_param('isssi', $cid, $t, $title, $msg, $review_id);
    }
    if (!$st->execute()) {
        error_log('[EliteGym] push_reply_notif INSERT/UPDATE failed: ' . $st->errno . ' ' . $st->error . ' | cid=' . $cid . ' rid=' . (int)$review_id);
    }
    $st->close();
}

/* ══ 3. Lấy danh sách reviews (AJAX load) ══════════════════ */
if ($action === 'get_reviews') {
    $page   = max(1,(int)($_GET['page'] ?? 1));
    $limit  = 6;
    $offset = ($page-1)*$limit;

    $total = (int)$conn->query("SELECT COUNT(*) c FROM Review")->fetch_assoc()['c'];

    $rows = $conn->query("
        SELECT r.review_id, r.content, r.rating, r.review_date,
               r.staff_reply, r.staff_reply_by, r.staff_replied_at,
               c.full_name
        FROM Review r
        JOIN Customer c ON c.customer_id = r.customer_id
        ORDER BY r.review_date DESC
        LIMIT $limit OFFSET $offset
    ")->fetch_all(MYSQLI_ASSOC);

    $avg  = $conn->query("SELECT AVG(rating) a FROM Review")->fetch_assoc()['a'];
    $dist = $conn->query(
        "SELECT rating, COUNT(*) cnt FROM Review GROUP BY rating ORDER BY rating DESC"
    )->fetch_all(MYSQLI_ASSOC);

    json_ok([
        'reviews' => $rows,
        'total'   => $total,
        'page'    => $page,
        'pages'   => (int)ceil($total/$limit),
        'avg'     => round((float)$avg,1),
        'dist'    => $dist,
    ]);
}

json_err('Hành động không hợp lệ.');
