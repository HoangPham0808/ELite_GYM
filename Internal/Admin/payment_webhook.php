<?php
/**
 * payment_webhook.php
 * Đặt cùng thư mục với Invoice_Management_function.php
 * SePay POST JSON → tự động xác nhận thanh toán ngay lập tức
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

// ======================================================
// GHI LOG ĐỂ DEBUG (xem file webhook_log.txt cùng thư mục)
// ======================================================
function writeLog($msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(__DIR__ . '/webhook_log.txt', $line, FILE_APPEND);
}

writeLog('=== Webhook called ===');
writeLog('Method: ' . $_SERVER['REQUEST_METHOD']);

// ======================================================
// ĐỌC PAYLOAD JSON TỪ SEPAY
// ======================================================
$raw  = file_get_contents('php://input');
writeLog('Raw body: ' . $raw);

$body = json_decode($raw, true);

if (!$body) {
    writeLog('ERROR: Invalid JSON');
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
    writeLog('Ignored: not incoming transfer');
    echo json_encode(['success' => true, 'message' => 'Ignored: not incoming']);
    exit;
}

$content = $body['content'] ?? $body['code'] ?? '';
$amount  = intval($body['transferAmount'] ?? 0);
writeLog("Content: $content | Amount: $amount");

// ======================================================
// TÁCH MÃ HÓA ĐƠN TỪ NỘI DUNG CHUYỂN KHOẢN
// Nội dung: "ELITEGYM HD9 Nguyen Van A" → lấy số 9
// ======================================================
if (!preg_match('/HD(\d+)/i', $content, $m)) {
    writeLog('No invoice ID found in content: ' . $content);
    echo json_encode(['success' => true, 'message' => 'No invoice ID in content']);
    exit;
}

$invoice_id = intval($m[1]);
writeLog("Invoice ID extracted: #$invoice_id");

// ======================================================
// KẾT NỐI DATABASE — tự tìm db.php
// ======================================================
$db_path = null;
$candidates = [
    __DIR__ . '/../../../Database/db.php',
    __DIR__ . '/../../Database/db.php',
    __DIR__ . '/../Database/db.php',
    __DIR__ . '/../../../../Database/db.php',
];
foreach ($candidates as $c) {
    writeLog("Trying DB path: $c | " . (file_exists($c) ? 'FOUND' : 'not found'));
    if (file_exists($c)) { $db_path = $c; break; }
}

if (!$db_path) {
    writeLog('ERROR: db.php not found in any candidate path');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'db.php not found']);
    exit;
}

writeLog("Using DB: $db_path");
require_once $db_path;

if (!isset($conn) || $conn->connect_error) {
    writeLog('ERROR: DB connection failed - ' . ($conn->connect_error ?? 'conn not set'));
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
    writeLog("Invoice #$invoice_id NOT FOUND in DB");
    echo json_encode(['success' => false, 'message' => "Invoice #$invoice_id not found"]);
    exit;
}

writeLog("Invoice #$invoice_id found. Status: " . $inv['status'] . " | Amount in DB: " . $inv['final_amount']);

// Đã Paid rồi thì bỏ qua
if ($inv['status'] === 'Paid') {
    writeLog("Invoice #$invoice_id already Paid, skip");
    echo json_encode(['success' => true, 'message' => "Already paid"]);
    exit;
}

// ======================================================
// XÁC NHẬN THANH TOÁN NGAY — KHÔNG KIỂM TRA SỐ TIỀN
// ======================================================
$ref         = $body['referenceCode'] ?? strval($body['id'] ?? 'N/A');
$txDate      = $body['transactionDate'] ?? date('d/m/Y H:i');
$note_append = "Auto SePay - $txDate - Ref: $ref - " . number_format($amount) . "đ";

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
    writeLog("ERROR: DB update failed - " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB update failed']);
    exit;
}

writeLog("Invoice #$invoice_id updated to Paid successfully!");

// ======================================================
// ĐĂNG KÝ GÓI TẬP (MembershipRegistration)
// ======================================================
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
        $plan_id      = intval($row['plan_id']);
        $quantity     = intval($row['quantity']);
        $duration     = intval($row['duration_months'] ?? 1);
        $total_months = $duration * $quantity;

        $existRes = $conn->query("
            SELECT MAX(end_date) as max_end
            FROM MembershipRegistration
            WHERE customer_id = $customer_id AND plan_id = $plan_id
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

registerPackagesForInvoice($conn, $invoice_id);
writeLog("Packages registered for invoice #$invoice_id");

// ======================================================
// TRẢ HTTP 200 — BẮT BUỘC để SePay không gọi lại
// ======================================================
echo json_encode([
    'success' => true,
    'message' => "Invoice #$invoice_id marked as Paid successfully"
]);

writeLog('=== Done ===');
