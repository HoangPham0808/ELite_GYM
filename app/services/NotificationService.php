<?php
/**
 * NotificationService.php
 * Vị trí: app/services/NotificationService.php
 *
 * Thêm 2 method public mới:
 *   runAutoCheckFull() — dùng bởi cron (không cần session)
 *   pushExpiring()     — thông báo gói sắp hết hạn
 */

class NotificationService
{
    public function __construct(
        private \mysqli      $db,
        private ?MailService $mail = null
    ) {
        if ($this->mail === null) {
            try { $this->mail = new MailService(); } catch (\Throwable) {}
        }
        $this->ensureTable();
    }

    // ══════════════════════════════════════════════════════════
    // CRON: Chạy đầy đủ cho 1 customer (không cần session)
    // ══════════════════════════════════════════════════════════
    public function runAutoCheckFull(
        int    $cid,
        int    $account_id,
        string $name,
        string $email,
        string $today,
        string $now
    ): array {
        $result = ['in_app' => 0, 'email' => 0, 'errors' => []];

        if (!$this->hasActivePlan($cid)) return $result;

        $hour = (int)date('H');

        // 1. Chưa đăng ký ca tập (sau 9:00)
        if ($hour >= 6) {
            $has_class = $this->db->query("
                SELECT 1 FROM classregistration ce
                JOIN trainingclass tc ON tc.class_id = ce.class_id
                WHERE ce.customer_id = $cid
                  AND DATE(tc.start_time) = '$today'
                LIMIT 1
            ")->fetch_assoc();

            if (!$has_class) {
                $title = 'Chưa đăng ký ca tập hôm nay';
                $msg   = "Hôm nay (" . date('d/m/Y') . ") bạn chưa đăng ký ca tập nào. Hãy liên hệ lễ tân hoặc đặt lịch ngay!";
                if ($this->insertNotif($account_id, 'no_registration', $title, $msg, $today, $now)) {
                    $result['in_app']++;
                    if ($this->sendEmail($email, $name, $title, $msg, 'no_registration'))
                        $result['email']++;
                    else if (!empty($email))
                        $result['errors'][] = "email no_registration";
                }
            }
        }

        // 2. Vắng ca tập
        $absent = $this->db->query("
            SELECT tc.class_id,
                   CONCAT(tc.class_name, ' ', DATE_FORMAT(tc.start_time,'%H:%i')) AS class_info
            FROM classregistration ce
            JOIN trainingclass tc ON tc.class_id = ce.class_id
            LEFT JOIN gymcheckin qr ON qr.customer_id = $cid
                AND DATE(qr.check_time) = '$today'
                AND qr.type = 'checkin'
            WHERE ce.customer_id = $cid
              AND DATE(tc.start_time) = '$today'
              AND tc.end_time < NOW()
              AND qr.checkin_id IS NULL
            LIMIT 3
        ")->fetch_all(MYSQLI_ASSOC);

        foreach ($absent as $row) {
            $type  = 'absent_' . $row['class_id'];
            $title = 'Vắng ca tập';
            $msg   = "Bạn đã vắng ca tập: {$row['class_info']}. Liên hệ lễ tân nếu cần hỗ trợ.";
            if ($this->insertNotif($account_id, $type, $title, $msg, $today, $now)) {
                $result['in_app']++;
                if ($this->sendEmail($email, $name, $title, $msg, 'absent'))
                    $result['email']++;
                else if (!empty($email))
                    $result['errors'][] = "email absent class={$row['class_id']}";
            }
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════
    // CRON: Gói sắp hết hạn
    // ══════════════════════════════════════════════════════════
    public function pushExpiring(
        int    $cid,
        int    $account_id,
        string $name,
        string $email,
        string $type,
        string $msg,
        string $expiry_fmt,
        int    $days_left,
        string $today,
        string $now
    ): array {
        $result = ['in_app' => 0, 'email' => 0];
        if (!$this->insertNotif($account_id, $type, 'Gói tập sắp hết hạn', $msg, $today, $now))
            return $result;

        $result['in_app']++;

        // Email template gói hết hạn
        if (!empty($email) && $this->mail) {
            try {
                $body_extra = BASE_URL . '/payment';
                $ok = $this->mail->sendNotification(
                    $email, $name,
                    "Gói tập sắp hết hạn ({$days_left} ngày)",
                    $msg . "\n\nGia hạn tại: " . $body_extra,
                    'expiring'
                );
                if ($ok) $result['email']++;
            } catch (\Throwable) {}
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════
    // WEB: Gọi từ notification_ui.php (có session)
    // ══════════════════════════════════════════════════════════
    public function runAutoCheck(int $customerId): array
    {
        if (!$this->hasActivePlan($customerId)) return ['skipped' => 'no_active_plan'];

        $row = $this->db->query("
            SELECT c.full_name, c.email, a.account_id
            FROM customer c JOIN account a ON a.account_id = c.account_id
            WHERE c.customer_id = $customerId LIMIT 1
        ")->fetch_assoc();

        if (!$row) return ['skipped' => 'customer_not_found'];

        return $this->runAutoCheckFull(
            $customerId,
            (int)$row['account_id'],
            $row['full_name'],
            $row['email'] ?? '',
            date('Y-m-d'),
            date('Y-m-d H:i:s')
        );
    }

    // ══════════════════════════════════════════════════════════
    // Review reply notification (giữ nguyên như cũ)
    // ══════════════════════════════════════════════════════════
    public function pushReplyNotif(int $reviewId, string $replyText, string $staffName, int $customerId): void
    {
        $title   = 'Elite Gym đã phản hồi đánh giá của bạn';
        $preview = mb_strlen($replyText) > 80 ? mb_substr($replyText, 0, 80) . '…' : $replyText;
        $message = htmlspecialchars($staffName) . ' phản hồi: "' . htmlspecialchars($preview) . '"';

        $row = $this->db->query("
            SELECT c.full_name, c.email, a.account_id
            FROM customer c JOIN account a ON a.account_id = c.account_id
            WHERE c.customer_id = $customerId LIMIT 1
        ")->fetch_assoc();
        if (!$row) return;

        $acc_id = (int)$row['account_id'];

        // Upsert (update nếu đã có, insert nếu chưa)
        $exist = $this->db->query("
            SELECT id FROM notification
            WHERE account_id = $acc_id AND type = 'review_reply'
              AND message LIKE '%review_id:{$reviewId}%'
            LIMIT 1
        ")->fetch_assoc();

        if ($exist) {
            $nid = (int)$exist['id'];
            $st  = $this->db->prepare("UPDATE notification SET title=?,message=?,is_read=0,created_at=NOW() WHERE id=?");
            $st->bind_param('ssi', $title, $message, $nid);
            $st->execute(); $st->close();
        } else {
            $full_msg = $message . " [review_id:{$reviewId}]";
            $st = $this->db->prepare("INSERT INTO notification (account_id,type,title,message,is_read,created_at) VALUES (?,'review_reply',?,?,0,NOW())");
            $st->bind_param('iss', $acc_id, $title, $full_msg);
            $st->execute(); $st->close();
            // Gửi email
            $this->sendEmail($row['email'] ?? '', $row['full_name'], $title, $message, 'review_reply');
        }
    }

    // ══════════════════════════════════════════════════════════
    // PRIVATE helpers
    // ══════════════════════════════════════════════════════════
    private function hasActivePlan(int $cid): bool
    {
        $today = date('Y-m-d');
        $st = $this->db->prepare("SELECT 1 FROM membershipregistration WHERE customer_id=? AND status='active' AND end_date>=? LIMIT 1");
        $st->bind_param('is', $cid, $today); $st->execute();
        $ok = (bool)$st->get_result()->fetch_assoc(); $st->close();
        return $ok;
    }

    private function insertNotif(int $account_id, string $type, string $title, string $msg, string $today, string $now): bool
    {
        $db = $this->db;
        // Tránh trùng: check đã gửi hôm nay chưa
        $chk = $db->prepare("SELECT 1 FROM notification WHERE account_id=? AND type=? AND DATE(created_at)=? LIMIT 1");
        $chk->bind_param('iss', $account_id, $type, $today);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) { $chk->close(); return false; }
        $chk->close();

        $st = $db->prepare("INSERT INTO notification (account_id,type,title,message,is_read,created_at) VALUES (?,?,?,?,0,?)");
        $st->bind_param('issss', $account_id, $type, $title, $msg, $now);
        $ok = $st->execute(); $st->close();
        return $ok;
    }

    private function sendEmail(string $email, string $name, string $title, string $message, string $type): bool
    {
        if (!$this->mail || empty($email)) return false;
        try {
            return $this->mail->sendNotification($email, $name, $title, $message, $type);
        } catch (\Throwable $e) {
            error_log('[EliteGym] NotificationService::sendEmail: ' . $e->getMessage());
            return false;
        }
    }

    private function ensureTable(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `notification` (
            `id`   INT NOT NULL AUTO_INCREMENT,
            `account_id` INT NOT NULL,
            `type`       VARCHAR(60) NOT NULL DEFAULT 'general',
            `title`      VARCHAR(200) NOT NULL,
            `message`    TEXT NOT NULL,
            `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_acc_read` (`account_id`, `is_read`),
            KEY `idx_type_date` (`account_id`, `type`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
