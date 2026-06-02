<?php

class Invoice extends Model
{
    // ════════════════════════════════════════════════════════
    // ✅ Bug 2 FIX: method public mới — thay thế việc truy cập
    //    $this->invoiceModel->db trực tiếp từ InvoiceController
    //    (db là protected trong Model, không được truy cập ngoài class)
    // ════════════════════════════════════════════════════════
    public function getPackageTypeSortForPlan(int $planId): ?array
    {
        $res = $this->db->query(
            "SELECT mp.package_type_id, pt.sort_order
             FROM MembershipPlan mp
             LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
             WHERE mp.plan_id = $planId
             LIMIT 1"
        );
        return $res ? $res->fetch_assoc() : null;
    }

    // ════════════════════════════════════════════════════════
    // Các method gốc giữ nguyên bên dưới
    // ════════════════════════════════════════════════════════

    public function getStats(): array
    {
        $today     = date('Y-m-d');
        $thisMonth = date('Y-m');
        return [
            'total'         => (int)$this->db->query("SELECT COUNT(*) as c FROM Invoice")->fetch_assoc()['c'],
            'revenue_month' => (float)$this->db->query("SELECT COALESCE(SUM(final_amount),0) as r FROM Invoice WHERE DATE_FORMAT(invoice_date,'%Y-%m')='$thisMonth'")->fetch_assoc()['r'],
            'today_count'   => (int)$this->db->query("SELECT COUNT(*) as c FROM Invoice WHERE invoice_date='$today'")->fetch_assoc()['c'],
            'promo_used'    => (int)$this->db->query("SELECT COUNT(*) as c FROM Invoice WHERE promotion_id IS NOT NULL")->fetch_assoc()['c'],
            'avg_value'     => (float)$this->db->query("SELECT COALESCE(AVG(final_amount),0) as a FROM Invoice")->fetch_assoc()['a'],
        ];
    }

