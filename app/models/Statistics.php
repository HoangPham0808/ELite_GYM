<?php

class Statistics extends Model
{
    private function execSql(string $sql, array $params = []): array
    {
        if (empty($params)) {
            $res = $this->db->query($sql);
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) $types .= 'i';
            elseif (is_double($p)) $types .= 'd';
            else $types .= 's';
        }
        $st = $this->db->prepare($sql);
        $st->bind_param($types, ...$params);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
        return $rows;
    }

    public function getKPI(): array
    {
        $month = date('m');
        $year  = date('Y');

        $rows = $this->execSql("SELECT COALESCE(SUM(final_amount), 0) AS total FROM Invoice WHERE status='Paid' AND MONTH(invoice_date)=? AND YEAR(invoice_date)=?", [$month, $year]);
        $revenue = (float)($rows[0]['total'] ?? 0);

        $rows = $this->execSql("SELECT COUNT(*) AS cnt FROM Customer WHERE MONTH(registered_at)=? AND YEAR(registered_at)=?", [$month, $year]);
        $newMembers = (int)($rows[0]['cnt'] ?? 0);

        $rows = $this->execSql("SELECT COUNT(DISTINCT customer_id) AS cnt FROM MembershipRegistration WHERE status='active' AND end_date>=CURDATE()");
        $activeMembers = (int)($rows[0]['cnt'] ?? 0);

        $rows = $this->execSql("SELECT COUNT(*) AS cnt FROM GymCheckIn WHERE DATE(check_time)=CURDATE() AND type='checkin'");
        $checkinToday = (int)($rows[0]['cnt'] ?? 0);

        $rows = $this->execSql("SELECT COUNT(*) AS cnt FROM EquipmentMaintenance WHERE status='In Progress'");
        $equipMaint = (int)($rows[0]['cnt'] ?? 0);

        return compact('revenue', 'newMembers', 'activeMembers', 'checkinToday', 'equipMaint');
    }

    public function getRevenue(string $year): array
    {
        $rawRows = $this->execSql("SELECT MONTH(invoice_date) AS m, COALESCE(SUM(final_amount), 0) AS total FROM Invoice WHERE status='Paid' AND YEAR(invoice_date)=? GROUP BY MONTH(invoice_date) ORDER BY m", [$year]);
        $map = [];
        foreach ($rawRows as $r) {
            $map[(int)$r['m']] = (float)$r['total'];
        }
        $result = [];
        for ($i = 1; $i <= 12; $i++) {
            $result[] = $map[$i] ?? 0;
        }
        return $result;
    }

    public function getPlanDistribution(): array
    {
        return $this->execSql("SELECT pt.type_name, COUNT(mr.registration_id) AS cnt FROM MembershipRegistration mr JOIN MembershipPlan mp ON mr.plan_id=mp.plan_id JOIN PackageType pt ON mp.package_type_id=pt.type_id WHERE mr.status='active' GROUP BY pt.type_name ORDER BY cnt DESC");
    }

    public function getRecentInvoices(int $limit): array
    {
        return $this->execSql("SELECT i.invoice_id, c.full_name, mp.plan_name, i.invoice_date, i.final_amount, i.status FROM Invoice i JOIN Customer c ON i.customer_id=c.customer_id JOIN InvoiceDetail id ON i.invoice_id=id.invoice_id JOIN MembershipPlan mp ON id.plan_id=mp.plan_id ORDER BY i.invoice_date DESC, i.invoice_id DESC LIMIT ?", [$limit]);
    }

    public function fetchReportData(string $report, string $from, string $to, string $group): array
    {
        $groupSql = $this->buildGroupSQL($group);

        switch ($report) {
            case 'revenue-monthly':
                return $this->execSql("SELECT {$groupSql} AS period,
                           COUNT(i.invoice_id)       AS total_invoices,
                           SUM(i.original_amount)    AS original_amount,
                           SUM(i.discount_amount)    AS discount_amount,
                           SUM(i.final_amount)        AS revenue
                    FROM Invoice i
                    WHERE i.status = 'Paid'
                      AND i.invoice_date BETWEEN ? AND ?
                    GROUP BY period ORDER BY period", [$from, $to]);

            case 'revenue-plan':
                return $this->execSql("SELECT mp.plan_name, pt.type_name AS package_type,
                           COUNT(id.detail_id)    AS qty,
                           SUM(id.subtotal)        AS revenue
                    FROM InvoiceDetail id
                    JOIN Invoice i        ON id.invoice_id = i.invoice_id
                    JOIN MembershipPlan mp ON id.plan_id   = mp.plan_id
                    LEFT JOIN PackageType pt ON mp.package_type_id = pt.type_id
                    WHERE i.status = 'Paid'
                      AND i.invoice_date BETWEEN ? AND ?
                    GROUP BY mp.plan_id ORDER BY revenue DESC", [$from, $to]);

            case 'revenue-promo':
                return $this->execSql("SELECT p.promotion_name, p.discount_percent,
                           COUNT(i.invoice_id)     AS usage_count,
                           SUM(i.discount_amount)  AS total_discount,
                           SUM(i.final_amount)      AS revenue_after_discount
                    FROM Invoice i
                    JOIN Promotion p ON i.promotion_id = p.promotion_id
                    WHERE i.status = 'Paid'
                      AND i.invoice_date BETWEEN ? AND ?
                    GROUP BY p.promotion_id ORDER BY total_discount DESC", [$from, $to]);

            case 'member-new':
                return $this->execSql("SELECT c.customer_id, c.full_name, c.phone, c.email,
                           c.gender, c.registered_at,
                           mp.plan_name, mr.start_date, mr.end_date
                    FROM Customer c
                    JOIN MembershipRegistration mr ON c.customer_id = mr.customer_id
                    JOIN MembershipPlan mp          ON mr.plan_id = mp.plan_id
                    WHERE c.registered_at BETWEEN ? AND ?
                    ORDER BY c.registered_at DESC", [$from, $to]);

            case 'member-active':
                return $this->execSql("SELECT c.customer_id, c.full_name, c.phone, c.email,
                           mp.plan_name, mr.start_date, mr.end_date,
                           DATEDIFF(mr.end_date, CURDATE()) AS days_remaining
                    FROM MembershipRegistration mr
                    JOIN Customer c       ON mr.customer_id = c.customer_id
                    JOIN MembershipPlan mp ON mr.plan_id = mp.plan_id
                    WHERE mr.status = 'active'
                      AND mr.end_date >= CURDATE()
                    ORDER BY mr.end_date ASC");

            case 'member-expire':
                return $this->execSql("SELECT c.customer_id, c.full_name, c.phone, c.email, mp.plan_name, mr.start_date, mr.end_date, DATEDIFF(CURDATE(), mr.end_date) AS days_expired FROM MembershipRegistration mr JOIN Customer c ON mr.customer_id=c.customer_id JOIN MembershipPlan mp ON mr.plan_id=mp.plan_id WHERE mr.status='inactive' AND mr.end_date BETWEEN ? AND ? ORDER BY mr.end_date DESC", [$from, $to]);

            case 'employee-attend':
                return $this->execSql("SELECT e.employee_id, e.full_name, e.position,
                           SUM(a.status = 'Present')  AS present,
                           SUM(a.status = 'Late')     AS late,
                           SUM(a.status = 'Absent')   AS absent,
                           SUM(a.status = 'On Leave') AS on_leave,
                           COUNT(a.attendance_id)      AS total_days
                    FROM Attendance a
                    JOIN Employee e ON a.employee_id = e.employee_id
                    WHERE a.work_date BETWEEN ? AND ?
                    GROUP BY e.employee_id ORDER BY e.full_name", [$from, $to]);

            case 'employee-payroll':
                $fromYM = (int)date('Y', strtotime($from)) * 12 + (int)date('m', strtotime($from));
                $toYM   = (int)date('Y', strtotime($to))   * 12 + (int)date('m', strtotime($to));
                return $this->execSql("SELECT e.full_name, e.position,
                           p.month, p.year,
                           p.base_salary, p.allowance, p.bonus,
                           p.deduction, p.net_salary
                    FROM Payroll p
                    JOIN Employee e ON p.employee_id = e.employee_id
                    WHERE (p.year * 12 + p.month) BETWEEN ? AND ?
                    ORDER BY p.year, p.month, e.full_name", [$fromYM, $toYM]);

            case 'equipment-status':
                return $this->execSql("SELECT eq.equipment_id, eq.equipment_name, eq.condition_status, gr.room_name, et.type_name, eq.purchase_date, eq.purchase_price, eq.last_maintenance_date, DATEDIFF(CURDATE(), eq.last_maintenance_date) AS days_since_maint FROM Equipment eq LEFT JOIN GymRoom gr ON eq.room_id=gr.room_id LEFT JOIN EquipmentType et ON eq.type_id=et.type_id ORDER BY days_since_maint DESC");

            case 'equipment-maint':
                return $this->execSql("SELECT em.maintenance_id, eq.equipment_name, et.type_name,
                           em.maintenance_date, em.description,
                           em.cost, em.performed_by, em.status
                    FROM EquipmentMaintenance em
                    JOIN Equipment eq      ON em.equipment_id = eq.equipment_id
                    LEFT JOIN EquipmentType et ON eq.type_id  = et.type_id
                    WHERE em.maintenance_date BETWEEN ? AND ?
                    ORDER BY em.maintenance_date DESC", [$from, $to]);

            case 'checkin-daily':
                $groupDailySql = $this->buildGroupSQL($group, 'check_time');
                return $this->execSql("SELECT {$groupDailySql} AS period,
                           COUNT(*) AS total_checkins,
                           COUNT(DISTINCT customer_id) AS unique_members
                    FROM GymCheckIn
                    WHERE type = 'checkin'
                      AND DATE(check_time) BETWEEN ? AND ?
                    GROUP BY period ORDER BY period", [$from, $to]);

            case 'checkin-class':
                return $this->execSql("SELECT tc.class_name, e.full_name AS trainer,
                           tc.start_time, tc.end_time,
                           gr.room_name,
                           COUNT(cr.class_registration_id) AS registered
                    FROM TrainingClass tc
                    LEFT JOIN Employee e          ON tc.trainer_id = e.employee_id
                    LEFT JOIN GymRoom gr          ON tc.room_id    = gr.room_id
                    LEFT JOIN ClassRegistration cr ON tc.class_id = cr.class_id
                    WHERE DATE(tc.start_time) BETWEEN ? AND ?
                    GROUP BY tc.class_id ORDER BY tc.start_time DESC", [$from, $to]);

            default:
                return $this->execSql("SELECT i.invoice_id, c.full_name, mp.plan_name,
                           i.invoice_date, i.original_amount,
                           i.discount_amount, i.final_amount, i.status, i.created_by
                    FROM Invoice i
                    JOIN Customer c        ON i.customer_id = c.customer_id
                    JOIN InvoiceDetail id  ON i.invoice_id  = id.invoice_id
                    JOIN MembershipPlan mp ON id.plan_id    = mp.plan_id
                    WHERE i.invoice_date BETWEEN ? AND ?
                    ORDER BY i.invoice_date DESC", [$from, $to]);
        }
    }

    private function buildGroupSQL(string $group, string $col = 'invoice_date'): string
    {
        return match($group) {
            'day'   => "DATE({$col})",
            'week'  => "YEARWEEK({$col}, 1)",
            'year'  => "YEAR({$col})",
            default => "DATE_FORMAT({$col}, '%Y-%m')",
        };
    }
}
