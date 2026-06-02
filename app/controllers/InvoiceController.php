<?php

class InvoiceController extends Controller
{
    private Invoice $invoiceModel;

    public function __construct()
    {
        $this->invoiceModel = new Invoice();
    }

    public function api(): void
    {
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        ensureSession(); // helper from session_helper.php

        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'get_stats':
                echo json_encode(['success' => true, ...$this->invoiceModel->getStats()]);
                break;

            case 'get_invoices':
                $page   = max(1, intval($_GET['page'] ?? 1));
                $limit  = intval($_GET['limit'] ?? 15);
                $search = trim($_GET['search'] ?? '');
                $sort   = $_GET['sort'] ?? 'id_desc';
                $month  = trim($_GET['month'] ?? '');
                $status = trim($_GET['status'] ?? '');

                $result = $this->invoiceModel->getInvoicesList($page, $limit, $search, $sort, $month, $status);
                echo json_encode(['success' => true, 'page' => $page, 'limit' => $limit, ...$result]);
                break;

            case 'get_detail':
                $id = intval($_GET['id'] ?? 0);
                if ($id === 0) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }
                $detail = $this->invoiceModel->getDetail($id);
                if (!$detail) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn']); exit; }
                echo json_encode(['success' => true, ...$detail]);
                break;

            case 'get_customers':
                $q = trim($_GET['q'] ?? '');
                echo json_encode(['success' => true, 'data' => $this->invoiceModel->getCustomersSearch($q)]);
                break;

            case 'get_packages':
                $filter_type = intval($_GET['package_type_id'] ?? 0) ?: null;
                echo json_encode(['success' => true, 'data' => $this->invoiceModel->getPackagesForDropdown($filter_type)]);
                break;

            case 'get_promotions':
                $total = floatval($_GET['total'] ?? 0);
                echo json_encode(['success' => true, 'data' => $this->invoiceModel->getActivePromotions($total)]);
                break;

            case 'add_invoice':
                $created_by   = $_SESSION['username'] ?? 'unknown';
                $customer_id  = intval($_POST['customer_id'] ?? 0);
                $invoice_date = trim($_POST['invoice_date'] ?? date('Y-m-d'));
                $promotion_id = intval($_POST['promotion_id'] ?? 0) ?: null;
                $note         = trim($_POST['note'] ?? '') ?: null;
                $items        = json_decode($_POST['items'] ?? '[]', true);