    public function getInvoicesList(int $page, int $limit, string $search, string $sort, string $month, string $status): array
    {
        $offset = ($page - 1) * $limit;
        $where  = ['1=1']; $params = []; $types = '';

        if ($search !== '') {
            $where[] = "(c.full_name LIKE ? OR c.phone LIKE ? OR inv.invoice_id LIKE ?)";
            $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; $types .= 'sss';
        }
        if ($month !== '')  { $where[] = "DATE_FORMAT(inv.invoice_date,'%Y-%m') = ?"; $params[] = $month;  $types .= 's'; }
        if ($status !== '') { $where[] = "inv.status = ?";                             $params[] = $status; $types .= 's'; }

        $whereStr = implode(' AND ', $where);
        $orderBy  = match($sort) {
            'id_asc'     => 'inv.invoice_id ASC',
            'total_desc' => 'inv.final_amount DESC',
            'total_asc'  => 'inv.final_amount ASC',
            'date_asc'   => 'inv.invoice_date ASC',
            default      => 'inv.invoice_id DESC',
        };

        $countSql = "SELECT COUNT(*) as total FROM Invoice inv JOIN Customer c ON c.customer_id = inv.customer_id WHERE $whereStr";
        $stmt = $this->db->prepare($countSql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $dp = $params; $dt = $types; $dp[] = $limit; $dp[] = $offset; $dt .= 'ii';
        $dataSql = "SELECT inv.invoice_id, inv.invoice_date, inv.original_amount, inv.discount_amount, inv.final_amount, inv.status, inv.note,
                        c.customer_id, c.full_name AS ten_khach, c.phone, inv.created_by,
                        p.promotion_id, p.promotion_name, p.discount_percent
                    FROM Invoice inv
                    JOIN Customer c ON c.customer_id = inv.customer_id
                    LEFT JOIN Promotion p ON p.promotion_id = inv.promotion_id
                    WHERE $whereStr ORDER BY $orderBy LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($dataSql);
        $stmt->bind_param($dt, ...$dp);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return ['data' => $rows, 'total' => (int)$total, 'totalPages' => max(1, (int)ceil($total / $limit))];
    }

    public function getDetail(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT inv.*, c.full_name AS ten_khach, c.phone, c.email, inv.created_by,
                    p.promotion_name, p.discount_percent, p.max_discount_amount
             FROM Invoice inv
             JOIN Customer c ON c.customer_id = inv.customer_id
             LEFT JOIN Promotion p ON p.promotion_id = inv.promotion_id
             WHERE inv.invoice_id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$invoice) return null;

        $stmt = $this->db->prepare(
            "SELECT ct.detail_id, ct.quantity, ct.unit_price, ct.subtotal,
                    mp.plan_id, mp.plan_name, mp.duration_months
             FROM InvoiceDetail ct
             JOIN MembershipPlan mp ON mp.plan_id = ct.plan_id
             WHERE ct.invoice_id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return ['invoice' => $invoice, 'items' => $items];
    }

    public function getCustomersSearch(string $q): array
    {
        $today = date('Y-m-d');
        $stmt  = $this->db->prepare(
            "SELECT c.customer_id, c.full_name, c.phone, c.email
             FROM Customer c
             WHERE c.full_name LIKE ? OR c.phone LIKE ?
             LIMIT 10"
        );
        $s = "%$q%";
        $stmt->bind_param('ss', $s, $s);
        $stmt->execute();
        $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($customers as &$cust) {
            $cid   = intval($cust['customer_id']);
            $pkRes = $this->db->query(
                "SELECT mp.package_type_id, pt.type_name, pt.sort_order
                 FROM MembershipRegistration mr
                 JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
                 LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
                 WHERE mr.customer_id = $cid AND mr.status = 'active' AND mr.end_date >= '$today'
                   AND mp.package_type_id IS NOT NULL
                 ORDER BY pt.sort_order DESC LIMIT 1"
            );
            $pk = $pkRes ? $pkRes->fetch_assoc() : null;
            $cust['active_package_type_id']   = $pk ? (int)$pk['package_type_id'] : null;
            $cust['active_package_type_name'] = $pk ? $pk['type_name'] : null;
        }
        unset($cust);
        return $customers;
    }

    public function getPackagesForDropdown(?int $filterTypeId): array
    {
        if ($filterTypeId > 0) {
            $sortRes  = $this->db->query("SELECT sort_order FROM PackageType WHERE type_id = $filterTypeId");
            $sortRow  = $sortRes ? $sortRes->fetch_assoc() : null;
            $cur_sort = $sortRow ? (int)$sortRow['sort_order'] : 0;
            if ($cur_sort > 0) {
                $stmt = $this->db->prepare(
                    "SELECT mp.plan_id, mp.plan_name, mp.duration_months, mp.price, mp.package_type_id
                     FROM MembershipPlan mp
                     LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
                     WHERE pt.sort_order >= ?
                     ORDER BY pt.sort_order ASC, mp.price ASC"
                );
                $stmt->bind_param('i', $cur_sort);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                return $data;
            }
        }
        return $this->db->query(
            "SELECT plan_id, plan_name, duration_months, price, package_type_id FROM MembershipPlan ORDER BY price ASC"
        )->fetch_all(MYSQLI_ASSOC);
    }

    public function getActivePromotions(float $total): array
    {
        $today = date('Y-m-d');
        $stmt  = $this->db->prepare(
            "SELECT promotion_id, promotion_name, discount_percent, min_order_value,
                    max_discount_amount, max_usage, usage_count
             FROM Promotion
             WHERE status='Active' AND start_date<=? AND end_date>=?
               AND (max_usage IS NULL OR max_usage > usage_count)
               AND min_order_value<=?
             ORDER BY discount_percent DESC"
        );
        $stmt->bind_param('ssd', $today, $today, $total);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    public function calcUpgradeCredit(int $customer_id, int $new_package_type_id): float
    {
        $today      = date('Y-m-d');
        $newTypeRes = $this->db->query("SELECT sort_order FROM PackageType WHERE type_id = $new_package_type_id");
        $newTypeRow = $newTypeRes ? $newTypeRes->fetch_assoc() : null;
        if (!$newTypeRow) return 0.0;
        $new_order = (int)$newTypeRow['sort_order'];

        $activeRes = $this->db->query(
            "SELECT mr.start_date, mr.end_date, mp.price, pt.sort_order AS old_order
             FROM MembershipRegistration mr
             JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
             LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
             WHERE mr.customer_id = $customer_id AND mr.status = 'active' AND mr.end_date >= '$today'
             ORDER BY mr.end_date DESC LIMIT 1"
        );
        $active = $activeRes ? $activeRes->fetch_assoc() : null;
        if (!$active || (int)$active['old_order'] >= $new_order) return 0.0;

        $start          = new DateTime($active['start_date']);
        $end            = new DateTime($active['end_date']);
        $now            = new DateTime($today);
        $total_days     = max(1, $start->diff($end)->days);
        $days_remaining = max(0, $now->diff($end)->days);
        return round(floatval($active['price']) / $total_days * $days_remaining, 0);
    }

    public function registerPackagesForInvoice(int $invoice_id): void
    {
        $inv = $this->db->query("SELECT customer_id, invoice_date FROM Invoice WHERE invoice_id = $invoice_id")->fetch_assoc();
        if (!$inv) return;

        $customer_id = (int)$inv['customer_id'];
        $today       = $inv['invoice_date'];

        $ctRes = $this->db->query(
            "SELECT ct.plan_id, ct.quantity, mp.duration_months, mp.package_type_id,
                    pt.sort_order AS type_order
             FROM InvoiceDetail ct
             JOIN MembershipPlan mp ON mp.plan_id = ct.plan_id
             LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
             WHERE ct.invoice_id = $invoice_id"
        );
        if (!$ctRes) return;

        while ($row = $ctRes->fetch_assoc()) {
            $plan_id      = (int)$row['plan_id'];
            $quantity     = (int)$row['quantity'];
            $duration     = (int)($row['duration_months'] ?? 1);
            $total_months = $duration * $quantity;
            $new_type_id  = $row['package_type_id'] ? (int)$row['package_type_id'] : null;
            $new_type_ord = $row['type_order'] ? (int)$row['type_order'] : 0;

            $activeRes = $this->db->query(
                "SELECT mr.start_date, mr.end_date, mp.price, mp.package_type_id AS old_type_id,
                        pt.sort_order AS old_type_order
                 FROM MembershipRegistration mr
                 JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
                 LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
                 WHERE mr.customer_id = $customer_id AND mr.status = 'active' AND mr.end_date >= '$today'
                 ORDER BY mr.end_date DESC LIMIT 1"
            );
            $activeReg  = $activeRes ? $activeRes->fetch_assoc() : null;
            $is_upgrade = ($activeReg && $new_type_id && (int)$activeReg['old_type_order'] < $new_type_ord);

            if ($is_upgrade) {
                $this->db->query("UPDATE MembershipRegistration SET status='inactive' WHERE customer_id=$customer_id AND status='active' AND end_date>='$today'");
                $start_date = $today;
            } else {
                if ($new_type_id) {
                    $existRes = $this->db->query("SELECT MAX(mr.end_date) as max_end FROM MembershipRegistration mr JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id WHERE mr.customer_id=$customer_id AND mp.package_type_id=$new_type_id");
                } else {
                    $existRes = $this->db->query("SELECT MAX(end_date) as max_end FROM MembershipRegistration WHERE customer_id=$customer_id AND plan_id=$plan_id");
                }
                $max_end    = $existRes ? ($existRes->fetch_assoc()['max_end'] ?? null) : null;
                $start_date = ($max_end && $max_end >= $today) ? date('Y-m-d', strtotime("$max_end +1 day")) : $today;
            }

            $end_date = date('Y-m-d', strtotime("$start_date +$total_months months"));
            $stmtDK   = $this->db->prepare(
                "INSERT INTO MembershipRegistration (customer_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'active')"
            );
            $stmtDK->bind_param('iiss', $customer_id, $plan_id, $start_date, $end_date);
            $stmtDK->execute();
            $stmtDK->close();
        }
    }

    public function getUpgradeCreditInfo(int $customer_id, int $new_pkg_type_id): array
    {
        $today      = date('Y-m-d');
        $newTypeRes = $this->db->query("SELECT sort_order FROM PackageType WHERE type_id = $new_pkg_type_id");
        $newTypeRow = $newTypeRes ? $newTypeRes->fetch_assoc() : null;
        $new_order  = $newTypeRow ? (int)$newTypeRow['sort_order'] : 0;

        $activeRes = $this->db->query(
            "SELECT mr.start_date, mr.end_date, mp.price, mp.plan_name,
                    pt.sort_order AS old_order, pt.type_name AS old_type
             FROM MembershipRegistration mr
             JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
             LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
             WHERE mr.customer_id=$customer_id AND mr.status='active' AND mr.end_date>='$today'
             ORDER BY mr.end_date DESC LIMIT 1"
        );
        $active = $activeRes ? $activeRes->fetch_assoc() : null;
        if (!$active || (int)$active['old_order'] >= $new_order) return ['is_upgrade' => false, 'credit' => 0];

        $start          = new DateTime($active['start_date']);
        $end            = new DateTime($active['end_date']);
        $now            = new DateTime($today);
        $total_days     = max(1, $start->diff($end)->days);
        $days_remaining = max(0, $now->diff($end)->days);
        $credit         = round(floatval($active['price']) / $total_days * $days_remaining, 0);

        return [
            'is_upgrade'     => true,
            'credit'         => $credit,
            'days_remaining' => $days_remaining,
            'old_plan_name'  => $active['plan_name'],
            'old_type'       => $active['old_type'],
            'end_date'       => $active['end_date'],
        ];
    }

    public function addInvoice(int $customer_id, string $invoice_date, ?int $promotion_id, float $original_amount, float $discount_amount, float $final_amount, ?string $note, string $created_by, array $items, string $status = 'Pending'): int|false
    {
        $this->db->begin_transaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO Invoice (customer_id, invoice_date, promotion_id, original_amount, discount_amount, final_amount, note, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('isiiddsss', $customer_id, $invoice_date, $promotion_id, $original_amount, $discount_amount, $final_amount, $note, $status, $created_by);
            $stmt->execute();
            $invoice_id = (int)$this->db->insert_id;
            $stmt->close();

            $stmtItem = $this->db->prepare(
                "INSERT INTO InvoiceDetail (invoice_id, plan_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)"
            );
            foreach ($items as $item) {
                $plan_id = (int)$item['plan_id']; $qty = (int)$item['quantity'];
                $up  = floatval($item['unit_price']); $sub = $qty * $up;
                $stmtItem->bind_param('iiidd', $invoice_id, $plan_id, $qty, $up, $sub);
                $stmtItem->execute();
            }
            $stmtItem->close();

            if ($promotion_id) {
                $this->db->query("UPDATE Promotion SET usage_count=usage_count+1 WHERE promotion_id=$promotion_id");
                $this->db->query("UPDATE Promotion SET status='Expired' WHERE promotion_id=$promotion_id AND max_usage IS NOT NULL AND usage_count>=max_usage");
            }
            $this->db->commit();
            return $invoice_id;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    public function deleteInvoice(int $id): array
    {
        $inv = $this->db->query("SELECT promotion_id, customer_id FROM Invoice WHERE invoice_id = $id")->fetch_assoc();
        if (!$inv) return ['success' => false, 'message' => 'Không tìm thấy hóa đơn'];

        $planRows = [];
        $ctRes = $this->db->query("SELECT plan_id FROM InvoiceDetail WHERE invoice_id = $id");
        while ($row = $ctRes->fetch_assoc()) $planRows[] = (int)$row['plan_id'];
        $customer_id = (int)$inv['customer_id'];

        $this->db->begin_transaction();
        try {
            if (!empty($planRows) && $customer_id > 0) {
                foreach ($planRows as $plan_id) {
                    $this->db->query("DELETE FROM MembershipRegistration WHERE customer_id=$customer_id AND plan_id=$plan_id ORDER BY registration_id DESC LIMIT 1");
                }
            }
            $this->db->query("DELETE FROM InvoiceDetail WHERE invoice_id = $id");
            $this->db->query("DELETE FROM Invoice WHERE invoice_id = $id");
            if ($inv['promotion_id']) {
                $pid = (int)$inv['promotion_id'];
                $this->db->query("UPDATE Promotion SET usage_count=GREATEST(0,usage_count-1) WHERE promotion_id=$pid");
                $this->db->query("UPDATE Promotion SET status='Active' WHERE promotion_id=$pid AND status='Expired' AND max_usage IS NOT NULL AND usage_count<max_usage AND end_date>=CURDATE()");
            }
            $this->db->commit();
            return ['success' => true, 'message' => 'Đã xóa hóa đơn'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE Invoice SET status=? WHERE invoice_id=?");
        $stmt->bind_param('si', $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getStatus(int $id): ?string
    {
        $r = $this->db->query("SELECT status FROM Invoice WHERE invoice_id=$id")->fetch_assoc();
        return $r['status'] ?? null;
    }

    public function updateInvoice(int $id, string $invoice_date, ?string $note, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE Invoice SET invoice_date=?, note=?, status=? WHERE invoice_id=?");
        $stmt->bind_param('sssi', $invoice_date, $note, $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function confirmPayment(int $id, string $method): bool
    {
        $note_append = "Thanh toán qua: $method - " . date('d/m/Y H:i');
        $stmt = $this->db->prepare(
            "UPDATE Invoice SET status='Paid', note=CONCAT(IFNULL(note,''),IF(note IS NULL OR note='','',' | '),?) WHERE invoice_id=?"
        );
        $stmt->bind_param('si', $note_append, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getPaymentInfo(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT inv.*, c.full_name AS ten_khach, c.phone, c.email
             FROM Invoice inv
             JOIN Customer c ON c.customer_id=inv.customer_id
             WHERE inv.invoice_id=?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $inv = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $inv ?: null;
    }
}
