<?php

class Review extends Model
{
    // ==========================================
    // REVIEWS DB LOGIC
    // ==========================================

    public function submitReview(int $cid, string $content, int $rating): array
    {
        // Giới hạn 1 review / 30 ngày
        $chk = $this->db->prepare("SELECT review_id FROM Review WHERE customer_id=? AND review_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) LIMIT 1");
        $chk->bind_param('i', $cid);
        $chk->execute();
        $res = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($res) {
            return ['success' => false, 'message' => 'Bạn đã gửi đánh giá trong 30 ngày qua.'];
        }

        $date = date('Y-m-d');
        $ins  = $this->db->prepare("INSERT INTO Review (customer_id, content, rating, review_date) VALUES (?, ?, ?, ?)");
        $ins->bind_param('isis', $cid, $content, $rating, $date);
        $ok = $ins->execute();
        $new_id = $ok ? $this->db->insert_id : 0;
        $ins->close();

        if ($ok) {
            return ['success' => true, 'review_id' => $new_id, 'message' => 'Cảm ơn bạn đã gửi đánh giá!'];
        }
        return ['success' => false, 'message' => 'Lỗi lưu đánh giá.'];
    }

    public function replyReview(int $rid, string $reply, string $by): bool
    {
        // Tự động migrate cột reply nếu chưa có
        $this->ensureReplyColumns();

        $up = $this->db->prepare("UPDATE Review SET staff_reply=?, staff_reply_by=?, staff_replied_at=NOW() WHERE review_id=?");
        $up->bind_param('ssi', $reply, $by, $rid);
        $ok = $up->execute();
        $up->close();

        if ($ok) {
            $this->pushReplyNotif($rid, $reply, $by);
        }
        return $ok;
    }

    public function deleteReply(int $rid): bool
    {
        $st = $this->db->prepare("UPDATE Review SET staff_reply=NULL, staff_reply_by=NULL, staff_replied_at=NULL WHERE review_id=?");
        $st->bind_param('i', $rid);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }

    public function deleteReview(int $rid): bool
    {
        // Xóa thông báo liên quan trước
        $this->db->query("DELETE FROM CustomerNotification WHERE type='review_reply' AND related_id=$rid");

        $st = $this->db->prepare("DELETE FROM Review WHERE review_id=?");
        $st->bind_param('i', $rid);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }

