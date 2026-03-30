<?php
/**
 * payment_webhook.php
 * Đặt cùng thư mục với Invoice_Management_function.php
 * SePay POST JSON → tự động xác nhận thanh toán
 *
 * Cập nhật:
 *   - Xác thực API Key từ header Authorization
 *   - Kiểm tra số tiền trước khi xác nhận
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// ======================================================
// GHI LOG ĐỂ DEBUG
// ======================================================
function writeLog($msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(__DIR__ . '/webhook_log.txt', $line, FILE_APPEND | LOCK_EX);
}

writeLog('=== Webhook called ===');
writeLog('Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));

// ======================================================
// XÁC THỰC API KEY TỪ SEPAY
// SePay gửi header: Authorization: Apikey elitegym_PAY
// Đổi $SEPAY_API_KEY nếu bạn thay key trong SePay dashboard
// ======================================================
$SEPAY_API_KEY = 'elitegym_PAY';

$authHeader = '';
if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('getallheaders')) {
    $allHeaders = array_change_key_case(getallheaders(), CASE_LOWER);
    $authHeader = $allHeaders['authorization'] ?? '';
}

writeLog('Auth header: ' . ($authHeader !== '' ? $authHeader : '(empty)'));

if (!preg_match('/Apikey\s+(\S+)/i', $authHeader, $keyMatch)) {
    writeLog('BLOCKED: Thieu hoac sai Authorization header');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: missing API key']);
    exit;
}

if (trim($keyMatch[1]) !== $SEPAY_API_KEY) {
    writeLog('BLOCKED: API Key sai - nhan duoc: ' . $keyMatch[1]);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: invalid API key']);
    exit;
}

writeLog('API Key OK');

// ======================================================
// ĐỌC PAYLOAD JSON TỪ SEPAY
// ======================================================
$raw = file_get_contents('php://input');
writeLog('Raw body: ' . $raw);

$body = json_decode($raw, true);

if (!$body) {
    writeLog('ERROR: JSON khong hop le');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// ======================================================
// CHỈ XỬ LÝ TIỀN VÀO
// ======================================================
$transferType = $body['transferType'] ?? '';
writeLog('transferType: ' . $transferType);

if ($transferType !== 'in') {
    writeLog('Bo qua: khong phai tien vao');
    echo json_encode(['success' => true, 'message' => 'Ignored: not incoming']);
    exit;
}

$content = $body['content'] ?? $body['code'] ?? '';
$amount  = intval($body['transferAmount'] ?? 0);
writeLog("Content: $content | Amount: $amount");

// ======================================================
// TÁCH MÃ HÓA ĐƠN TỪ NỘI DUNG CHUYỂN KHOẢN
// ======================================================
if (!preg_match('/HD(\d+)/i', $content, $m)) {
    writeLog('Khong tim thay ma hoa don trong: ' . $content);
    echo json_encode(['success' => true, 'message' => 'No invoice ID in content']);
    exit;
}

$invoice_id = intval($m[1]);
writeLog("Invoice ID extracted: #$invoice_id");

// ======================================================
// KẾT NỐI DATABASE
// ======================================================
$db_path = null;
$candidates = [
    // Internal/Admin/ → ELite_GYM/Database/  ← ĐÚNG cho cấu trúc hiện tại
    __DIR__ . '/../../Database/db.php',
    // Fallback các vị trí khác
    __DIR__ . '/../Database/db.php',
    __DIR__ . '/../../../Database/db.php',
    __DIR__ . '/../../../../Database/db.php',
    dirname($_SERVER['DOCUMENT_ROOT']) . '/Database/db.php',
];
foreach ($candidates as $c) {
    writeLog("Trying DB path: $c | " . (file_exists($c) ? 'FOUND' : 'not found'));
    if (file_exists($c)) { $db_path = $c; break; }
}

if (!$db_path) {
    writeLog('ERROR: db.php khong tim thay');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'db.php not found']);
    exit;
}

writeLog("Using DB: $db_path");
require_once $db_path;

if (!isset($conn) || $conn->connect_error) {
    writeLog('ERROR: Ket noi DB that bai - ' . ($conn->connect_error ?? 'conn not set'));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}
writeLog('DB connected OK');

// ======================================================
// KIỂM TRA HÓA ĐƠN TỒN TẠI
// ======================================================
$stmt = $conn->prepare("SELECT invoice_id, final_amount, status FROM Invoice WHERE invoice_id = ?");
$stmt->bind_param('i', $invoice_id);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();

if (!$inv) {
    writeLog("Invoice #$invoice_id KHONG TON TAI trong DB");
    echo json_encode(['success' => false, 'message' => "Invoice #$invoice_id not found"]);
    exit;
}

writeLog("Invoice #$invoice_id found. Status: " . $inv['status'] . " | Amount in DB: " . $inv['final_amount']);

if ($inv['status'] === 'Paid') {
    writeLog("Invoice #$invoice_id da Paid roi, bo qua");
    echo json_encode(['success' => true, 'message' => 'Already paid']);
    exit;
}

// ======================================================
// KIỂM TRA SỐ TIỀN CHUYỂN KHOẢN
// Từ chối nếu nhận < final_amount | Cho phép chuyển thừa
// ======================================================
$expected = intval(floatval($inv['final_amount']));

if ($amount < $expected) {
    writeLog("REJECTED: So tien khong khop - nhan: {$amount} | can: {$expected} | thieu: " . ($expected - $amount));
    // Trả 200 để SePay không retry, nhưng KHÔNG cập nhật DB
    echo json_encode([
        'success' => false,
        'message' => "Amount mismatch: received {$amount}, expected {$expected}"
    ]);
    exit;
}

if ($amount > $expected) {
    writeLog("WARNING: Khach chuyen thua - nhan: {$amount} | can: {$expected} | du: " . ($amount - $expected));
}

writeLog("Kiem tra so tien OK: {$amount} >= {$expected}");

// ======================================================
// XÁC NHẬN THANH TOÁN — ĐÃ QUA KIỂM TRA SỐ TIỀN
// ======================================================
$ref         = $body['referenceCode'] ?? strval($body['id'] ?? 'N/A');
$txDate      = $body['transactionDate'] ?? date('d/m/Y H:i');
$note_append = "Auto SePay - $txDate - Ref: $ref - " . number_format($amount) . "d";

$stmt = $conn->prepare("
    UPDATE Invoice
    SET status = 'Paid',
        note   = CONCAT(IFNULL(note,''), IF(note IS NULL OR note = '', '', ' | '), ?)
    WHERE invoice_id = ?
      AND status != 'Paid'
");
$stmt->bind_param('si', $note_append, $invoice_id);
$ok = $stmt->execute();

if (!$ok) {
    writeLog("ERROR: DB update that bai - " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB update failed']);
    exit;
}

writeLog("Invoice #$invoice_id cap nhat Paid thanh cong!");

// ======================================================
// ĐĂNG KÝ GÓI TẬP (MembershipRegistration)
// ======================================================
function registerPackagesForInvoice($conn, $invoice_id) {
    $inv = $conn->query("SELECT customer_id, invoice_date FROM Invoice WHERE invoice_id = $invoice_id")->fetch_assoc();
    if (!$inv) { writeLog("registerPackages: khong tim thay invoice"); return; }

    $customer_id = intval($inv['customer_id']);
    $today       = $inv['invoice_date'];

    $ctRes = $conn->query("
        SELECT ct.plan_id, ct.quantity, mp.duration_months,
               mp.package_type_id, pt.sort_order AS type_order
        FROM InvoiceDetail ct
        JOIN MembershipPlan mp ON mp.plan_id = ct.plan_id
        LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
        WHERE ct.invoice_id = $invoice_id
    ");
    if (!$ctRes) { writeLog("registerPackages: khong co invoice detail"); return; }

    while ($row = $ctRes->fetch_assoc()) {
        $plan_id      = intval($row['plan_id']);
        $quantity     = intval($row['quantity']);
        $duration     = intval($row['duration_months'] ?? 1);
        $total_months = $duration * $quantity;
        $new_type_id  = $row['package_type_id'] ? intval($row['package_type_id']) : null;
        $new_type_ord = $row['type_order']       ? intval($row['type_order'])       : 0;

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
            writeLog("Nang cap: old_order={$activeReg['old_type_order']} < new_order=$new_type_ord");
        } else {
            if ($new_type_id) {
                $existRes = $conn->query("
                    SELECT MAX(mr.end_date) AS max_end
                    FROM MembershipRegistration mr
                    JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
                    WHERE mr.customer_id = $customer_id
                      AND mp.package_type_id = $new_type_id
                ");
            } else {
                $existRes = $conn->query("
                    SELECT MAX(end_date) AS max_end
                    FROM MembershipRegistration
                    WHERE customer_id = $customer_id AND plan_id = $plan_id
                ");
            }
            $max_end    = $existRes ? ($existRes->fetch_assoc()['max_end'] ?? null) : null;
            $start_date = ($max_end && $max_end >= $today) ? $max_end : $today;
        }

        $end_date = date('Y-m-d', strtotime("$start_date +$total_months months"));
        writeLog("Dang ky: plan_id=$plan_id | start=$start_date | end=$end_date | months=$total_months");

        $stmtDK = $conn->prepare("
            INSERT INTO MembershipRegistration (customer_id, plan_id, start_date, end_date, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmtDK->bind_param('iiss', $customer_id, $plan_id, $start_date, $end_date);
        $stmtDK->execute();
    }
}

registerPackagesForInvoice($conn, $invoice_id);
writeLog("Da dang ky goi tap cho invoice #$invoice_id");

// ======================================================
// TRẢ HTTP 200 — BẮT BUỘC để SePay không gọi lại
// ======================================================
echo json_encode([
    'success' => true,
    'message' => "Invoice #$invoice_id marked as Paid successfully"
]);

writeLog('=== Done ===');
