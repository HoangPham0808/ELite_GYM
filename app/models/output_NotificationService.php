<?php
/**
 * NotificationService.php
 * Vị trí: app/models/NotificationService.php
 *
 * - Ghi thông báo vào DB (CustomerNotification)
 * - Tự động gửi email qua MailService khi có thông báo mới
 */

class NotificationService
{
    public function __construct(
        private \mysqli     $db,
        private ?MailService $mail = null
    ) {
        // MailService tự khởi tạo nếu không truyền vào
        if ($this->mail === null) {
            try { $this->mail = new MailService(); }
            catch (\Throwable) { $this->mail = null; }
        }
        $this->ensureTable();
    }

    // ══════════════════════════════════════════════════════════
    // 1. Kiểm tra & tạo thông báo tự động (vắng ca, chưa đăng ký)
    // ══════════════════════════════════════════════════════════
    public function runAutoCheck(int $customerId): array
    {
        if (!$this->hasActivePlan($customerId)) return ['skipped' => 'no_active_plan'];

        $now     = new \DateTime();
        $today   = $now->format('Y-m-d');
        $results = [];

        // Chưa đăng ký ca hôm nay (sau 09:00)
        if ((int)$now->format('H') >= 9) {
            $results['no_reg'] = $this->checkNoRegistration($customerId, $today, $now);
        }

        // Vắng ca tập 7 ngày qua
        $results['absent_pushed'] = $this->checkAbsent($customerId, $today);

        return $results;
    }

    // ══════════════════════════════════════════════════════════
    // 2. Push thông báo reply review → ghi DB + gửi email
    // ══════════════════════════════════════════════════════════
    public function pushReplyNotif(int $reviewId, string $replyText, string $staffName, int $customerId): void
    {
        $title   = 'Elite Gym đã phản hồi đánh giá của bạn';
        $preview = mb_strlen($replyText) > 80 ? mb_substr($replyText, 0, 80) . '…' : $replyText;
        $message = htmlspecialchars($staffName) . ' phản hồi: "' . htmlspecialchars($preview) . '"';

        $isNew = $this->upsertReplyNotif($customerId, $reviewId, $title, $message);

        // Gửi email nếu là thông báo mới
        if ($isNew) {
            $this->sendEmail($customerId, $title, $message, 'review_reply');
        }
    }

    // ══════════════════════════════════════════════════════════
    // PRIVATE: Logic helpers
    // ══════════════════════════════════════════════════════════

    private function hasActivePlan(int $cid): bool
    {
        $today = date('Y-m-d');
        $st = $this->db->prepare("SELECT 1 FROM MembershipRegistration WHERE customer_id=? AND status='active' AND end_date>=? LIMIT 1");
        $st->bind_param('is', $cid, $today); $st->execute();
        $ok = (bool)$st->get_result()->fetch_assoc(); $st->close();
        return $ok;
    }

    private function checkNoRegistration(int $cid, string $today, \DateTime $now): string
    {
        $st = $this->db->prepare("SELECT COUNT(*) cnt FROM ClassRegistration cr JOIN TrainingClass tc ON tc.class_id=cr.class_id WHERE cr.customer_id=? AND DATE(tc.start_time)=?");
        $st->bind_param('is', $cid, $today); $st->execute();
        $count = (int)$st->get_result()->fetch_assoc()['cnt']; $st->close();

        if ($count === 0) {
            $title = 'Chưa đăng ký ca tập hôm nay';
            $msg   = 'Hôm nay (' . $now->format('d/m/Y') . ') bạn chưa đăng ký ca tập nào. Hãy liên hệ lễ tân hoặc đặt lịch ngay!';
            $result = $this->push($cid, 'no_registration', $title, $msg, null);
            if ($result === 'inserted') $this->sendEmail($cid, $title, $msg, 'no_registration');
            return $result;
        }

        $this->remove($cid, 'no_registration', null);
        return 'removed';
    }

