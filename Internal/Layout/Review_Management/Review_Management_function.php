<?php
/**
 * Review_Management_function.php
 * Xử lý tất cả thao tác DB cho trang quản lý đánh giá
 */

require_once __DIR__ . '/../../../Database/db.php';

/* ═══════════════════════════════════
   HELPERS
═══════════════════════════════════ */
function rm_json_ok($data = [])  { header('Content-Type: application/json'); echo json_encode(['ok' => true,  'data' => $data]); exit; }
function rm_json_err($msg)       { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg'  => $msg]);  exit; }

/* ═══════════════════════════════════
   AJAX HANDLER
═══════════════════════════════════ */
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_GET['ajax'])) {
    global $conn;
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    /* ── Lấy danh sách reviews ── */
    if ($action === 'get_reviews') {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $limit   = 10;
        $offset  = ($page - 1) * $limit;
        $rating  = (int)($_GET['rating'] ?? 0);
        $replied = $_GET['replied'] ?? 'all'; // all | yes | no
        $search  = trim($_GET['search'] ?? '');

        $where = ['1=1'];
        $params = [];
        $types  = '';

        if ($rating >= 1 && $rating <= 5) {
            $where[] = 'r.rating = ?';
            $params[] = $rating;
            $types .= 'i';
        }
        if ($replied === 'yes') {
            $where[] = 'r.staff_reply IS NOT NULL';
        } elseif ($replied === 'no') {
            $where[] = 'r.staff_reply IS NULL';
        }
        if ($search !== '') {
            $where[] = '(c.full_name LIKE ? OR r.content LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $types .= 'ss';
        }

        $whereSQL = implode(' AND ', $where);

        $countSt = $conn->prepare("SELECT COUNT(*) AS c FROM Review r JOIN Customer c ON c.customer_id = r.customer_id WHERE $whereSQL");
        if ($types) $countSt->bind_param($types, ...$params);
        $countSt->execute();
        $total = (int)$countSt->get_result()->fetch_assoc()['c'];
        $countSt->close();

        $st = $conn->prepare("
            SELECT r.review_id, r.content, r.rating, r.review_date,
                   r.staff_reply, r.staff_reply_by, r.staff_replied_at,
                   c.full_name, c.customer_id, c.email
            FROM Review r
            JOIN Customer c ON c.customer_id = r.customer_id
            WHERE $whereSQL
            ORDER BY r.review_date DESC
            LIMIT ? OFFSET ?
        ");
        $allParams = $params;
        $allParams[] = $limit;
        $allParams[] = $offset;
        $allTypes = $types . 'ii';
        $st->bind_param($allTypes, ...$allParams);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        /* Stats */
        $avg  = $conn->query("SELECT ROUND(AVG(rating),1) AS a FROM Review")->fetch_assoc()['a'] ?? 0;
        $dist = $conn->query("SELECT rating, COUNT(*) AS cnt FROM Review GROUP BY rating ORDER BY rating DESC")->fetch_all(MYSQLI_ASSOC);
        $total_all = $conn->query("SELECT COUNT(*) AS c FROM Review")->fetch_assoc()['c'];
        $replied_count = $conn->query("SELECT COUNT(*) AS c FROM Review WHERE staff_reply IS NOT NULL")->fetch_assoc()['c'];

        rm_json_ok([
            'reviews'       => $rows,
            'total'         => $total,
            'page'          => $page,
            'pages'         => (int)ceil($total / $limit),
            'stats'         => [
                'avg'          => (float)$avg,
                'total'        => (int)$total_all,
                'replied'      => (int)$replied_count,
                'pending'      => (int)$total_all - (int)$replied_count,
                'dist'         => $dist,
            ],
        ]);
    }

    /* ── Phản hồi / cập nhật reply ── */
    if ($action === 'reply') {
        $rid   = (int)($_POST['review_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        $by    = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';

        if ($rid <= 0)             rm_json_err('Review ID không hợp lệ.');
        if (mb_strlen($reply) < 5) rm_json_err('Phản hồi quá ngắn.');

        $st = $conn->prepare("UPDATE Review SET staff_reply=?, staff_reply_by=?, staff_replied_at=NOW() WHERE review_id=?");
        $st->bind_param('ssi', $reply, $by, $rid);
        if (!$st->execute()) rm_json_err('Lỗi DB.');
        $st->close();

        /* ── Gửi thông báo cho khách hàng ── */
        rm_push_reply_notif($conn, $rid, $reply, $by);

        rm_json_ok(['msg' => 'Đã lưu phản hồi và thông báo cho khách hàng.']);
    }

    /* ── Xóa reply ── */
    if ($action === 'delete_reply') {
        $rid = (int)($_POST['review_id'] ?? 0);
        if ($rid <= 0) rm_json_err('Review ID không hợp lệ.');
        $st = $conn->prepare("UPDATE Review SET staff_reply=NULL, staff_reply_by=NULL, staff_replied_at=NULL WHERE review_id=?");
        $st->bind_param('i', $rid);
        $st->execute() ? rm_json_ok(['msg' => 'Đã xóa phản hồi.']) : rm_json_err('Lỗi DB.');
    }

    /* ── Xóa review ── */
    if ($action === 'delete_review') {
        $rid = (int)($_POST['review_id'] ?? 0);
        if ($rid <= 0) rm_json_err('Review ID không hợp lệ.');
        $st = $conn->prepare("DELETE FROM Review WHERE review_id=?");
        $st->bind_param('i', $rid);
        $st->execute() ? rm_json_ok(['msg' => 'Đã xóa đánh giá.']) : rm_json_err('Lỗi DB.');
    }

    rm_json_err('Action không hợp lệ.');
}


/* ══════════════════════════════════════════════════════
   HELPER: Tạo/cập nhật thông báo cho khách hàng
   (Không dùng FK để tránh constraint lỗi trên WAMP)
══════════════════════════════════════════════════════ */
function rm_push_reply_notif($conn, $review_id, $reply_text, $staff_name) {
    /* Tạo bảng nếu chưa có — không FK */
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

    /* Xoá FK cũ nếu còn tồn tại (gây lỗi INSERT) */
    try { $conn->query("ALTER TABLE `CustomerNotification` DROP FOREIGN KEY `fk_cn_customer`"); }
    catch (\mysqli_sql_exception $_e) { /* không có FK — bỏ qua */ }

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
        WHERE customer_id = $cid AND type = 'review_reply' AND related_id = " . (int)$review_id . " LIMIT 1
    ")->fetch_assoc();

    if ($exist) {
        $nid = (int)$exist['notif_id'];
        $st  = $conn->prepare(
            "UPDATE CustomerNotification SET title=?, message=?, is_read=0, created_at=NOW() WHERE notif_id=?"
        );
        $st->bind_param('ssi', $title, $msg, $nid);
    } else {
        $st  = $conn->prepare(
            "INSERT INTO CustomerNotification (customer_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)"
        );
        $t = 'review_reply';
        $st->bind_param('isssi', $cid, $t, $title, $msg, $review_id);
    }
    $st->execute();
    $st->close();
}
