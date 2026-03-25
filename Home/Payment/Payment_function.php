<?php
ob_start();
session_start();

if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

require_once '../../Database/db.php';
header('Content-Type: application/json; charset=utf-8');

$account_id = (int)$_SESSION['account_id'];

// Lấy customer_id
$r = $conn->prepare("SELECT customer_id FROM Customer WHERE account_id = ? LIMIT 1");
$r->bind_param("i", $account_id);
$r->execute();
$cus = $r->get_result()->fetch_assoc();
$r->close();

if (!$cus) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy tài khoản']);
    exit;
}
$cid = (int)$cus['customer_id'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ════════════════════════════════════════════════════════════
// HELPER: Tính credit hoàn tiền khi đổi sang loại gói khác
// ════════════════════════════════════════════════════════════
function calcUpgradeCredit($conn, $customer_id, $new_package_type_id) {
    $today = date('Y-m-d');

    $newTypeRes = $conn->query("SELECT sort_order FROM PackageType WHERE type_id = " . intval($new_package_type_id));
    $newTypeRow = $newTypeRes ? $newTypeRes->fetch_assoc() : null;
    if (!$newTypeRow) return 0.0;
    $new_order = intval($newTypeRow['sort_order']);

    $activeRes = $conn->query("
        SELECT mr.start_date, mr.end_date, mp.price,
               mp.package_type_id AS old_type_id,
               pt.sort_order AS old_order
        FROM MembershipRegistration mr
        JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
        LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
        WHERE mr.customer_id = " . intval($customer_id) . "
          AND mr.status      = 'active'
          AND mr.end_date   >= '$today'
        ORDER BY pt.sort_order DESC, mr.end_date DESC
        LIMIT 1
    ");
    $active = $activeRes ? $activeRes->fetch_assoc() : null;
    if (!$active) return 0.0;

    // Chỉ tính credit nếu sort_order của gói mới cao hơn
    if (intval($active['old_order']) >= $new_order) return 0.0;

    $start          = new DateTime($active['start_date']);
    $end            = new DateTime($active['end_date']);
    $now            = new DateTime($today);
    $total_days     = max(1, $start->diff($end)->days);
    $days_remaining = max(0, $now->diff($end)->days);
    $daily_rate     = floatval($active['price']) / $total_days;
    return round($daily_rate * $days_remaining, 0);
}

// ════════════════════════════════════════════════════════════
// HELPER: Đăng ký gói sau khi thanh toán thành công
// ════════════════════════════════════════════════════════════
function registerPackagesForInvoice($conn, $invoice_id) {
    $inv = $conn->query("SELECT customer_id, invoice_date FROM Invoice WHERE invoice_id = $invoice_id")->fetch_assoc();
    if (!$inv) return;

    $customer_id  = intval($inv['customer_id']);
    $invoice_date = $inv['invoice_date'];
    $today        = $invoice_date;

    $ctRes = $conn->query("
        SELECT ct.plan_id, ct.quantity, mp.duration_months,
               mp.package_type_id, pt.sort_order AS type_order
        FROM InvoiceDetail ct
        JOIN MembershipPlan mp ON mp.plan_id = ct.plan_id
        LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
        WHERE ct.invoice_id = $invoice_id
    ");
    if (!$ctRes) return;

    while ($row = $ctRes->fetch_assoc()) {
        $plan_id      = intval($row['plan_id']);
        $quantity     = intval($row['quantity']);
        $duration     = intval($row['duration_months'] ?? 1);
        $total_months = $duration * $quantity;
        $new_type_id  = $row['package_type_id'] ? intval($row['package_type_id']) : null;
        $new_type_ord = $row['type_order']       ? intval($row['type_order'])      : 0;

        // Gói active cao nhất hiện tại của khách
        $activeRes = $conn->query("
            SELECT mr.start_date, mr.end_date, mp.price,
                   mp.package_type_id AS old_type_id,
                   pt.sort_order AS old_type_order
            FROM MembershipRegistration mr
            JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
            LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
            WHERE mr.customer_id = $customer_id
              AND mr.status      = 'active'
              AND mr.end_date   >= '$today'
            ORDER BY pt.sort_order DESC, mr.end_date DESC
            LIMIT 1
        ");
        $activeReg = $activeRes ? $activeRes->fetch_assoc() : null;

        // Nâng cấp: gói mới sort_order CAO HƠN gói cũ
        $is_upgrade = ($activeReg && $new_type_id && intval($activeReg['old_type_order']) < $new_type_ord);

        if ($is_upgrade) {
            $conn->query("
                UPDATE MembershipRegistration
                SET status = 'inactive'
                WHERE customer_id = $customer_id
                  AND status      = 'active'
                  AND end_date   >= '$today'
            ");
            $start_date = $today;
        } else {
            $existRes = $conn->query("
                SELECT MAX(end_date) AS max_end
                FROM MembershipRegistration
                WHERE customer_id = $customer_id AND plan_id = $plan_id
            ");
            $max_end = $existRes ? ($existRes->fetch_assoc()['max_end'] ?? null) : null;
            $start_date = ($max_end && $max_end >= $today)
                ? date('Y-m-d', strtotime("$max_end +1 day"))
                : $today;
        }

        $end_date = date('Y-m-d', strtotime("$start_date +$total_months months"));

        $stmtDK = $conn->prepare("
            INSERT INTO MembershipRegistration (customer_id, plan_id, start_date, end_date, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmtDK->bind_param('iiss', $customer_id, $plan_id, $start_date, $end_date);
        $stmtDK->execute();
    }
}

switch ($action) {

    // ════════════════════════════════════════════════════════
    // LẤY TẤT CẢ GÓI TẬP (lọc theo gói đang dùng)
    // Basic → tất cả | Standard → Standard+ | Premium → Premium
    // ════════════════════════════════════════════════════════
    case 'get_plans':
        $today = date('Y-m-d');

        // Gói đang active cao nhất của khách
        $curRes = $conn->query("
            SELECT mp.package_type_id, pt.sort_order, pt.type_name
            FROM MembershipRegistration mr
            JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
            LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
            WHERE mr.customer_id = $cid
              AND mr.status      = 'active'
              AND mr.end_date   >= '$today'
            ORDER BY pt.sort_order DESC
            LIMIT 1
        ");
        $curPkg   = $curRes ? $curRes->fetch_assoc() : null;
        $cur_sort = $curPkg ? intval($curPkg['sort_order']) : 0;

        if ($cur_sort > 0) {
            $stmt = $conn->prepare("
                SELECT mp.plan_id, mp.plan_name, mp.duration_months, mp.price,
                       mp.description, mp.image_url,
                       mp.package_type_id,
                       pt.type_name, pt.color_code, pt.sort_order AS type_order
                FROM MembershipPlan mp
                LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
                WHERE pt.sort_order >= ?
                ORDER BY pt.sort_order ASC, mp.price ASC
            ");
            $stmt->bind_param('i', $cur_sort);
        } else {
            $stmt = $conn->prepare("
                SELECT mp.plan_id, mp.plan_name, mp.duration_months, mp.price,
                       mp.description, mp.image_url,
                       mp.package_type_id,
                       pt.type_name, pt.color_code, pt.sort_order AS type_order
                FROM MembershipPlan mp
                LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
                ORDER BY pt.sort_order ASC, mp.price ASC
            ");
        }
        $stmt->execute();
        $plans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'success'       => true,
            'data'          => $plans,
            'cur_sort'      => $cur_sort,
            'cur_type_name' => $curPkg ? $curPkg['type_name'] : null,
        ]);
        break;

    // ════════════════════════════════════════════════════════
    // LẤY KHUYẾN MÃI ÁP DỤNG ĐƯỢC
    // ════════════════════════════════════════════════════════
    case 'get_promotions':
        $total_amount = floatval($_GET['total'] ?? 0);
        $today = date('Y-m-d');

        $stmt = $conn->prepare("
            SELECT promotion_id, promotion_name, discount_percent,
                   min_order_value, max_discount_amount, max_usage, usage_count
            FROM Promotion
            WHERE status = 'Active'
              AND start_date <= ? AND end_date >= ?
              AND (max_usage IS NULL OR max_usage > usage_count)
              AND min_order_value <= ?
            ORDER BY discount_percent DESC
        ");
        $stmt->bind_param('ssd', $today, $today, $total_amount);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ════════════════════════════════════════════════════════
    // TÍNH CREDIT NÂNG CẤP
    // ════════════════════════════════════════════════════════
    case 'get_upgrade_credit':
        $new_pkg_type_id = intval($_GET['package_type_id'] ?? 0);
        if ($new_pkg_type_id === 0) {
            echo json_encode(['success' => true, 'credit' => 0, 'is_upgrade' => false]);
            exit;
        }

        $today = date('Y-m-d');

        $newTypeRes = $conn->query("SELECT sort_order FROM PackageType WHERE type_id = $new_pkg_type_id");
        $newTypeRow = $newTypeRes ? $newTypeRes->fetch_assoc() : null;
        $new_order  = $newTypeRow ? intval($newTypeRow['sort_order']) : 0;

        $activeRes = $conn->query("
            SELECT mr.start_date, mr.end_date,
                   mp.price, mp.plan_name,
                   pt.sort_order AS old_order, pt.type_name AS old_type
            FROM MembershipRegistration mr
            JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
            LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
            WHERE mr.customer_id = $cid
              AND mr.status      = 'active'
              AND mr.end_date   >= '$today'
            ORDER BY pt.sort_order DESC
            LIMIT 1
        ");
        $active = $activeRes ? $activeRes->fetch_assoc() : null;

        if (!$active || intval($active['old_order']) >= $new_order) {
            echo json_encode(['success' => true, 'credit' => 0, 'is_upgrade' => false]);
            exit;
        }

        $start          = new DateTime($active['start_date']);
        $end            = new DateTime($active['end_date']);
        $now            = new DateTime($today);
        $total_days     = max(1, $start->diff($end)->days);
        $days_remaining = max(0, $now->diff($end)->days);
        $daily_rate     = floatval($active['price']) / $total_days;
        $credit         = round($daily_rate * $days_remaining, 0);

        echo json_encode([
            'success'        => true,
            'is_upgrade'     => true,
            'credit'         => $credit,
            'days_remaining' => $days_remaining,
            'old_plan_name'  => $active['plan_name'],
            'old_type'       => $active['old_type'],
            'end_date'       => $active['end_date'],
        ]);
        break;

    // ════════════════════════════════════════════════════════
    // TẠO HÓA ĐƠN & MUA GÓI
    // ════════════════════════════════════════════════════════
    case 'create_order':
        $plan_id      = intval($_POST['plan_id']      ?? 0);
        $quantity     = max(1, intval($_POST['quantity'] ?? 1));
        $promotion_id = intval($_POST['promotion_id'] ?? 0) ?: null;
        $invoice_date = date('Y-m-d');

        if ($plan_id === 0) {
            echo json_encode(['success' => false, 'message' => 'Gói tập không hợp lệ']);
            exit;
        }

        // Lấy thông tin gói
        $planRes = $conn->query("
            SELECT mp.plan_id, mp.plan_name, mp.price, mp.duration_months,
                   mp.package_type_id, pt.sort_order AS type_order
            FROM MembershipPlan mp
            LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
            WHERE mp.plan_id = $plan_id
        ");
        $plan = $planRes ? $planRes->fetch_assoc() : null;
        if (!$plan) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy gói tập']);
            exit;
        }

        $unit_price      = floatval($plan['price']);
        $original_amount = $unit_price * $quantity;

        // Tính upgrade credit
        $upgrade_credit  = 0;
        $new_pkg_type_id = $plan['package_type_id'] ? intval($plan['package_type_id']) : 0;
        if ($new_pkg_type_id > 0) {
            $upgrade_credit = calcUpgradeCredit($conn, $cid, $new_pkg_type_id);
        }

        $discount_amount = $upgrade_credit;
        $final_amount    = max(0, $original_amount - $upgrade_credit);

        // Áp dụng khuyến mãi
        if ($promotion_id) {
            $promo = $conn->query("
                SELECT * FROM Promotion
                WHERE promotion_id = $promotion_id
                  AND status = 'Active'
                  AND start_date <= CURDATE() AND end_date >= CURDATE()
                  AND (max_usage IS NULL OR max_usage > usage_count)
            ")->fetch_assoc();

            if (!$promo) {
                echo json_encode(['success' => false, 'message' => 'Khuyến mãi không còn hợp lệ']);
                exit;
            }
            if ($original_amount < floatval($promo['min_order_value'])) {
                echo json_encode(['success' => false, 'message' => 'Đơn hàng chưa đủ điều kiện áp dụng KM (tối thiểu ' . number_format($promo['min_order_value'],0,',','.') . '₫)']);
                exit;
            }
            $promo_disc = $original_amount * floatval($promo['discount_percent']) / 100;
            if ($promo['max_discount_amount'] !== null && $promo_disc > floatval($promo['max_discount_amount'])) {
                $promo_disc = floatval($promo['max_discount_amount']);
            }
            $discount_amount += $promo_disc;
            $final_amount     = max(0, $original_amount - $discount_amount);
        }

        // Transaction
        $conn->begin_transaction();
        try {
            $created_by = $_SESSION['username'] ?? 'customer';
            $note       = "Mua gói online — {$plan['plan_name']}";

            $stmt = $conn->prepare("
                INSERT INTO Invoice (customer_id, invoice_date, promotion_id, original_amount, discount_amount, final_amount, note, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
            ");
            $stmt->bind_param('isiiddss', $cid, $invoice_date, $promotion_id, $original_amount, $discount_amount, $final_amount, $note, $created_by);
            $stmt->execute();
            $invoice_id = $conn->insert_id;

            $subtotal = $unit_price * $quantity;
            $stmtItem = $conn->prepare("
                INSERT INTO InvoiceDetail (invoice_id, plan_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtItem->bind_param('iiidd', $invoice_id, $plan_id, $quantity, $unit_price, $subtotal);
            $stmtItem->execute();

            if ($promotion_id) {
                $conn->query("UPDATE Promotion SET usage_count = usage_count + 1 WHERE promotion_id = $promotion_id");
                $conn->query("UPDATE Promotion SET status = 'Expired' WHERE promotion_id = $promotion_id AND max_usage IS NOT NULL AND usage_count >= max_usage");
            }

            $conn->commit();

            // Tạo QR thanh toán
            $bank_config = ['bank_id' => 'MB', 'account_no' => '0981015808', 'account_name' => 'PHAM VAN HOANG'];
            $amount  = intval($final_amount);
            $info    = urlencode("ELITEGYM HD{$invoice_id}");
            $qr_url  = "https://img.vietqr.io/image/{$bank_config['bank_id']}-{$bank_config['account_no']}-compact2.png?amount={$amount}&addInfo={$info}&accountName=" . urlencode($bank_config['account_name']);

            echo json_encode([
                'success'         => true,
                'invoice_id'      => $invoice_id,
                'original_amount' => $original_amount,
                'discount_amount' => $discount_amount,
                'final_amount'    => $final_amount,
                'upgrade_credit'  => $upgrade_credit,
                'is_upgrade'      => $upgrade_credit > 0,
                'qr_url'          => $qr_url,
                'amount'          => $amount,
                'bank'            => $bank_config,
                'description'     => "ELITEGYM HD{$invoice_id}",
                'plan_name'       => $plan['plan_name'],
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi tạo đơn: ' . $e->getMessage()]);
        }
        break;

    // ════════════════════════════════════════════════════════
    // XÁC NHẬN THANH TOÁN THỦ CÔNG (tiền mặt / chuyển khoản)
    // ════════════════════════════════════════════════════════
    case 'confirm_payment':
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $method     = trim($_POST['method'] ?? 'Chuyển khoản');
        if ($invoice_id === 0) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }

        // Xác minh hóa đơn thuộc về khách này
        $check = $conn->query("SELECT customer_id, status FROM Invoice WHERE invoice_id = $invoice_id")->fetch_assoc();
        if (!$check || intval($check['customer_id']) !== $cid) {
            echo json_encode(['success' => false, 'message' => 'Không có quyền']); exit;
        }
        if ($check['status'] === 'Paid') {
            echo json_encode(['success' => true, 'message' => 'Đã thanh toán']); exit;
        }

        $note_append = "Thanh toán qua: $method — " . date('d/m/Y H:i');
        $stmt = $conn->prepare("
            UPDATE Invoice
            SET status = 'Paid',
                note   = CONCAT(IFNULL(note,''), IF(note IS NULL OR note='', '', ' | '), ?)
            WHERE invoice_id = ?
        ");
        $stmt->bind_param('si', $note_append, $invoice_id);
        if ($stmt->execute()) {
            registerPackagesForInvoice($conn, $invoice_id);
            echo json_encode(['success' => true, 'message' => 'Thanh toán thành công! Gói tập đã được kích hoạt.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi xác nhận thanh toán']);
        }
        break;

    // ════════════════════════════════════════════════════════
    // KIỂM TRA TRẠNG THÁI HÓA ĐƠN (polling QR)
    // ════════════════════════════════════════════════════════
    case 'check_status':
        $invoice_id = intval($_GET['invoice_id'] ?? 0);
        if ($invoice_id === 0) { echo json_encode(['status' => 'Pending']); exit; }
        $row = $conn->query("SELECT status FROM Invoice WHERE invoice_id = $invoice_id AND customer_id = $cid")->fetch_assoc();
        echo json_encode(['status' => $row['status'] ?? 'Pending']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
}
?>
