<?php

class Payment extends Model
{
    public function getCustomerIdByAccountId(int $accountId): ?int
    {
        $r = $this->db->prepare("SELECT customer_id FROM Customer WHERE account_id = ? LIMIT 1");
        $r->bind_param("i", $accountId); $r->execute();
        $row = $r->get_result()->fetch_assoc(); $r->close();
        return $row ? (int)$row['customer_id'] : null;
    }

    public function getPlansForCustomer(int $cid): array
    {
        $today = date('Y-m-d');
        $curRes = $this->db->query("SELECT mp.package_type_id, pt.sort_order, pt.type_name FROM MembershipRegistration mr JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id WHERE mr.customer_id = $cid AND mr.status = 'active' AND mr.end_date >= '$today' ORDER BY pt.sort_order DESC LIMIT 1");
        $curPkg   = $curRes ? $curRes->fetch_assoc() : null;
        $cur_sort = $curPkg ? (int)$curPkg['sort_order'] : 0;

        if ($cur_sort > 0) {
            $stmt = $this->db->prepare("SELECT mp.plan_id, mp.plan_name, mp.duration_months, mp.price, mp.description, mp.image_url, mp.package_type_id, pt.type_name, pt.color_code, pt.sort_order AS type_order FROM MembershipPlan mp LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id WHERE pt.sort_order >= ? ORDER BY pt.sort_order ASC, mp.price ASC");
            $stmt->bind_param('i', $cur_sort);
        } else {
            $stmt = $this->db->prepare("SELECT mp.plan_id, mp.plan_name, mp.duration_months, mp.price, mp.description, mp.image_url, mp.package_type_id, pt.type_name, pt.color_code, pt.sort_order AS type_order FROM MembershipPlan mp LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id ORDER BY pt.sort_order ASC, mp.price ASC");
        }
        $stmt->execute(); $plans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return ['data' => $plans, 'cur_sort' => $cur_sort, 'cur_type_name' => $curPkg['type_name'] ?? null];
    }

    public function getActivePromotions(float $totalAmount): array
    {
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("SELECT promotion_id, promotion_name, discount_percent, min_order_value, max_discount_amount, max_usage, usage_count FROM Promotion WHERE status='Active' AND start_date<=? AND end_date>=? AND (max_usage IS NULL OR max_usage > usage_count) AND min_order_value<=? ORDER BY discount_percent DESC");
        $stmt->bind_param('ssd', $today, $today, $totalAmount); $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        return $data;
    }

    public function calcUpgradeCredit(int $customerId, int $newPkgTypeId): float
    {
        $today = date('Y-m-d');
        $newTypeRes = $this->db->query("SELECT sort_order FROM PackageType WHERE type_id = $newPkgTypeId");
        $newTypeRow = $newTypeRes ? $newTypeRes->fetch_assoc() : null;
        if (!$newTypeRow) return 0.0;
        $new_order = (int)$newTypeRow['sort_order'];

        $activeRes = $this->db->query("SELECT mr.start_date, mr.end_date, mp.price, mp.package_type_id AS old_type_id, pt.sort_order AS old_order FROM MembershipRegistration mr JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id WHERE mr.customer_id = $customerId AND mr.status = 'active' AND mr.end_date >= '$today' ORDER BY pt.sort_order DESC, mr.end_date DESC LIMIT 1");
        $active = $activeRes ? $activeRes->fetch_assoc() : null;
        if (!$active || (int)$active['old_order'] >= $new_order) return 0.0;

        $start = new DateTime($active['start_date']); $end = new DateTime($active['end_date']); $now = new DateTime($today);
        $total_days = max(1, $start->diff($end)->days);
        $days_remaining = max(0, $now->diff($end)->days);
        return round(floatval($active['price']) / $total_days * $days_remaining, 0);
    }