                if ($customer_id === 0 || empty($items)) { echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']); exit; }

                $original_amount = array_sum(array_map(fn($i) => floatval($i['unit_price']) * intval($i['quantity']), $items));

                // Upgrade credit
                $upgrade_credit = 0; $new_pkg_type_id = 0; $new_type_order = 0;
                foreach ($items as $item) {
                    $pid   = intval($item['plan_id']);
                    $pkRes = $this->invoiceModel->db->query("SELECT mp.package_type_id, pt.sort_order FROM MembershipPlan mp LEFT JOIN PackageType pt ON pt.type_id=mp.package_type_id WHERE mp.plan_id=$pid");
                    $pkRow = $pkRes ? $pkRes->fetch_assoc() : null;
                    if ($pkRow && intval($pkRow['sort_order']) > $new_type_order) { $new_pkg_type_id = (int)$pkRow['package_type_id']; $new_type_order = (int)$pkRow['sort_order']; }
                }
                if ($new_pkg_type_id > 0) $upgrade_credit = $this->invoiceModel->calcUpgradeCredit($customer_id, $new_pkg_type_id);

                $discount_amount = $upgrade_credit;
                $final_amount    = max(0, $original_amount - $upgrade_credit);

                if ($promotion_id) {
                    $promos = $this->invoiceModel->getActivePromotions($original_amount);
                    $promo  = array_filter($promos, fn($p) => $p['promotion_id'] == $promotion_id);
                    $promo  = reset($promo);
                    if (!$promo) { echo json_encode(['success' => false, 'message' => 'Khuyến mãi không còn hợp lệ hoặc đã hết lượt']); exit; }
                    if ($original_amount < floatval($promo['min_order_value'])) { echo json_encode(['success' => false, 'message' => 'Giá trị đơn hàng chưa đủ điều kiện áp dụng khuyến mãi (tối thiểu ' . number_format($promo['min_order_value'],0,',','.') . '₫)']); exit; }
                    $promo_discount = $original_amount * floatval($promo['discount_percent']) / 100;
                    if ($promo['max_discount_amount'] !== null && $promo_discount > floatval($promo['max_discount_amount'])) $promo_discount = floatval($promo['max_discount_amount']);
                    $discount_amount += $promo_discount;
                    $final_amount     = max(0, $original_amount - $discount_amount);
                }

                $invoice_id = $this->invoiceModel->addInvoice($customer_id, $invoice_date, $promotion_id, $original_amount, $discount_amount, $final_amount, $note, $created_by, $items);
                if ($invoice_id) echo json_encode(['success' => true, 'message' => 'Tạo hóa đơn thành công', 'id' => $invoice_id, 'original_amount' => $original_amount, 'discount_amount' => $discount_amount, 'final_amount' => $final_amount, 'upgrade_credit' => $upgrade_credit, 'is_upgrade' => $upgrade_credit > 0]);
                else echo json_encode(['success' => false, 'message' => 'Lỗi tạo hóa đơn']);
                break;

            case 'delete_invoice':
                if (($_SESSION['role'] ?? '') !== 'Admin') { echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa hóa đơn']); exit; }
                $id = intval($_POST['id'] ?? 0);
                if ($id === 0) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }
                echo json_encode($this->invoiceModel->deleteInvoice($id));
                break;

            case 'update_status':
                $id     = intval($_POST['id'] ?? 0);
                $status = trim($_POST['status'] ?? '');
                if ($id === 0 || !in_array($status, ['Paid', 'Pending', 'Cancelled'])) { echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']); exit; }
                $old_status = $this->invoiceModel->getStatus($id);
                $ok = $this->invoiceModel->updateStatus($id, $status);
                if ($ok) {
                    if ($status === 'Paid' && $old_status !== 'Paid') $this->invoiceModel->registerPackagesForInvoice($id);
                    echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
                } else echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật']);
                break;

            case 'update_invoice':
                if (($_SESSION['role'] ?? '') !== 'Admin') { echo json_encode(['success' => false, 'message' => 'Chỉ Admin mới được sửa hóa đơn']); exit; }
                $id           = intval($_POST['id'] ?? 0);
                $invoice_date = trim($_POST['invoice_date'] ?? '');
                $note         = trim($_POST['note'] ?? '') ?: null;
                $status       = trim($_POST['status'] ?? '');
                if ($id === 0) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }
                $old_status = $this->invoiceModel->getStatus($id);
                $ok = $this->invoiceModel->updateInvoice($id, $invoice_date, $note, $status);
                if ($ok) {
                    if ($status === 'Paid' && $old_status !== 'Paid') $this->invoiceModel->registerPackagesForInvoice($id);
                    echo json_encode(['success' => true, 'message' => 'Cập nhật hóa đơn thành công']);
                } else echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật']);
                break;

            case 'get_payment_info':
                $id = intval($_GET['id'] ?? 0);
                if ($id === 0) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }
                $inv = $this->invoiceModel->getPaymentInfo($id);
                if (!$inv) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy hóa đơn']); exit; }
                $bank  = ['bank_id' => 'MB', 'account_no' => '0981015808', 'account_name' => 'PHAM VAN HOANG'];
                $amount = intval(floatval($inv['final_amount']));
                $info   = urlencode("ELITEGYM HD{$id} " . preg_replace('/[^a-zA-Z0-9 ]/', '', $inv['ten_khach']));
                $qr_url = "https://img.vietqr.io/image/{$bank['bank_id']}-{$bank['account_no']}-compact2.png?amount={$amount}&addInfo={$info}&accountName=" . urlencode($bank['account_name']);
                echo json_encode(['success' => true, 'invoice' => $inv, 'qr_url' => $qr_url, 'amount' => $amount, 'bank' => $bank, 'description' => "ELITEGYM HD{$id} {$inv['ten_khach']}"]);
                break;

            case 'confirm_payment':
                $id     = intval($_POST['id'] ?? 0);
                $method = trim($_POST['phuong_thuc'] ?? 'Chuyển khoản');
                if ($id === 0) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }
                $ok = $this->invoiceModel->confirmPayment($id, $method);
                if ($ok) { $this->invoiceModel->registerPackagesForInvoice($id); echo json_encode(['success' => true, 'message' => 'Xác nhận thanh toán thành công!']); }
                else echo json_encode(['success' => false, 'message' => 'Lỗi xác nhận']);
                break;

            case 'check_payment_status':
                $id = intval($_GET['id'] ?? 0);
                echo json_encode(['status' => $id > 0 ? ($this->invoiceModel->getStatus($id) ?? 'Pending') : 'Pending']);
                break;

            case 'get_upgrade_credit':
                $customer_id     = intval($_GET['customer_id']     ?? 0);
                $new_pkg_type_id = intval($_GET['package_type_id'] ?? 0);
                if ($customer_id === 0 || $new_pkg_type_id === 0) { echo json_encode(['success' => true, 'credit' => 0]); exit; }
                $info = $this->invoiceModel->getUpgradeCreditInfo($customer_id, $new_pkg_type_id);
                echo json_encode(['success' => true, ...$info]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
        }
    }
}
