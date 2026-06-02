<?php

class PromotionController extends Controller
{
    private Promotion $promotionModel;

    public function __construct()
    {
        $this->promotionModel = new Promotion();
    }

    public function api(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        ensureSession();

        // Auto-expire promotions past end_date
        $this->promotionModel->autoExpire();

        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'get_stats':
                echo json_encode(['success' => true, ...$this->promotionModel->getStats()]);
                break;

            case 'get_promos':
                $page   = max(1, intval($_GET['page'] ?? 1));
                $limit  = intval($_GET['limit'] ?? 15);
                $search = trim($_GET['search'] ?? '');
                $status = trim($_GET['status'] ?? '');
                $sort   = $_GET['sort'] ?? 'id_desc';

                $result = $this->promotionModel->getPromosList($page, $limit, $search, $status, $sort);
                echo json_encode(['success' => true, 'page' => $page, 'limit' => $limit, ...$result]);
                break;

            case 'get_detail':
                $id = intval($_GET['id'] ?? 0);
                if ($id === 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
                $promo = $this->promotionModel->getDetail($id);
                if (!$promo) { echo json_encode(['success' => false, 'message' => 'Promotion not found']); exit; }
                echo json_encode(['success' => true, 'promo' => $promo]);
                break;

            case 'add_promo':
                $data = $this->extractPromoData($_POST);
                if (!$data['valid']) { echo json_encode(['success' => false, 'message' => $data['error']]); exit; }

                $id = $this->promotionModel->addPromo($data);
                if ($id) echo json_encode(['success' => true, 'message' => 'Thêm khuyến mãi thành công', 'id' => $id]);
                else echo json_encode(['success' => false, 'message' => 'Lỗi database']);
                break;

            case 'update_promo':
                $id   = intval($_POST['id'] ?? 0);
                $data = $this->extractPromoData($_POST);
                if ($id === 0 || !$data['valid']) { echo json_encode(['success' => false, 'message' => $data['error'] ?? 'Dữ liệu không hợp lệ']); exit; }

                $ok = $this->promotionModel->updatePromo($id, $data);
                echo json_encode($ok ? ['success' => true, 'message' => 'Cập nhật khuyến mãi thành công'] : ['success' => false, 'message' => 'Lỗi database']);
                break;

            case 'delete_promo':
                $id = intval($_POST['id'] ?? 0);
                if ($id === 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
                echo json_encode($this->promotionModel->deletePromo($id));
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }

    private function extractPromoData(array $post): array
    {
        $name         = trim($post['promotion_name']   ?? '');
        $discount     = floatval($post['discount_percent'] ?? 0);
        $status       = trim($post['status']           ?? 'Active');
        $min_order    = floatval($post['min_order_value'] ?? 0);
        $max_disc_raw = trim($post['max_discount_amount'] ?? '');
        $max_usage_raw = trim($post['max_usage']        ?? '');
        $start_date   = trim($post['start_date']        ?? '');
        $end_date     = trim($post['end_date']          ?? '');
        $description  = trim($post['description']       ?? '') ?: null;

        if ($name === '' || $discount <= 0 || !$start_date || !$end_date)
            return ['valid' => false, 'error' => 'Invalid data'];
        if ($discount > 100)
            return ['valid' => false, 'error' => 'Discount cannot exceed 100%'];

        return [
            'valid'               => true,
            'promotion_name'      => $name,
            'discount_percent'    => $discount,
            'status'              => $status,
            'min_order_value'     => $min_order,
            'max_discount_amount' => $max_disc_raw !== '' ? floatval($max_disc_raw) : null,
            'max_usage'           => $max_usage_raw !== '' ? intval($max_usage_raw) : null,
            'start_date'          => $start_date,
            'end_date'            => $end_date,
            'description'         => $description,
        ];
    }
}