    public function getUpgradeCreditInfo(int $cid, int $newPkgTypeId): array
    {
        $today = date('Y-m-d');
        $newTypeRes = $this->db->query("SELECT sort_order FROM PackageType WHERE type_id = $newPkgTypeId");
        $newTypeRow = $newTypeRes ? $newTypeRes->fetch_assoc() : null;
        $new_order = $newTypeRow ? (int)$newTypeRow['sort_order'] : 0;

        $activeRes = $this->db->query("SELECT mr.start_date, mr.end_date, mp.price, mp.plan_name, pt.sort_order AS old_order, pt.type_name AS old_type FROM MembershipRegistration mr JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id WHERE mr.customer_id = $cid AND mr.status = 'active' AND mr.end_date >= '$today' ORDER BY pt.sort_order DESC LIMIT 1");
        $active = $activeRes ? $activeRes->fetch_assoc() : null;
        if (!$active || (int)$active['old_order'] >= $new_order) return ['is_upgrade' => false, 'credit' => 0];

        $start = new DateTime($active['start_date']); $end = new DateTime($active['end_date']); $now = new DateTime($today);
        $total_days = max(1, $start->diff($end)->days);
        $days_remaining = max(0, $now->diff($end)->days);
        $credit = round(floatval($active['price']) / $total_days * $days_remaining, 0);
        return ['is_upgrade' => true, 'credit' => $credit, 'days_remaining' => $days_remaining, 'old_plan_name' => $active['plan_name'], 'old_type' => $active['old_type'], 'end_date' => $active['end_date']];
    }

    public function getPlanInfo(int $planId): ?array
    {
        $res = $this->db->query("SELECT mp.plan_id, mp.plan_name, mp.price, mp.duration_months, mp.package_type_id, pt.sort_order AS type_order FROM MembershipPlan mp LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id WHERE mp.plan_id = $planId");
        return $res ? $res->fetch_assoc() : null;
    }

    public function registerPackagesForInvoice(int $invoice_id): void
    {
        $inv = $this->db->query("SELECT customer_id, invoice_date FROM Invoice WHERE invoice_id = $invoice_id")->fetch_assoc();
        if (!$inv) return;
        $customer_id = (int)$inv['customer_id']; $today = $inv['invoice_date'];

        $ctRes = $this->db->query("SELECT ct.plan_id, ct.quantity, mp.duration_months, mp.package_type_id, pt.sort_order AS type_order FROM InvoiceDetail ct JOIN MembershipPlan mp ON mp.plan_id = ct.plan_id LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id WHERE ct.invoice_id = $invoice_id");
        if (!$ctRes) return;

        while ($row = $ctRes->fetch_assoc()) {
            $plan_id = (int)$row['plan_id']; $quantity = (int)$row['quantity']; $duration = (int)($row['duration_months'] ?? 1);
            $total_months = $duration * $quantity;
            $new_type_id = $row['package_type_id'] ? (int)$row['package_type_id'] : null;
            $new_type_ord = $row['type_order'] ? (int)$row['type_order'] : 0;

            $activeRes = $this->db->query("SELECT mr.start_date, mr.end_date, mp.price, mp.package_type_id AS old_type_id, pt.sort_order AS old_type_order FROM MembershipRegistration mr JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id WHERE mr.customer_id = $customer_id AND mr.status = 'active' AND mr.end_date >= '$today' ORDER BY pt.sort_order DESC, mr.end_date DESC LIMIT 1");
            $activeReg = $activeRes ? $activeRes->fetch_assoc() : null;
            $is_upgrade = ($activeReg && $new_type_id && (int)$activeReg['old_type_order'] < $new_type_ord);

            if ($is_upgrade) {
                $this->db->query("UPDATE MembershipRegistration SET status='inactive' WHERE customer_id=$customer_id AND status='active' AND end_date>='$today'");
                $start_date = $today;
            } else {
                if ($new_type_id) {
                    $existRes = $this->db->query("SELECT MAX(mr.end_date) AS max_end FROM MembershipRegistration mr JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id WHERE mr.customer_id=$customer_id AND mp.package_type_id=$new_type_id");
                } else {
                    $existRes = $this->db->query("SELECT MAX(end_date) AS max_end FROM MembershipRegistration WHERE customer_id=$customer_id AND plan_id=$plan_id");
                }
                $max_end = $existRes ? ($existRes->fetch_assoc()['max_end'] ?? null) : null;
                $start_date = ($max_end && $max_end >= $today) ? $max_end : $today;
            }
            $end_date = date('Y-m-d', strtotime("$start_date +$total_months months"));
            $stmtDK = $this->db->prepare("INSERT INTO MembershipRegistration (customer_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'active')");
            $stmtDK->bind_param('iiss', $customer_id, $plan_id, $start_date, $end_date); $stmtDK->execute(); $stmtDK->close();
        }
    }

