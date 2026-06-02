<?php

class QRCheckin extends Model
{
    public function getCustomerInfo(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT customer_id, full_name, phone, gender, email, date_of_birth FROM Customer WHERE customer_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ?: null;
    }

    public function getActivePackage(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT mr.end_date, mp.plan_name,
                CASE
                    WHEN mr.end_date >= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'active'
                    WHEN mr.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'expiring'
                    WHEN mr.end_date < CURDATE() THEN 'expired'
                    ELSE 'none'
                END AS pkg_status
            FROM MembershipRegistration mr
            JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
            WHERE mr.customer_id = ?
            ORDER BY mr.end_date DESC
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ?: null;
    }

    public function getTodayCheckinOut(int $id): array
    {
        $stmt = $this->db->prepare("
            SELECT
                MAX(CASE WHEN type = 'checkin'  THEN check_time END) AS last_checkin,
                MAX(CASE WHEN type = 'checkout' THEN check_time END) AS last_checkout
            FROM GymCheckIn
            WHERE customer_id = ? AND DATE(check_time) = CURDATE()
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $res ?: ['last_checkin' => null, 'last_checkout' => null];
    }

    public function getTodayClasses(int $id): array
    {
        $stmt = $this->db->prepare("
            SELECT
                tc.class_id,
                tc.class_name,
                tc.start_time,
                tc.end_time,
                gr.room_name,
                e.full_name AS trainer_name
            FROM ClassRegistration cr
            JOIN TrainingClass tc ON tc.class_id = cr.class_id
            LEFT JOIN GymRoom gr   ON gr.room_id   = tc.room_id
            LEFT JOIN Employee e   ON e.employee_id = tc.trainer_id
            WHERE cr.customer_id = ?
              AND DATE(tc.start_time) = CURDATE()
            ORDER BY tc.start_time ASC
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $res;
    }

    public function autoCheckoutIfFinished(int $id, array $todayClasses, string $lastCheckin, ?string $lastCheckout): bool
    {
        // Khách có đang trong gym không?
        $currentlyIn = false;
        if (!empty($lastCheckin)) {
            if (empty($lastCheckout)) {
                $currentlyIn = true;
            } else {
                $currentlyIn = strtotime($lastCheckin) > strtotime($lastCheckout);
            }
        }

        if ($currentlyIn && !empty($todayClasses)) {
            $latest_end = null;
            foreach ($todayClasses as $cls) {
                if (!$latest_end || $cls['end_time'] > $latest_end) {
                    $latest_end = $cls['end_time'];
                }
            }
            if ($latest_end && strtotime($latest_end) < time()) {
                $auto = $this->db->prepare("
                    INSERT INTO GymCheckIn (customer_id, type, check_time, recorded_by)
                    VALUES (?, 'checkout', ?, 0)
                ");
                $auto->bind_param('is', $id, $latest_end);
                $ok = $auto->execute();
                $auto->close();
                return $ok;
            }
        }
        return false;
    }

    public function hasUnresolvedCheckinToday(int $customerId): bool
    {
        $dupChk = $this->db->prepare("
            SELECT COUNT(*) AS c
            FROM GymCheckIn
            WHERE customer_id = ?
              AND type = 'checkin'
              AND DATE(check_time) = CURDATE()
              AND check_time > IFNULL(
                    (SELECT MAX(check_time) FROM GymCheckIn
                     WHERE customer_id = ? AND type = 'checkout' AND DATE(check_time) = CURDATE()),
                    '1970-01-01')
        ");
        $dupChk->bind_param('ii', $customerId, $customerId);
        $dupChk->execute();
        $count = $dupChk->get_result()->fetch_assoc()['c'];
        $dupChk->close();
        return $count > 0;
    }

    public function hasActivePackage(int $customerId): bool
    {
        $pkgChk = $this->db->prepare("
            SELECT COUNT(*) AS c FROM MembershipRegistration
            WHERE customer_id = ? AND end_date >= CURDATE()
        ");
        $pkgChk->bind_param('i', $customerId);
        $pkgChk->execute();
        $count = $pkgChk->get_result()->fetch_assoc()['c'];
        $pkgChk->close();
        return $count > 0;
    }

    public function getOngoingClass(int $customerId): ?array
    {
        $clsChk = $this->db->prepare("
            SELECT tc.class_id, tc.class_name, tc.start_time, tc.end_time
            FROM ClassRegistration cr
            JOIN TrainingClass tc ON tc.class_id = cr.class_id
            WHERE cr.customer_id = ?
              AND DATE(tc.start_time) = CURDATE()
              AND NOW() BETWEEN tc.start_time AND tc.end_time
            LIMIT 1
        ");
        $clsChk->bind_param('i', $customerId);
        $clsChk->execute();
        $res = $clsChk->get_result()->fetch_assoc();
        $clsChk->close();
        return $res ?: null;
    }

    public function getNextClass(int $customerId): ?array
    {
        $nextCls = $this->db->prepare("
            SELECT TIME_FORMAT(tc.start_time, '%H:%i') AS start_hm
            FROM ClassRegistration cr
            JOIN TrainingClass tc ON tc.class_id = cr.class_id
            WHERE cr.customer_id = ?
              AND DATE(tc.start_time) = CURDATE()
              AND tc.start_time > NOW()
            ORDER BY tc.start_time ASC
            LIMIT 1
        ");
        $nextCls->bind_param('i', $customerId);
        $nextCls->execute();
        $res = $nextCls->get_result()->fetch_assoc();
        $nextCls->close();
        return $res ?: null;
    }

    public function addCheckInRecord(int $customerId, string $type, int $recordedBy): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO GymCheckIn (customer_id, type, check_time, recorded_by)
            VALUES (?, ?, NOW(), ?)
        ");
        $stmt->bind_param('isi', $customerId, $type, $recordedBy);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getTodayStats(): array
    {
        $total_in  = (int)$this->db->query("SELECT COUNT(*) AS c FROM GymCheckIn WHERE type='checkin'  AND DATE(check_time)=CURDATE()")->fetch_assoc()['c'];
        $total_out = (int)$this->db->query("SELECT COUNT(*) AS c FROM GymCheckIn WHERE type='checkout' AND DATE(check_time)=CURDATE()")->fetch_assoc()['c'];
        $inside    = $total_in - $total_out;
        return [
            'checkin_today'     => $total_in,
            'checkout_today'    => $total_out,
            'currently_inside'  => max(0, $inside)
        ];
    }
}
