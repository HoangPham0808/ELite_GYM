<?php
require_once '../../../Database/db.php';
header('Content-Type: application/json; charset=utf-8');

// ── Cấu hình upload ảnh ───────────────────────────────────────────
define('PKG_UPLOAD_DIR', 'C:/wamp64/www/PHP/ELite_GYM/upload/image_package/');
define('PKG_UPLOAD_URL', '/PHP/ELite_GYM/upload/image_package/');
define('PKG_MAX_SIZE',   5 * 1024 * 1024); // 5 MB
$PKG_ALLOWED_MIME = ['image/jpeg','image/png','image/webp','image/gif'];
$PKG_ALLOWED_EXT  = ['jpg','jpeg','png','webp','gif'];

if (!is_dir(PKG_UPLOAD_DIR)) mkdir(PKG_UPLOAD_DIR, 0755, true);

// ── Helper upload ảnh ─────────────────────────────────────────────
function handlePackageImage(array $file, array $allowedMime, array $allowedExt): array {
    if ($file['error'] !== UPLOAD_ERR_OK)
        return ['ok'=>false,'msg'=>'Lỗi upload file.'];
    if ($file['size'] > PKG_MAX_SIZE)
        return ['ok'=>false,'msg'=>'Ảnh vượt quá 5 MB.'];

    $mime = mime_content_type($file['tmp_name']);
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($mime, $allowedMime) || !in_array($ext, $allowedExt))
        return ['ok'=>false,'msg'=>'Định dạng ảnh không hợp lệ (JPG, PNG, WEBP, GIF).'];

    $base     = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $filename = 'pkg_' . $base . '_' . uniqid() . '.' . $ext;
    $dest     = PKG_UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest))
        return ['ok'=>false,'msg'=>'Không thể lưu file.'];

    return ['ok'=>true,'filename'=>$filename,'url'=> PKG_UPLOAD_URL . $filename];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ========================
    // LẤY DANH SÁCH GÓI TẬP
    // ========================
    case 'get_packages':
        $page     = max(1, intval($_GET['page'] ?? 1));
        $limit    = intval($_GET['limit'] ?? 15);
        $search   = trim($_GET['search'] ?? '');
        $duration = intval($_GET['duration'] ?? 0);
        $sort     = $_GET['sort'] ?? 'id_desc';
        $offset   = ($page - 1) * $limit;

        // Build WHERE
        $where  = ['1=1'];
        $params = [];
        $types  = '';

        if ($search !== '') {
            $where[] = "(mp.plan_name LIKE ? OR mp.description LIKE ?)";
            $s = "%$search%";
            $params[] = $s; $params[] = $s;
            $types .= 'ss';
        }

        if ($duration > 0) {
            $where[] = "mp.duration_months = ?";
            $params[] = $duration;
            $types .= 'i';
        }

        $whereStr = implode(' AND ', $where);

        // Sort
        $orderBy = match($sort) {
            'price_asc'         => 'mp.price ASC',
            'price_desc'        => 'mp.price DESC',
            'duration_asc'      => 'mp.duration_months ASC',
            'subscribers_desc'  => 'total_subscribers DESC',
            default             => 'mp.plan_id DESC'
        };

        $sql = "
            SELECT
                mp.plan_id,
                mp.plan_name,
                mp.duration_months,
                mp.price,
                mp.description,
                mp.image_url,
                COUNT(DISTINCT mr.registration_id) AS total_subscribers,
                COUNT(DISTINCT CASE WHEN mr.end_date >= CURDATE() THEN mr.registration_id END) AS active_subscribers
            FROM MembershipPlan mp
            LEFT JOIN MembershipRegistration mr ON mr.plan_id = mp.plan_id
            WHERE $whereStr
            GROUP BY mp.plan_id, mp.plan_name, mp.duration_months, mp.price, mp.description, mp.image_url
            ORDER BY $orderBy
        ";

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM ($sql) as sub";
        $stmt = $conn->prepare($countSql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];

        // Paginated data
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types   .= 'ii';

        $stmt = $conn->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result   = $stmt->get_result();
        $packages = [];
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }

        echo json_encode([
            'success'    => true,
            'data'       => $packages,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => max(1, ceil($total / $limit))
        ]);
        break;

    // ========================
    // THÊM GÓI TẬP
    // ========================
    case 'add_package':
        $plan_name   = trim($_POST['plan_name'] ?? '');
        $duration    = intval($_POST['duration_months'] ?? 0);
        $price       = floatval($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if ($plan_name === '') {
            echo json_encode(['success' => false, 'message' => 'Plan name is required']); exit;
        }
        if ($duration < 1) {
            echo json_encode(['success' => false, 'message' => 'Duration must be greater than 0']); exit;
        }
        if ($price < 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid price']); exit;
        }

        // Kiểm tra trùng tên
        $check = $conn->prepare("SELECT plan_id FROM MembershipPlan WHERE plan_name = ?");
        $check->bind_param('s', $plan_name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Plan name already exists']); exit;
        }

        // Xử lý upload ảnh
        $image_url = null;
        if (!empty($_FILES['image_package']['name'])) {
            global $PKG_ALLOWED_MIME, $PKG_ALLOWED_EXT;
            $up = handlePackageImage($_FILES['image_package'], $PKG_ALLOWED_MIME, $PKG_ALLOWED_EXT);
            if (!$up['ok']) {
                echo json_encode(['success' => false, 'message' => $up['msg']]); exit;
            }
            $image_url = $up['url'];
        }

        $desc_val = $description ?: null;
        $stmt = $conn->prepare("
            INSERT INTO MembershipPlan (plan_name, duration_months, price, description, image_url)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sidss', $plan_name, $duration, $price, $desc_val, $image_url);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Thêm gói tập thành công', 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
        }
        break;

    // ========================
    // CẬP NHẬT GÓI TẬP
    // ========================
    case 'update_package':
        $id          = intval($_POST['id'] ?? 0);
        $plan_name   = trim($_POST['plan_name'] ?? '');
        $duration    = intval($_POST['duration_months'] ?? 0);
        $price       = floatval($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if ($id === 0 || $plan_name === '') {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']); exit;
        }
        if ($duration < 1) {
            echo json_encode(['success' => false, 'message' => 'Duration must be greater than 0']); exit;
        }

        // Kiểm tra trùng tên (trừ chính nó)
        $check = $conn->prepare("SELECT plan_id FROM MembershipPlan WHERE plan_name = ? AND plan_id != ?");
        $check->bind_param('si', $plan_name, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Plan name already exists']); exit;
        }

        // Lấy ảnh cũ để xóa nếu có ảnh mới
        $old = $conn->query("SELECT image_url FROM MembershipPlan WHERE plan_id = $id")->fetch_assoc();
        $image_url = $old['image_url'] ?? null; // Giữ ảnh cũ mặc định

        if (!empty($_FILES['image_package']['name'])) {
            global $PKG_ALLOWED_MIME, $PKG_ALLOWED_EXT;
            $up = handlePackageImage($_FILES['image_package'], $PKG_ALLOWED_MIME, $PKG_ALLOWED_EXT);
            if (!$up['ok']) {
                echo json_encode(['success' => false, 'message' => $up['msg']]); exit;
            }
            // Xóa ảnh cũ khỏi disk
            if ($old['image_url']) {
                $oldFile = PKG_UPLOAD_DIR . basename($old['image_url']);
                if (file_exists($oldFile)) @unlink($oldFile);
            }
            $image_url = $up['url'];
        }

        // Nếu admin chọn "Xóa ảnh"
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            if ($old['image_url']) {
                $oldFile = PKG_UPLOAD_DIR . basename($old['image_url']);
                if (file_exists($oldFile)) @unlink($oldFile);
            }
            $image_url = null;
        }

        $desc_val = $description ?: null;
        $stmt = $conn->prepare("
            UPDATE MembershipPlan
            SET plan_name=?, duration_months=?, price=?, description=?, image_url=?
            WHERE plan_id=?
        ");
        $stmt->bind_param('sidssi', $plan_name, $duration, $price, $desc_val, $image_url, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Cập nhật gói tập thành công']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
        }
        break;

    // ========================
    // XÓA GÓI TẬP
    // ========================
    case 'delete_package':
        $id = intval($_POST['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit;
        }

        $checks = [
            ["SELECT COUNT(*) as c FROM MembershipRegistration WHERE plan_id = $id", 'membership registrations'],
            ["SELECT COUNT(*) as c FROM InvoiceDetail WHERE plan_id = $id",           'invoices'],
        ];
        foreach ($checks as [$chk, $label]) {
            $r = $conn->query($chk)->fetch_assoc();
            if ($r['c'] > 0) {
                echo json_encode(['success'=>false,'message'=>"Cannot delete: plan has related data ($label)"]); exit;
            }
        }

        // Lấy ảnh để xóa file
        $old = $conn->query("SELECT image_url FROM MembershipPlan WHERE plan_id = $id")->fetch_assoc();

        $stmt = $conn->prepare("DELETE FROM MembershipPlan WHERE plan_id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            // Xóa file ảnh nếu có
            if (!empty($old['image_url'])) {
                $oldFile = PKG_UPLOAD_DIR . basename($old['image_url']);
                if (file_exists($oldFile)) @unlink($oldFile);
            }
            echo json_encode(['success' => true, 'message' => 'Đã xóa gói tập']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $conn->error]);
        }
        break;

    // ========================
    // XEM CHI TIẾT GÓI TẬP
    // ========================
    case 'get_detail':
        $id = intval($_GET['id'] ?? 0);
        if ($id === 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }

        $stmt = $conn->prepare("SELECT plan_id, plan_name, duration_months, price, description, image_url FROM MembershipPlan WHERE plan_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $package = $stmt->get_result()->fetch_assoc();

        if (!$package) {
            echo json_encode(['success' => false, 'message' => 'Membership plan not found']);
            exit;
        }

        // Thống kê
        $stmt = $conn->prepare("
            SELECT
                COUNT(*) as total_subscribers,
                COUNT(CASE WHEN end_date >= CURDATE() THEN 1 END) as active_subscribers,
                MAX(start_date) as last_registered
            FROM MembershipRegistration
            WHERE plan_id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();

        // Doanh thu từ invoices
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(id_tbl.unit_price * id_tbl.quantity), 0) as total_revenue
            FROM InvoiceDetail id_tbl
            WHERE id_tbl.plan_id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $revenue = $stmt->get_result()->fetch_assoc();

        // Danh sách khách hàng đăng ký (tối đa 20)
        $stmt = $conn->prepare("
            SELECT
                c.full_name,
                c.phone,
                mr.start_date,
                mr.end_date
            FROM MembershipRegistration mr
            JOIN Customer c ON c.customer_id = mr.customer_id
            WHERE mr.plan_id = ?
            ORDER BY mr.start_date DESC
            LIMIT 20
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $subscribers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'success'            => true,
            'package'            => $package,
            'total_subscribers'  => $stats['total_subscribers'],
            'active_subscribers' => $stats['active_subscribers'],
            'last_registered'    => $stats['last_registered'],
            'total_revenue'      => $revenue['total_revenue'],
            'subscribers'        => $subscribers
        ]);
        break;

    // ========================
    // THỐNG KÊ STATS STRIP
    // ========================
    case 'get_stats':
        // Tổng gói tập
        $total = $conn->query("SELECT COUNT(*) as c FROM MembershipPlan")->fetch_assoc()['c'];

        // Tổng khách đang sử dụng (gói còn hạn)
        $active_subscribers = $conn->query("
            SELECT COUNT(DISTINCT customer_id) as c
            FROM MembershipRegistration
            WHERE end_date >= CURDATE()
        ")->fetch_assoc()['c'];

        // Gói phổ biến nhất (nhiều đăng ký nhất)
        $popularRow = $conn->query("
            SELECT mp.plan_name, COUNT(mr.registration_id) as cnt
            FROM MembershipPlan mp
            LEFT JOIN MembershipRegistration mr ON mr.plan_id = mp.plan_id
            GROUP BY mp.plan_id
            ORDER BY cnt DESC
            LIMIT 1
        ")->fetch_assoc();

        $popular_name = $popularRow ? $popularRow['plan_name'] : null;

        // Giá trung bình
        $avg_price = $conn->query("SELECT AVG(price) as avg FROM MembershipPlan")->fetch_assoc()['avg'];

        echo json_encode([
            'success'            => true,
            'total'              => $total,
            'active_subscribers' => $active_subscribers,
            'popular_name'       => $popular_name,
            'avg_price'          => $avg_price ? round($avg_price) : 0
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
