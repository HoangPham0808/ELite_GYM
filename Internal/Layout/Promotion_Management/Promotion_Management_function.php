<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once '../../../Database/db.php';

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Cannot connect to database']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ========================
// AUTO-EXPIRE promotions past end_date
// Runs on every request
// ========================
$conn->query("
    UPDATE Promotion
    SET status = 'Expired'
    WHERE status = 'Active'
      AND end_date < CURDATE()
      AND end_date NOT IN ('0000-00-00')
      AND end_date IS NOT NULL
");

// Helper: trả về 0 nếu query lỗi
function safe_count($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) return 0;
    $row = $r->fetch_assoc();
    return intval($row['c'] ?? 0);
}

switch ($action) {

    // ========================
    // THỐNG KÊ
    // ========================
    case 'get_stats':
        $today = date('Y-m-d');

        $total       = safe_count($conn, "SELECT COUNT(*) as c FROM Promotion");
        $active      = safe_count($conn, "SELECT COUNT(*) as c FROM Promotion WHERE status = 'Active' AND end_date >= '$today'");
        $expired     = safe_count($conn, "SELECT COUNT(*) as c FROM Promotion WHERE status = 'Expired' OR end_date < '$today'");
        $total_used  = safe_count($conn, "SELECT COALESCE(SUM(usage_count), 0) as c FROM Promotion");

        $avg_res = $conn->query("SELECT ROUND(AVG(discount_percent), 1) as avg_d FROM Promotion");
        $avg_discount = $avg_res ? ($avg_res->fetch_assoc()['avg_d'] ?? 0) : 0;

        echo json_encode([
            'success'      => true,
            'total'        => $total,
            'active'       => $active,
            'expired'      => $expired,
            'total_used'   => $total_used,
            'avg_discount' => $avg_discount,
        ]);
        break;

    // ========================
    // DANH SÁCH
    // ========================
    case 'get_promos':
        $page   = max(1, intval($_GET['page'] ?? 1));
        $limit  = intval($_GET['limit'] ?? 15);
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $sort   = $_GET['sort'] ?? 'id_desc';
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];
        $types  = '';

        if ($search !== '') {
            $where[] = "(promotion_name LIKE ? OR description LIKE ?)";
            $s = "%$search%";
            $params[] = $s; $params[] = $s;
            $types .= 'ss';
        }

        if ($status !== '') {
            $where[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }

        $whereStr = implode(' AND ', $where);

        $orderBy = match($sort) {
            'id_asc'        => 'promotion_id ASC',
            'discount_desc' => 'discount_percent DESC',
            'end_asc'       => 'end_date ASC',
            default         => 'promotion_id DESC'
        };

        // Count
        $countSql = "SELECT COUNT(*) as total FROM Promotion WHERE $whereStr";
        $stmt = $conn->prepare($countSql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];

        // Data
        $dataSql = "SELECT * FROM Promotion WHERE $whereStr ORDER BY $orderBy LIMIT ? OFFSET ?";
        $dp = $params;
        $dt = $types;
        $dp[] = $limit; $dp[] = $offset;
        $dt .= 'ii';

        $stmt = $conn->prepare($dataSql);
        if (!empty($dp)) $stmt->bind_param($dt, ...$dp);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;

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
    // CHI TIẾT
    // ========================
    case 'get_detail':
        $id = intval($_GET['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        $stmt = $conn->prepare("SELECT * FROM Promotion WHERE promotion_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $promo = $stmt->get_result()->fetch_assoc();

        if (!$promo) {
            echo json_encode(['success' => false, 'message' => 'Promotion not found']);
            exit;
        }
        echo json_encode(['success' => true, 'promo' => $promo]);
        break;

    // ========================
    // THÊM
    // ========================
    case 'add_promo':
        $promotion_name      = trim($_POST['promotion_name']      ?? '');
        $discount_percent    = floatval($_POST['discount_percent'] ?? 0);
        $status              = trim($_POST['status']              ?? 'Active');
        $min_order_value     = floatval($_POST['min_order_value'] ?? 0);
        $max_discount_raw    = trim($_POST['max_discount_amount'] ?? '');
        $max_discount_amount = ($max_discount_raw !== '') ? floatval($max_discount_raw) : null;
        $max_usage_raw       = trim($_POST['max_usage']           ?? '');
        $max_usage           = ($max_usage_raw !== '') ? intval($max_usage_raw) : null;
        $start_date          = trim($_POST['start_date']          ?? '');
        $end_date            = trim($_POST['end_date']            ?? '');
        $description         = trim($_POST['description']        ?? '') ?: null;

        if ($promotion_name === '' || $discount_percent <= 0 || !$start_date || !$end_date) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        if ($discount_percent > 100) {
            echo json_encode(['success' => false, 'message' => 'Discount cannot exceed 100%']);
            exit;
        }

        // Tự động cập nhật trạng thái theo ngày (đã xử lý tự động ở đầu file)

        $pn   = $conn->real_escape_string($promotion_name);
        $dp   = floatval($discount_percent);
        $st   = $conn->real_escape_string($status);
        $mov  = floatval($min_order_value);
        $mda  = ($max_discount_amount !== null) ? floatval($max_discount_amount) : 'NULL';
        $mu   = ($max_usage !== null) ? intval($max_usage) : 'NULL';
        $sd   = $conn->real_escape_string($start_date);
        $ed   = $conn->real_escape_string($end_date);
        $desc = ($description !== null) ? "'" . $conn->real_escape_string($description) . "'" : 'NULL';

        $sql = "INSERT INTO Promotion
                    (promotion_name, discount_percent, status, min_order_value, max_discount_amount, max_usage, start_date, end_date, description)
                VALUES ('$pn', $dp, '$st', $mov, $mda, $mu, '$sd', '$ed', $desc)";

        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Thêm khuyến mãi thành công', 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $conn->error]);
        }
        break;

    // ========================
    // CẬP NHẬT
    // ========================
    case 'update_promo':
        $id                  = intval($_POST['id']                ?? 0);
        $promotion_name      = trim($_POST['promotion_name']      ?? '');
        $discount_percent    = floatval($_POST['discount_percent'] ?? 0);
        $status              = trim($_POST['status']              ?? 'Active');
        $min_order_value     = floatval($_POST['min_order_value'] ?? 0);
        $max_discount_raw    = trim($_POST['max_discount_amount'] ?? '');
        $max_discount_amount = ($max_discount_raw !== '') ? floatval($max_discount_raw) : null;
        $max_usage_raw       = trim($_POST['max_usage']           ?? '');
        $max_usage           = ($max_usage_raw !== '') ? intval($max_usage_raw) : null;
        $start_date          = trim($_POST['start_date']          ?? '');
        $end_date            = trim($_POST['end_date']            ?? '');
        $description         = trim($_POST['description']        ?? '') ?: null;

        if ($id === 0 || $promotion_name === '' || $discount_percent <= 0 || !$start_date || !$end_date) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }
        if ($discount_percent > 100) {
            echo json_encode(['success' => false, 'message' => 'Discount cannot exceed 100%']);
            exit;
        }

        // Tự động cập nhật trạng thái theo ngày (đã xử lý tự động ở đầu file)

        $pn   = $conn->real_escape_string($promotion_name);
        $dp   = floatval($discount_percent);
        $st   = $conn->real_escape_string($status);
        $mov  = floatval($min_order_value);
        $mda  = ($max_discount_amount !== null) ? floatval($max_discount_amount) : 'NULL';
        $mu   = ($max_usage !== null) ? intval($max_usage) : 'NULL';
        $sd   = $conn->real_escape_string($start_date);
        $ed   = $conn->real_escape_string($end_date);
        $desc = ($description !== null) ? "'" . $conn->real_escape_string($description) . "'" : 'NULL';

        $sql = "UPDATE Promotion SET
                    promotion_name     = '$pn',
                    discount_percent   = $dp,
                    status             = '$st',
                    min_order_value    = $mov,
                    max_discount_amount= $mda,
                    max_usage          = $mu,
                    start_date         = '$sd',
                    end_date           = '$ed',
                    description        = $desc
                WHERE promotion_id = $id";

        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Cập nhật khuyến mãi thành công']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $conn->error]);
        }
        break;

    // ========================
    // XÓA
    // ========================
    case 'delete_promo':
        $id = intval($_POST['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        // Kiểm tra ràng buộc với LichSuPromotion (chỉ nếu bảng tồn tại)
        $tableExists = $conn->query("SHOW TABLES LIKE 'LichSuPromotion'")->num_rows > 0;
        if ($tableExists) {
            $chk = $conn->query("SELECT COUNT(*) as c FROM LichSuPromotion WHERE promotion_id = $id");
            if ($chk && $chk->fetch_assoc()['c'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete: promotion has been used in history']);
                exit;
            }
        }

        $stmt = $conn->prepare("DELETE FROM Promotion WHERE promotion_id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Promotion deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
