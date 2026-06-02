<?php

class PaymentController extends Controller
{
    private Payment $paymentModel;

    public function __construct()
    {
        $this->paymentModel = new Payment();
    }

    public function api(): void
    {
        AuthMiddleware::requireRole('Customer');
        $this->ensureSession();

        if (!isset($_SESSION['account_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']); exit;
        }

        $cid = $this->paymentModel->getCustomerIdByAccountId((int)$_SESSION['account_id']);
        if (!$cid) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy tài khoản']); exit; }

        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'get_plans':
                echo json_encode(['success' => true, ...$this->paymentModel->getPlansForCustomer($cid)]);
                break;

            case 'get_promotions':
                $total = floatval($_GET['total'] ?? 0);
                echo json_encode(['success' => true, 'data' => $this->paymentModel->getActivePromotions($total)]);
                break;

            case 'get_upgrade_credit':
                $newPkgTypeId = intval($_GET['package_type_id'] ?? 0);
                if ($newPkgTypeId === 0) { echo json_encode(['success' => true, 'credit' => 0, 'is_upgrade' => false]); exit; }
                echo json_encode(['success' => true, ...$this->paymentModel->getUpgradeCreditInfo($cid, $newPkgTypeId)]);
                break;

            case 'create_order':
                $plan_id      = intval($_POST['plan_id'] ?? 0);
                $quantity     = max(1, intval($_POST['quantity'] ?? 1));
                $promotion_id = intval($_POST['promotion_id'] ?? 0) ?: null;

                if ($plan_id === 0) { echo json_encode(['success' => false, 'message' => 'Gói tập không hợp lệ']); exit; }

                $plan = $this->paymentModel->getPlanInfo($plan_id);
                if (!$plan) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy gói tập']); exit; }

                $unit_price      = floatval($plan['price']);
                $original_amount = $unit_price * $quantity;
                $upgrade_credit  = 0;
                $new_pkg_type_id = $plan['package_type_id'] ? (int)$plan['package_type_id'] : 0;
                if ($new_pkg_type_id > 0) $upgrade_credit = $this->paymentModel->calcUpgradeCredit($cid, $new_pkg_type_id);

                $discount_amount = $upgrade_credit;
                $final_amount    = max(0, $original_amount - $upgrade_credit);

                if ($promotion_id) {
                    $promos = $this->paymentModel->getActivePromotions($original_amount);
                    $promo  = null;
                    foreach ($promos as $p) { if ($p['promotion_id'] == $promotion_id) { $promo = $p; break; } }
                    if (!$promo) { echo json_encode(['success' => false, 'message' => 'Khuyến mãi không còn hợp lệ']); exit; }
                    if ($original_amount < floatval($promo['min_order_value'])) { echo json_encode(['success' => false, 'message' => 'Đơn hàng chưa đủ điều kiện áp dụng KM (tối thiểu ' . number_format($promo['min_order_value'],0,',','.') . '₫)']); exit; }
                    $promo_disc = $original_amount * floatval($promo['discount_percent']) / 100;
                    if ($promo['max_discount_amount'] !== null && $promo_disc > floatval($promo['max_discount_amount'])) $promo_disc = floatval($promo['max_discount_amount']);
                    $discount_amount += $promo_disc;
                    $final_amount = max(0, $original_amount - $discount_amount);
                }

                $created_by = $_SESSION['username'] ?? 'customer';
                $invoice_id = $this->paymentModel->createOrder($cid, $plan_id, $quantity, $promotion_id, $original_amount, $discount_amount, $final_amount, $plan['plan_name'], $created_by);

                if ($invoice_id) {
                    $bank = ['bank_id' => 'MB', 'account_no' => '0981015808', 'account_name' => 'PHAM VAN HOANG'];
                    $amount = intval($final_amount);
                    $info   = urlencode("ELITEGYM HD{$invoice_id}");
                    $qr_url = "https://img.vietqr.io/image/{$bank['bank_id']}-{$bank['account_no']}-compact2.png?amount={$amount}&addInfo={$info}&accountName=" . urlencode($bank['account_name']);

                    echo json_encode(['success' => true, 'invoice_id' => $invoice_id, 'original_amount' => $original_amount, 'discount_amount' => $discount_amount, 'final_amount' => $final_amount, 'upgrade_credit' => $upgrade_credit, 'is_upgrade' => $upgrade_credit > 0, 'qr_url' => $qr_url, 'amount' => $amount, 'bank' => $bank, 'description' => "ELITEGYM HD{$invoice_id}", 'plan_name' => $plan['plan_name']]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Lỗi tạo đơn']);
                }
                break;

            case 'confirm_payment':
                $invoice_id = intval($_POST['invoice_id'] ?? 0);
                $method     = trim($_POST['method'] ?? 'Chuyển khoản');
                if ($invoice_id === 0) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }
                echo json_encode($this->paymentModel->confirmPayment($invoice_id, $cid, $method));
                break;

            case 'check_status':
                $invoice_id = intval($_GET['invoice_id'] ?? 0);
                echo json_encode(['status' => $invoice_id > 0 ? $this->paymentModel->checkStatus($invoice_id, $cid) : 'Pending']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
        }
    }