    public function getReviewsList(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        $total = (int)$this->db->query("SELECT COUNT(*) c FROM Review")->fetch_assoc()['c'];

        $rows = $this->db->query("
            SELECT r.review_id, r.content, r.rating, r.review_date,
                   r.staff_reply, r.staff_reply_by, r.staff_replied_at,
                   c.full_name
            FROM Review r
            JOIN Customer c ON c.customer_id = r.customer_id
            ORDER BY r.review_date DESC
            LIMIT $limit OFFSET $offset
        ")->fetch_all(MYSQLI_ASSOC);

        $avg  = $this->db->query("SELECT AVG(rating) a FROM Review")->fetch_assoc()['a'];
        $dist = $this->db->query("SELECT rating, COUNT(*) cnt FROM Review GROUP BY rating ORDER BY rating DESC")->fetch_all(MYSQLI_ASSOC);

        return [
            'reviews' => $rows,
            'total'   => $total,
            'page'    => $page,
            'pages'   => (int)ceil($total / $limit),
            'avg'     => round((float)$avg, 1),
            'dist'    => $dist,
        ];
    }

    public function getReviewsAdmin(int $page, int $limit, int $rating, string $replied, string $search): array
    {
        $offset = ($page - 1) * $limit;
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

        $countSt = $this->db->prepare("SELECT COUNT(*) AS c FROM Review r JOIN Customer c ON c.customer_id = r.customer_id WHERE $whereSQL");
        if ($types) $countSt->bind_param($types, ...$params);
        $countSt->execute();
        $total = (int)$countSt->get_result()->fetch_assoc()['c'];
        $countSt->close();

        $st = $this->db->prepare("
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

        // Stats
        $avg  = $this->db->query("SELECT ROUND(AVG(rating),1) AS a FROM Review")->fetch_assoc()['a'] ?? 0;
        $dist = $this->db->query("SELECT rating, COUNT(*) AS cnt FROM Review GROUP BY rating ORDER BY rating DESC")->fetch_all(MYSQLI_ASSOC);
        $total_all = $this->db->query("SELECT COUNT(*) AS c FROM Review")->fetch_assoc()['c'];
        $replied_count = $this->db->query("SELECT COUNT(*) AS c FROM Review WHERE staff_reply IS NOT NULL")->fetch_assoc()['c'];

        return [
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
            ]
        ];
    }

    private function ensureReplyColumns(): void
    {
        $cols = ['staff_reply', 'staff_reply_by', 'staff_replied_at'];
        $exist_res = $this->db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='datn' AND TABLE_NAME='Review'");
        $exist_cols = [];
        if ($exist_res) {
            while ($ec = $exist_res->fetch_assoc()) {
                $exist_cols[] = $ec['COLUMN_NAME'];
            }
        }
        if (!in_array('staff_reply', $exist_cols)) {
            $this->db->query("ALTER TABLE `Review` ADD COLUMN `staff_reply` VARCHAR(300) NULL DEFAULT NULL");
        }
        if (!in_array('staff_reply_by', $exist_cols)) {
            $this->db->query("ALTER TABLE `Review` ADD COLUMN `staff_reply_by` VARCHAR(100) NULL DEFAULT NULL");
        }
        if (!in_array('staff_replied_at', $exist_cols)) {
            $this->db->query("ALTER TABLE `Review` ADD COLUMN `staff_replied_at` DATETIME NULL DEFAULT NULL");
        }
    }


    // ==========================================
    // NOTIFICATIONS LOGIC
    // ==========================================

    public function ensureNotificationTable(): void
    {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `CustomerNotification` (
                `notif_id`    INT          NOT NULL AUTO_INCREMENT,
                `customer_id` INT          NOT NULL,
                `type`        VARCHAR(50)  NOT NULL DEFAULT 'review_reply',
                `title`       VARCHAR(200) NOT NULL,
                `message`     TEXT         NOT NULL,
                `related_id`  INT          NULL DEFAULT NULL,
                `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
                `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`notif_id`),
                KEY `idx_cust_read`    (`customer_id`, `is_read`),
                KEY `idx_type_related` (`customer_id`, `type`, `related_id`),
                KEY `idx_created`      (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        try {
            $this->db->query("ALTER TABLE `CustomerNotification` DROP FOREIGN KEY `fk_cn_customer`");
        } catch (\Throwable $e) {}
    }

    public function getNotifications(int $cid, int $limit, int $offset): array
    {
        $this->ensureNotificationTable();
        $stmt = $this->db->prepare("
            SELECT notif_id, type, title, message, related_id, is_read,
                   created_at,
                   DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') AS time_fmt
            FROM   CustomerNotification
            WHERE  customer_id = ?
            ORDER  BY created_at DESC
            LIMIT  ? OFFSET ?
        ");
        $stmt->bind_param('iii', $cid, $limit, $offset);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $unread = (int)$this->db->query("SELECT COUNT(*) c FROM CustomerNotification WHERE customer_id=$cid AND is_read=0")->fetch_assoc()['c'];

        return ['notifications' => $rows, 'unread' => $unread];
    }

    public function getUnreadCount(int $cid): int
    {
        $this->ensureNotificationTable();
        return (int)$this->db->query("SELECT COUNT(*) c FROM CustomerNotification WHERE customer_id=$cid AND is_read=0")->fetch_assoc()['c'];
    }

    public function markRead(int $nid, int $cid): bool
    {
        $st = $this->db->prepare("UPDATE CustomerNotification SET is_read=1 WHERE notif_id=? AND customer_id=?");
        $st->bind_param('ii', $nid, $cid);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }

    public function markAllRead(int $cid): bool
    {
        return (bool)$this->db->query("UPDATE CustomerNotification SET is_read=1 WHERE customer_id=$cid");
    }

    public function pushReplyNotif(int $review_id, string $reply_text, string $staff_name): bool
    {
        $this->ensureNotificationTable();

        $rv = $this->db->query("SELECT customer_id FROM Review WHERE review_id=" . (int)$review_id . " LIMIT 1")->fetch_assoc();
        if (!$rv) return false;
        $cid = (int)$rv['customer_id'];

        $title   = 'Elite Gym đã phản hồi đánh giá của bạn';
        $preview = mb_strlen($reply_text) > 80 ? mb_substr($reply_text, 0, 80) . '…' : $reply_text;
        $msg     = htmlspecialchars($staff_name) . ' phản hồi: "' . htmlspecialchars($preview) . '"';

        $exist = $this->db->query("
            SELECT notif_id FROM CustomerNotification
            WHERE customer_id=$cid AND type='review_reply' AND related_id=" . (int)$review_id . " LIMIT 1
        ")->fetch_assoc();

        if ($exist) {
            $nid = (int)$exist['notif_id'];
            $st  = $this->db->prepare("UPDATE CustomerNotification SET title=?, message=?, is_read=0, created_at=NOW() WHERE notif_id=?");
            $st->bind_param('ssi', $title, $msg, $nid);
        } else {
            $st = $this->db->prepare("INSERT INTO CustomerNotification (customer_id, type, title, message, related_id) VALUES (?, 'review_reply', ?, ?, ?)");
            $st->bind_param('issi', $cid, $title, $msg, $review_id);
        }
        $ok = $st->execute();
        $st->close();
        return $ok;
    }

    public function pushNotifDirect(int $cid, string $type, string $title, string $msg, ?int $related_id): string
    {
        $this->ensureNotificationTable();
        try {
            if ($related_id !== null) {
                $rid = (int)$related_id;
                $ex  = $this->db->query("SELECT notif_id FROM CustomerNotification WHERE customer_id=$cid AND type='$type' AND related_id=$rid LIMIT 1")->fetch_assoc();
            } else {
                $ex = $this->db->query("SELECT notif_id FROM CustomerNotification WHERE customer_id=$cid AND type='$type' AND DATE(created_at)=CURDATE() LIMIT 1")->fetch_assoc();
            }
        } catch (\Throwable $e) {
            return 'error';
        }
        if ($ex) return 'exists';

        try {
            $st = $this->db->prepare("INSERT INTO CustomerNotification (customer_id, type, title, message, related_id, is_read) VALUES (?, ?, ?, ?, ?, 0)");
            $st->bind_param("isssi", $cid, $type, $title, $msg, $related_id);
            $ok  = $st->execute();
            $st->close();
            return $ok ? 'inserted' : 'error';
        } catch (\Throwable $e) {
            return 'error';
        }
    }

    public function removeNotif(int $cid, string $type, ?int $related_id): void
    {
        if ($related_id !== null) {
            $rid = (int)$related_id;
            $this->db->query("DELETE FROM CustomerNotification WHERE customer_id=$cid AND type='$type' AND related_id=$rid");
        } else {
            $this->db->query("DELETE FROM CustomerNotification WHERE customer_id=$cid AND type='$type' AND DATE(created_at)=CURDATE()");
        }
    }

    public function checkActivePlan(int $cid): bool
    {
        $today = date('Y-m-d');
        $ps = $this->db->prepare("SELECT 1 FROM MembershipRegistration WHERE customer_id=? AND status='active' AND end_date>=? LIMIT 1");
        $ps->bind_param("is", $cid, $today);
        $ps->execute();
        $hasActivePlan = (bool)$ps->get_result()->fetch_assoc();
        $ps->close();
        return $hasActivePlan;
    }

    public function checkTodayRegistrationAndPush(int $cid): string
    {
        $today = date('Y-m-d');
        $now = new DateTime();
        $rs = $this->db->prepare("SELECT COUNT(*) AS cnt FROM ClassRegistration cr JOIN TrainingClass tc ON tc.class_id=cr.class_id WHERE cr.customer_id=? AND DATE(tc.start_time)=?");
        $rs->bind_param("is", $cid, $today);
        $rs->execute();
        $regCount = (int)$rs->get_result()->fetch_assoc()['cnt'];
        $rs->close();

        if ($regCount === 0) {
            return $this->pushNotifDirect($cid, 'no_registration', 'Chưa đăng ký ca tập hôm nay',
                'Hôm nay (' . $now->format('d/m/Y') . ') bạn chưa đăng ký ca tập nào. Hãy liên hệ lễ tân hoặc đặt lịch ngay!', null);
        } else {
            $this->removeNotif($cid, 'no_registration', null);
            return 'removed';
        }
    }

    public function checkAbsentAndPush(int $cid): int
    {
        $sevenDaysAgo = (new DateTime())->modify('-7 days')->format('Y-m-d');
        $today = date('Y-m-d');
        $absentPushed = 0;

        $abs = $this->db->prepare("
            SELECT tc.class_id, tc.class_name, tc.start_time, tc.end_time, e.full_name AS trainer_name
            FROM   ClassRegistration cr
            JOIN   TrainingClass tc ON tc.class_id   = cr.class_id
            LEFT JOIN Employee   e  ON e.employee_id = tc.trainer_id
            WHERE  cr.customer_id      = ?
              AND  tc.end_time         IS NOT NULL
              AND  tc.end_time         <= NOW()
              AND  DATE(tc.start_time) >= ?
            ORDER  BY tc.start_time DESC
            LIMIT  30
        ");
        $abs->bind_param("is", $cid, $sevenDaysAgo);
        $abs->execute();
        $pastClasses = $abs->get_result()->fetch_all(MYSQLI_ASSOC);
        $abs->close();

        foreach ($pastClasses as $cls) {
            $classId = (int)$cls['class_id'];
            $ciCount = 0;

            // Check GymCheckIn
            $ci = $this->db->prepare("SELECT COUNT(*) AS cnt FROM GymCheckIn WHERE customer_id=? AND type='checkin' AND check_time BETWEEN DATE_SUB(?,INTERVAL 60 MINUTE) AND DATE_ADD(?,INTERVAL 60 MINUTE)");
            $ci->bind_param("iss", $cid, $cls['start_time'], $cls['end_time']);
            $ci->execute();
            $ciCount = (int)$ci->get_result()->fetch_assoc()['cnt'];
            $ci->close();

            // Check CheckInHistory
            if ($ciCount === 0) {
                $ci2 = $this->db->prepare("SELECT COUNT(*) AS cnt FROM CheckInHistory WHERE customer_id=? AND check_in BETWEEN DATE_SUB(?,INTERVAL 60 MINUTE) AND DATE_ADD(?,INTERVAL 60 MINUTE)");
                $ci2->bind_param("iss", $cid, $cls['start_time'], $cls['end_time']);
                $ci2->execute();
                $ciCount += (int)$ci2->get_result()->fetch_assoc()['cnt'];
                $ci2->close();
            }

            if ($ciCount === 0) {
                $dtStart = new DateTime($cls['start_time']);
                $dtEnd   = new DateTime($cls['end_time']);
                $label   = $dtStart->format('Y-m-d') === $today ? 'hôm nay' : 'ngày ' . $dtStart->format('d/m/Y');
                $msgText = sprintf(
                    'Bạn đã đăng ký lớp "%s" %s (%s – %s) nhưng không check-in.%s Liên hệ lễ tân nếu cần hỗ trợ.',
                    $cls['class_name'], $label, $dtStart->format('H:i'), $dtEnd->format('H:i'),
                    $cls['trainer_name'] ? ' HLV: ' . $cls['trainer_name'] . '.' : ''
                );
                $r = $this->pushNotifDirect($cid, 'absent', 'Vắng ca tập: ' . $cls['class_name'], $msgText, $classId);
                if ($r === 'inserted') $absentPushed++;
            } else {
                $this->removeNotif($cid, 'absent', $classId);
            }
        }

        return $absentPushed;
    }
}