    private function checkAbsent(int $cid, string $today): int
    {
        $sevenDaysAgo = (new \DateTime())->modify('-7 days')->format('Y-m-d');
        $pushed = 0;

        $st = $this->db->prepare("
            SELECT tc.class_id, tc.class_name, tc.start_time, tc.end_time, e.full_name AS trainer_name
            FROM ClassRegistration cr
            JOIN TrainingClass tc ON tc.class_id=cr.class_id
            LEFT JOIN Employee e  ON e.employee_id=tc.trainer_id
            WHERE cr.customer_id=? AND tc.end_time IS NOT NULL AND tc.end_time<=NOW() AND DATE(tc.start_time)>=?
            ORDER BY tc.start_time DESC LIMIT 30
        ");
        $st->bind_param('is', $cid, $sevenDaysAgo); $st->execute();
        $classes = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

        foreach ($classes as $cls) {
            $classId = (int)$cls['class_id'];
            if ($this->hasCheckIn($cid, $cls['start_time'], $cls['end_time'])) {
                $this->remove($cid, 'absent', $classId); continue;
            }

            $dtStart = new \DateTime($cls['start_time']);
            $dtEnd   = new \DateTime($cls['end_time']);
            $label   = $dtStart->format('Y-m-d') === $today ? 'hôm nay' : 'ngày ' . $dtStart->format('d/m/Y');
            $title   = 'Vắng ca tập: ' . $cls['class_name'];
            $msg     = sprintf(
                'Bạn đã đăng ký lớp "%s" %s (%s – %s) nhưng không check-in.%s Liên hệ lễ tân nếu cần hỗ trợ.',
                $cls['class_name'], $label, $dtStart->format('H:i'), $dtEnd->format('H:i'),
                $cls['trainer_name'] ? ' HLV: ' . $cls['trainer_name'] . '.' : ''
            );

            $result = $this->push($cid, 'absent', $title, $msg, $classId);
            if ($result === 'inserted') {
                $this->sendEmail($cid, $title, $msg, 'absent');
                $pushed++;
            }
        }
        return $pushed;
    }

    private function hasCheckIn(int $cid, string $start, string $end): bool
    {
        try {
            $st = $this->db->prepare("SELECT COUNT(*) c FROM GymCheckIn WHERE customer_id=? AND type='checkin' AND check_time BETWEEN DATE_SUB(?,INTERVAL 60 MINUTE) AND DATE_ADD(?,INTERVAL 60 MINUTE)");
            $st->bind_param('iss', $cid, $start, $end); $st->execute();
            if ((int)$st->get_result()->fetch_assoc()['c'] > 0) return true;
        } catch (\Throwable) {}
        return false;
    }

    // ── DB helpers ────────────────────────────────────────────

    private function push(int $cid, string $type, string $title, string $msg, ?int $relatedId): string
    {
        try {
            $ex = $relatedId !== null
                ? $this->db->query("SELECT notif_id FROM CustomerNotification WHERE customer_id=$cid AND type='$type' AND related_id=$relatedId LIMIT 1")->fetch_assoc()
                : $this->db->query("SELECT notif_id FROM CustomerNotification WHERE customer_id=$cid AND type='$type' AND DATE(created_at)=CURDATE() LIMIT 1")->fetch_assoc();
        } catch (\Throwable) { return 'error'; }
        if ($ex) return 'exists';

        $ridVal = $relatedId !== null ? (string)(int)$relatedId : null;
        $st = $this->db->prepare("INSERT INTO CustomerNotification (customer_id,type,title,message,related_id,is_read) VALUES (?,?,?,?,?,0)");
        $st->bind_param('issss', $cid, $type, $title, $msg, $ridVal);
        return $st->execute() ? 'inserted' : 'error';
    }

    private function remove(int $cid, string $type, ?int $relatedId): void
    {
        try {
            if ($relatedId !== null) {
                $this->db->query("DELETE FROM CustomerNotification WHERE customer_id=$cid AND type='$type' AND related_id=$relatedId");
            } else {
                $this->db->query("DELETE FROM CustomerNotification WHERE customer_id=$cid AND type='$type' AND DATE(created_at)=CURDATE()");
            }
        } catch (\Throwable) {}
    }

    private function upsertReplyNotif(int $cid, int $reviewId, string $title, string $msg): bool
    {
        $exist = $this->db->query("SELECT notif_id FROM CustomerNotification WHERE customer_id=$cid AND type='review_reply' AND related_id=$reviewId LIMIT 1")->fetch_assoc();
        if ($exist) {
            $nid = (int)$exist['notif_id'];
            $st  = $this->db->prepare("UPDATE CustomerNotification SET title=?,message=?,is_read=0,created_at=NOW() WHERE notif_id=?");
            $st->bind_param('ssi', $title, $msg, $nid); $st->execute(); $st->close();
            return false; // not new
        }
        $st = $this->db->prepare("INSERT INTO CustomerNotification (customer_id,type,title,message,related_id,is_read) VALUES (?,'review_reply',?,?,?,0)");
        $st->bind_param('issi', $cid, $title, $msg, $reviewId); $st->execute(); $st->close();
        return true; // is new
    }

    private function ensureTable(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `CustomerNotification` (
            `notif_id`    INT NOT NULL AUTO_INCREMENT,
            `customer_id` INT NOT NULL,
            `type`        VARCHAR(50) NOT NULL DEFAULT 'review_reply',
            `title`       VARCHAR(200) NOT NULL,
            `message`     TEXT NOT NULL,
            `related_id`  INT NULL DEFAULT NULL,
            `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`notif_id`),
            KEY `idx_cust_read` (`customer_id`, `is_read`),
            KEY `idx_type_related` (`customer_id`, `type`, `related_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { $this->db->query("ALTER TABLE `CustomerNotification` DROP FOREIGN KEY `fk_cn_customer`"); } catch (\Throwable) {}
    }

    // ══════════════════════════════════════════════════════════
    // EMAIL: Lấy email khách → gửi qua MailService
    // ══════════════════════════════════════════════════════════
    private function sendEmail(int $cid, string $title, string $message, string $type): void
    {
        if (!$this->mail) return;

        try {
            $row = $this->db->query("SELECT full_name, email FROM Customer WHERE customer_id=$cid LIMIT 1")->fetch_assoc();
            if (!$row || empty($row['email'])) return;

            $this->mail->sendNotification(
                $row['email'],
                $row['full_name'] ?? 'Khách hàng',
                $title,
                $message,
                $type
            );
        } catch (\Throwable $e) {
            error_log('[EliteGym] NotificationService::sendEmail failed: ' . $e->getMessage());
        }
    }
}
