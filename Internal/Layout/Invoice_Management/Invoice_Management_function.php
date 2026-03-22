<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../Internal/auth_check.php';
requireRole(['Admin', 'Employee']);

require_once '../../../Database/db.php';

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ========================
// HELPER
// ========================
function safe_query($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) return null;
    return $r->fetch_assoc();
}

/**
 * Đăng ký gói tập vào MembershipRegistration sau khi hóa đơn được thanh toán.
 * Nếu khách đã có gói còn hiệu lực → nối tiếp từ ngày kết thúc cũ + 1 ngày.
 */
function registerPackagesForInvoice($conn, $invoice_id) {
    $inv = $conn->query("SELECT customer_id, invoice_date FROM Invoice WHERE invoice_id = $invoice_id")->fetch_assoc();
    if (!$inv) return;

    $customer_id  = intval($inv['customer_id']);
    $invoice_date = $inv['invoice_date'];

    $ctRes = $conn->query("
        SELECT ct.plan_id, ct.quantity, mp.duration_months
        FROM InvoiceDetail ct
        JOIN MembershipPlan mp ON mp.plan_id = ct.plan_id
        WHERE ct.invoice_id = $invoice_id
    ");
    if (!$ctRes) return;

    $stmtDK = $conn->prepare("
        INSERT INTO MembershipRegistration (customer_id, plan_id, start_date, end_date)
        VALUES (?, ?, ?, ?)
    ");

    while ($row = $ctRes->fetch_assoc()) {
        $plan_id        = intval($row['plan_id']);
        $quantity       = intval($row['quantity']);
        $duration       = intval($row['duration_months'] ?? 1);
        $total_months   = $duration * $quantity;

        $existRes = $conn->query("
            SELECT MAX(end_date) as max_end
            FROM MembershipRegistration
            WHERE customer_id = $customer_id
              AND plan_id    = $plan_id
        ");
        $max_end = $existRes ? ($existRes->fetch_assoc()['max_end'] ?? null) : null;

        if ($max_end && $max_end >= $invoice_date) {
            $start_date = date('Y-m-d', strtotime("$max_end +1 day"));
        } else {
            $start_date = $invoice_date;
        }
        $end_date = date('Y-m-d', strtotime("$start_date +$total_months months"));

        $stmtDK->bind_param('iiss', $customer_id, $plan_id, $start_date, $end_date);
        $stmtDK->execute();
    }
}

switch ($action) {

    // ========================
    // THỐNG KÊ STATS
    // ========================
    case 'get_stats':
        $today     = date('Y-m-d');
        $thisMonth = date('Y-m');

        $total = $conn->query("SELECT COUNT(*) as c FROM Invoice")->fetch_assoc()['c'];

        $revenue_month = $conn->query("
            SELECT COALESCE(SUM(final_amount), 0) as r FROM Invoice
            WHERE DATE_FORMAT(invoice_date, '%Y-%m') = '$thisMonth'
        ")->fetch_assoc()['r'];

        $today_count = $conn->query("
            SELECT COUNT(*) as c FROM Invoice WHERE invoice_date = '$today'
        ")->fetch_assoc()['c'];

        $promo_used = $conn->query("
            SELECT COUNT(*) as c FROM Invoice WHERE promotion_id IS NOT NULL
        ")->fetch_assoc()['c'];

        $avg_val = $conn->query("
            SELECT COALESCE(AVG(final_amount), 0) as a FROM Invoice
        ")->fetch_assoc()['a'];

        echo json_encode([
            'success'       => true,
            'total'         => intval($total),
            'revenue_month' => floatval($revenue_month),
            'today_count'   => intval($today_count),
            'promo_used'    => intval($promo_used),
            'avg_value'     => floatval($avg_val),
        ]);
        break;

    // ========================
    // DANH SÁCH HÓA ĐƠN
    // ========================
    case 'get_invoices':
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = intval($_GET['limit'] ?? 15);
        $search = trim($_GET['search'] ?? '');
        $sort   = $_GET['sort'] ?? 'id_desc';
        $month  = trim($_GET['month'] ?? '');
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];
        $types  = '';

        if ($search !== '') {
            $where[] = "(c.full_name LIKE ? OR c.phone LIKE ? OR inv.invoice_id LIKE ?)";
            $s = "%$search%";
            $params[] = $s; $params[] = $s; $params[] = $s;
            $types .= 'sss';
        }

        if ($month !== '') {
            $where[] = "DATE_FORMAT(inv.invoice_date, '%Y-%m') = ?";
            $params[] = $month;
            $types .= 's';
        }

        $status = trim($_GET['status'] ?? '');
        if ($status !== '') {
            $where[] = "inv.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        $whereStr = implode(' AND ', $where);

        $orderBy = match($sort) {
            'id_asc'       => 'inv.invoice_id ASC',
            'total_desc'   => 'inv.final_amount DESC',
            'total_asc'    => 'inv.final_amount ASC',
            'date_asc'     => 'inv.invoice_date ASC',
            default        => 'inv.invoice_id DESC'
        };

        // COUNT
        $countSql = "
            SELECT COUNT(*) as total
            FROM Invoice inv
            JOIN Customer c ON c.customer_id = inv.customer_id
            WHERE $whereStr
        ";
        $stmt = $conn->prepare($countSql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];

        // DATA
        $dataSql = "
            SELECT
                inv.invoice_id,
                inv.invoice_date,
                inv.original_amount,
                inv.discount_amount,
                inv.final_amount,
                inv.status,
                inv.note,
                c.customer_id,
                c.full_name     AS ten_khach,
                c.phone,
                inv.created_by,
                p.promotion_id,
                p.promotion_name,
                p.discount_percent
            FROM Invoice inv
            JOIN Customer c ON c.customer_id = inv.customer_id
            LEFT JOIN Promotion p ON p.promotion_id = inv.promotion_id
            WHERE $whereStr
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ";
        $dp = $params;
        $dt = $types;
        $dp[] = $limit; $dp[] = $offset;
        $dt .= 'ii';

        $stmt = $conn->prepare($dataSql);
        $stmt->bind_param($dt, ...$dp);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'success'    => true,
            'data'       => $rows,
            'total'      => intval($total),
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => max(1, ceil($total / $limit)),
        ]);
        break;

    // ========================
    // CHI TIẾT HÓA ĐƠN
    // ========================
    case 'get_detail':
        $id = intval($_GET['id'] ?? 0);
        if ($id === 0) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }

        // Header
        $stmt = $conn->prepare("
            SELECT
                inv.*,
                c.full_name AS ten_khach,
                c.phone, c.email,
                inv.created_by,
                p.promotion_name, p.discount_percent, p.max_discount_amount
            FROM Invoice inv
            JOIN Customer c ON c.customer_id = inv.customer_id
            LEFT JOIN Promotion p ON p.promotion_id = inv.promotion_id
            WHERE inv.invoice_id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        if (!$invoice) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn']); exit; }

        // Chi tiết
        $stmt = $conn->prepare("
            SELECT
                ct.detail_id,
                ct.quantity,
                ct.unit_price,
                ct.subtotal,
                mp.plan_id,
                mp.plan_name,
                mp.duration_months
            FROM InvoiceDetail ct
            JOIN MembershipPlan mp ON mp.plan_id = ct.plan_id
            WHERE ct.invoice_id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success' => true, 'invoice' => $invoice, 'items' => $items]);
        break;

    // ========================
    // LẤY KHÁCH HÀNG (cho autocomplete)
    // ========================
    case 'get_customers':
        $q = trim($_GET['q'] ?? '');
        $stmt = $conn->prepare("
            SELECT customer_id, full_name, phone, email
            FROM Customer
            WHERE full_name LIKE ? OR phone LIKE ?
            LIMIT 10
        ");
        $s = "%$q%";
        $stmt->bind_param('ss', $s, $s);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ========================
    // LẤY GÓI TẬP (cho dropdown)
    // ========================
    case 'get_packages':
        $stmt = $conn->prepare("SELECT plan_id, plan_name, duration_months, price FROM MembershipPlan ORDER BY price ASC");
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ========================
    // LẤY KHUYẾN MÃI ÁP DỤNG ĐƯỢC
    // ========================
    case 'get_promotions':
        $total_amount = floatval($_GET['total'] ?? 0);
        $today = date('Y-m-d');

        $stmt = $conn->prepare("
            SELECT
                promotion_id,
                promotion_name,
                discount_percent,
                min_order_value,
                max_discount_amount,
                max_usage,
                usage_count
            FROM Promotion
            WHERE status = 'Active'
              AND start_date <= ?
              AND end_date   >= ?
              AND (max_usage IS NULL OR max_usage > usage_count)
              AND min_order_value <= ?
            ORDER BY discount_percent DESC
        ");
        $stmt->bind_param('ssd', $today, $today, $total_amount);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ========================
    // THÊM HÓA ĐƠN
    // ========================
    case 'add_invoice':
        $created_by    = $_SESSION['username'] ?? 'unknown';
        $customer_id   = intval($_POST['customer_id'] ?? 0);
        $invoice_date  = trim($_POST['invoice_date'] ?? date('Y-m-d'));
        $promotion_id  = intval($_POST['promotion_id'] ?? 0) ?: null;
        $note          = trim($_POST['note'] ?? '') ?: null;
        $status        = 'Pending'; // Luôn tạo với trạng thái chờ thanh toán
        $items_json    = $_POST['items'] ?? '[]';
        $items         = json_decode($items_json, true);

        if ($customer_id === 0 || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit;
        }

        // Tính tổng tiền gốc
        $original_amount = 0;
        foreach ($items as $item) {
            $original_amount += floatval($item['unit_price']) * intval($item['quantity']);
        }

        // Áp dụng khuyến mãi
        $discount_amount = 0;
        $final_amount    = $original_amount;

        if ($promotion_id) {
            $promo = $conn->query("
                SELECT * FROM Promotion
                WHERE promotion_id = $promotion_id
                  AND status = 'Active'
                  AND start_date <= CURDATE()
                  AND end_date   >= CURDATE()
                  AND (max_usage IS NULL OR max_usage > usage_count)
            ")->fetch_assoc();

            if (!$promo) {
                echo json_encode(['success' => false, 'message' => 'Khuyến mãi không còn hợp lệ hoặc đã hết lượt']);
                exit;
            }

            if ($original_amount < floatval($promo['min_order_value'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Giá trị đơn hàng chưa đủ điều kiện áp dụng khuyến mãi (tối thiểu ' .
                        number_format($promo['min_order_value'], 0, ',', '.') . '₫)'
                ]);
                exit;
            }

            $discount_amount = $original_amount * floatval($promo['discount_percent']) / 100;
            if ($promo['max_discount_amount'] !== null && $discount_amount > floatval($promo['max_discount_amount'])) {
                $discount_amount = floatval($promo['max_discount_amount']);
            }
            $final_amount = max(0, $original_amount - $discount_amount);
        }

        // Transaction
        $conn->begin_transaction();
        try {
            // Insert Invoice
            $stmt = $conn->prepare("
                INSERT INTO Invoice (customer_id, invoice_date, promotion_id, original_amount, discount_amount, final_amount, note, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('isiiddsss', $customer_id, $invoice_date, $promotion_id, $original_amount, $discount_amount, $final_amount, $note, $status, $created_by);
            $stmt->execute();
            $invoice_id = $conn->insert_id;

            // Insert InvoiceDetail
            $stmtItem = $conn->prepare("
                INSERT INTO InvoiceDetail (invoice_id, plan_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $plan_id    = intval($item['plan_id']);
                $quantity   = intval($item['quantity']);
                $unit_price = floatval($item['unit_price']);
                $subtotal   = $quantity * $unit_price;

                $stmtItem->bind_param('iiidd', $invoice_id, $plan_id, $quantity, $unit_price, $subtotal);
                $stmtItem->execute();
            }

            // Cập nhật khuyến mãi nếu có
            if ($promotion_id) {
                $conn->query("
                    UPDATE Promotion
                    SET usage_count = usage_count + 1
                    WHERE promotion_id = $promotion_id
                ");
                $conn->query("
                    UPDATE Promotion
                    SET status = 'Expired'
                    WHERE promotion_id = $promotion_id
                      AND max_usage IS NOT NULL
                      AND usage_count >= max_usage
                ");
            }

            $conn->commit();

            echo json_encode([
                'success'         => true,
                'message'         => 'Tạo hóa đơn thành công',
                'id'              => $invoice_id,
                'original_amount' => $original_amount,
                'discount_amount' => $discount_amount,
                'final_amount'    => $final_amount,
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    // ========================
    // XÓA HÓA ĐƠN
    // ========================
    case 'delete_invoice':
        // Chỉ Admin mới được xóa hóa đơn
        if (($_SESSION['role'] ?? '') !== 'Admin') {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa hóa đơn']);
            exit;
        }
        $id = intval($_POST['id'] ?? 0);
        if ($id === 0) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }

        $inv = $conn->query("SELECT promotion_id FROM Invoice WHERE invoice_id = $id")->fetch_assoc();
        if (!$inv) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn']); exit; }

        $planRows = [];
        $ctRes = $conn->query("SELECT plan_id FROM InvoiceDetail WHERE invoice_id = $id");
        while ($row = $ctRes->fetch_assoc()) $planRows[] = intval($row['plan_id']);

        $customer_id = intval($conn->query("SELECT customer_id FROM Invoice WHERE invoice_id = $id")->fetch_assoc()['customer_id']);

        $conn->begin_transaction();
        try {
            // 1. Xóa MembershipRegistration tương ứng
            if (!empty($planRows) && $customer_id > 0) {
                foreach ($planRows as $plan_id) {
                    $conn->query("
                        DELETE FROM MembershipRegistration
                        WHERE customer_id = $customer_id
                          AND plan_id = $plan_id
                        ORDER BY registration_id DESC
                        LIMIT 1
                    ");
                }
            }

            // 2. Xóa InvoiceDetail
            $conn->query("DELETE FROM InvoiceDetail WHERE invoice_id = $id");

            // 3. Xóa Invoice
            $conn->query("DELETE FROM Invoice WHERE invoice_id = $id");

            // Hoàn lại lượt KM
            if ($inv['promotion_id']) {
                $promo_id = intval($inv['promotion_id']);
                $conn->query("
                    UPDATE Promotion
                    SET usage_count = GREATEST(0, usage_count - 1)
                    WHERE promotion_id = $promo_id
                ");
                $conn->query("
                    UPDATE Promotion
                    SET status = 'Active'
                    WHERE promotion_id = $promo_id
                      AND status = 'Expired'
                      AND max_usage IS NOT NULL
                      AND usage_count < max_usage
                      AND end_date >= CURDATE()
                ");
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Đã xóa hóa đơn']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    // ========================
    // CẬP NHẬT TRẠNG THÁI
    // ========================
    case 'update_status':
        $id     = intval($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $allowed = ['Paid', 'Pending', 'Cancelled'];

        if ($id === 0 || !in_array($status, $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit;
        }

        $curRow     = $conn->query("SELECT status FROM Invoice WHERE invoice_id = $id")->fetch_assoc();
        $old_status = $curRow['status'] ?? '';

        $stmt = $conn->prepare("UPDATE Invoice SET status = ? WHERE invoice_id = ?");
        $stmt->bind_param('si', $status, $id);
        if ($stmt->execute()) {
            if ($status === 'Paid' && $old_status !== 'Paid') {
                registerPackagesForInvoice($conn, $id);
            }
            echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $conn->error]);
        }
        break;

    // ========================
    // THÔNG TIN THANH TOÁN QR
    // ========================
    case 'get_payment_info':
        $id = intval($_GET['id'] ?? 0);
        if ($id === 0) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }

        $stmt = $conn->prepare("
            SELECT inv.*, c.full_name AS ten_khach, c.phone, c.email
            FROM Invoice inv
            JOIN Customer c ON c.customer_id = inv.customer_id
            WHERE inv.invoice_id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $inv = $stmt->get_result()->fetch_assoc();

        if (!$inv) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn']); exit; }

        // ==== CẤU HÌNH NGÂN HÀNG ====
        $bank_config = [
            'bank_id'      => 'MB',
            'account_no'   => '0981015808',
            'account_name' => 'PHAM VAN HOANG',
        ];

        $amount     = intval(floatval($inv['final_amount']));
        $info       = urlencode("ELITEGYM HD{$id} " . preg_replace('/[^a-zA-Z0-9 ]/', '', $inv['ten_khach']));
        $bank_id    = $bank_config['bank_id'];
        $account_no = $bank_config['account_no'];
        $acc_name   = urlencode($bank_config['account_name']);

        $qr_url = "https://img.vietqr.io/image/{$bank_id}-{$account_no}-compact2.png"
                . "?amount={$amount}&addInfo={$info}&accountName={$acc_name}";

        echo json_encode([
            'success'     => true,
            'invoice'     => $inv,
            'qr_url'      => $qr_url,
            'amount'      => $amount,
            'bank'        => $bank_config,
            'description' => "ELITEGYM HD{$id} {$inv['ten_khach']}",
        ]);
        break;

    // ========================
    // XÁC NHẬN THANH TOÁN THỦ CÔNG
    // ========================
    case 'confirm_payment':
        $id     = intval($_POST['id'] ?? 0);
        $method = trim($_POST['phuong_thuc'] ?? 'Chuyển khoản');
        if ($id === 0) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }

        $note_append = "Thanh toán qua: $method - " . date('d/m/Y H:i');
        $stmt = $conn->prepare("
            UPDATE Invoice
            SET status = 'Paid',
                note = CONCAT(IFNULL(note,''), IF(note IS NULL OR note='', '', ' | '), ?)
            WHERE invoice_id = ?
        ");
        $stmt->bind_param('si', $note_append, $id);
        if ($stmt->execute()) {
            registerPackagesForInvoice($conn, $id);
            echo json_encode(['success' => true, 'message' => 'Xác nhận thanh toán thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $conn->error]);
        }
        break;

    // ========================
    // KIỂM TRA TRẠNG THÁI (POLLING TỰ ĐỘNG)
    // ========================
    case 'check_payment_status':
        $id = intval($_GET['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['status' => 'Pending']);
            exit;
        }
        $row = $conn->query("SELECT status FROM Invoice WHERE invoice_id = $id")->fetch_assoc();
        echo json_encode(['status' => $row['status'] ?? 'Pending']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
}
?>