    public function createOrder(int $cid, int $planId, int $quantity, ?int $promotionId, float $originalAmount, float $discountAmount, float $finalAmount, string $planName, string $createdBy): int|false
    {
        $this->db->begin_transaction();
        try {
            $invoice_date = date('Y-m-d');
            $note = "Mua gói online — $planName";
            $stmt = $this->db->prepare("INSERT INTO Invoice (customer_id, invoice_date, promotion_id, original_amount, discount_amount, final_amount, note, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
            $stmt->bind_param('isiiddss', $cid, $invoice_date, $promotionId, $originalAmount, $discountAmount, $finalAmount, $note, $createdBy);
            $stmt->execute(); $invoice_id = (int)$this->db->insert_id; $stmt->close();

            $unit_price = $originalAmount / $quantity; $subtotal = $originalAmount;
            $stmtItem = $this->db->prepare("INSERT INTO InvoiceDetail (invoice_id, plan_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmtItem->bind_param('iiidd', $invoice_id, $planId, $quantity, $unit_price, $subtotal); $stmtItem->execute(); $stmtItem->close();

            if ($promotionId) {
                $this->db->query("UPDATE Promotion SET usage_count=usage_count+1 WHERE promotion_id=$promotionId");
                $this->db->query("UPDATE Promotion SET status='Expired' WHERE promotion_id=$promotionId AND max_usage IS NOT NULL AND usage_count>=max_usage");
            }
            $this->db->commit();
            return $invoice_id;
        } catch (\Exception $e) { $this->db->rollback(); return false; }
    }

    public function confirmPayment(int $invoiceId, int $cid, string $method): array
    {
        $check = $this->db->query("SELECT customer_id, status FROM Invoice WHERE invoice_id = $invoiceId")->fetch_assoc();
        if (!$check || (int)$check['customer_id'] !== $cid) return ['success' => false, 'message' => 'Không có quyền'];
        if ($check['status'] === 'Paid') return ['success' => true, 'message' => 'Đã thanh toán'];

        $note_append = "Thanh toán qua: $method — " . date('d/m/Y H:i');
        $stmt = $this->db->prepare("UPDATE Invoice SET status='Paid', note=CONCAT(IFNULL(note,''),IF(note IS NULL OR note='','',' | '),?) WHERE invoice_id=?");
        $stmt->bind_param('si', $note_append, $invoiceId); $ok = $stmt->execute(); $stmt->close();
        if ($ok) { $this->registerPackagesForInvoice($invoiceId); return ['success' => true, 'message' => 'Thanh toán thành công! Gói tập đã được kích hoạt.']; }
        return ['success' => false, 'message' => 'Lỗi xác nhận thanh toán'];
    }

    // ✅ FIX: method công khai để PaymentController::webhook() dùng thay vì $this->paymentModel->db
    public function getInvoiceForWebhook(int $invoiceId): ?array
    {
        $stmt = $this->db->prepare("SELECT invoice_id, final_amount, status FROM Invoice WHERE invoice_id = ?");
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function markPaidFromWebhook(int $invoiceId, string $note): bool
    {
        $stmt = $this->db->prepare("UPDATE Invoice SET status='Paid', note=CONCAT(IFNULL(note,''),IF(note IS NULL OR note='','',' | '),?) WHERE invoice_id=? AND status != 'Paid'");
        $stmt->bind_param('si', $note, $invoiceId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function checkStatus(int $invoiceId, int $cid): string
    {
        $row = $this->db->query("SELECT status FROM Invoice WHERE invoice_id = $invoiceId AND customer_id = $cid")->fetch_assoc();
        return $row['status'] ?? 'Pending';
    }
}