    public function webhook(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $writeLog = function($msg) {
            $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
            @file_put_contents(STORAGE_PATH . '/logs/webhook_log.txt', $line, FILE_APPEND | LOCK_EX);
        };

        $writeLog('=== Webhook called ===');
        $writeLog('Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));

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

        $writeLog('Auth header: ' . ($authHeader !== '' ? $authHeader : '(empty)'));

        if (!preg_match('/Apikey\s+(\S+)/i', $authHeader, $keyMatch)) {
            $writeLog('BLOCKED: Thieu hoac sai Authorization header');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: missing API key']);
            exit;
        }

        if (trim($keyMatch[1]) !== $SEPAY_API_KEY) {
            $writeLog('BLOCKED: API Key sai - nhan duoc: ' . $keyMatch[1]);
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden: invalid API key']);
            exit;
        }

        $writeLog('API Key OK');

        $raw = file_get_contents('php://input');
        $writeLog('Raw body: ' . $raw);

        $body = json_decode($raw, true);
        if (!$body) {
            $writeLog('ERROR: JSON khong hop le');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
            exit;
        }

        $transferType = $body['transferType'] ?? '';
        $writeLog('transferType: ' . $transferType);

        if ($transferType !== 'in') {
            $writeLog('Bo qua: khong phai tien vao');
            echo json_encode(['success' => true, 'message' => 'Ignored: not incoming']);
            exit;
        }

        $content = $body['content'] ?? $body['code'] ?? '';
        $amount  = intval($body['transferAmount'] ?? 0);
        $writeLog("Content: $content | Amount: $amount");

        if (!preg_match('/HD(\d+)/i', $content, $m)) {
            $writeLog('Khong tim thay ma hoa don trong: ' . $content);
            echo json_encode(['success' => true, 'message' => 'No invoice ID in content']);
            exit;
        }

        $invoice_id = intval($m[1]);
        $writeLog("Invoice ID extracted: #$invoice_id");

        // Lấy thông tin hóa đơn
        $stmt = $this->paymentModel->db->prepare("SELECT invoice_id, final_amount, status FROM Invoice WHERE invoice_id = ?");
        $stmt->bind_param('i', $invoice_id);
        $stmt->execute();
        $inv = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$inv) {
            $writeLog("Invoice #$invoice_id KHONG TON TAI trong DB");
            echo json_encode(['success' => false, 'message' => "Invoice #$invoice_id not found"]);
            exit;
        }

        $writeLog("Invoice #$invoice_id found. Status: " . $inv['status'] . " | Amount in DB: " . $inv['final_amount']);

        if ($inv['status'] === 'Paid') {
            $writeLog("Invoice #$invoice_id da Paid roi, bo qua");
            echo json_encode(['success' => true, 'message' => 'Already paid']);
            exit;
        }

        $expected = intval(floatval($inv['final_amount']));
        if ($amount < $expected) {
            $writeLog("REJECTED: So tien khong khop - nhan: {$amount} | can: {$expected} | thieu: " . ($expected - $amount));
            echo json_encode([
                'success' => false,
                'message' => "Amount mismatch: received {$amount}, expected {$expected}"
            ]);
            exit;
        }

        if ($amount > $expected) {
            $writeLog("WARNING: Khach chuyen thua - nhan: {$amount} | can: {$expected} | du: " . ($amount - $expected));
        }

        $ref         = $body['referenceCode'] ?? strval($body['id'] ?? 'N/A');
        $txDate      = $body['transactionDate'] ?? date('d/m/Y H:i');
        $note_append = "Auto SePay - $txDate - Ref: $ref - " . number_format($amount) . "d";

        $stmt = $this->paymentModel->db->prepare("
            UPDATE Invoice
            SET status = 'Paid',
                note   = CONCAT(IFNULL(note,''), IF(note IS NULL OR note = '', '', ' | '), ?)
            WHERE invoice_id = ?
              AND status != 'Paid'
        ");
        $stmt->bind_param('si', $note_append, $invoice_id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            $writeLog("ERROR: DB update that bai - " . $this->paymentModel->db->error);
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'DB update failed']);
            exit;
        }

        $writeLog("Invoice #$invoice_id cap nhat Paid thanh cong!");

        // Đăng ký gói tập tự động
        $this->paymentModel->registerPackagesForInvoice($invoice_id);
        $writeLog("Da dang ky goi tap cho invoice #$invoice_id");

        echo json_encode([
            'success' => true,
            'message' => "Invoice #$invoice_id marked as Paid successfully"
        ]);
        $writeLog('=== Done ===');
    }
}
