<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once '../../../Database/db.php';

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Không thể kết nối cơ sở dữ liệu']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ========================
    // LOẠI GÓI TẬP
    // ========================
    case 'get_package_types':
        $res   = $conn->query("SELECT type_id, type_name, color_code FROM PackageType WHERE is_active=1 ORDER BY sort_order ASC, type_id ASC");
        $types = [];
        if ($res) while ($r = $res->fetch_assoc()) $types[] = $r;
        echo json_encode(['success' => true, 'data' => $types]);
        break;

    // ========================
    // STATS
    // ========================
    case 'get_stats':
        $total       = $conn->query("SELECT COUNT(*) c FROM GymRoom")->fetch_assoc()['c'];
        $active      = $conn->query("SELECT COUNT(*) c FROM GymRoom WHERE status='Hoạt động'")->fetch_assoc()['c'];
        $maintenance = $conn->query("SELECT COUNT(*) c FROM GymRoom WHERE status='Bảo trì'")->fetch_assoc()['c'];
        $capacity    = $conn->query("SELECT COALESCE(SUM(capacity),0) c FROM GymRoom")->fetch_assoc()['c'];
        // DB table: Equipment  columns: equipment_id, equipment_name, condition_status, room_id
        $thiet_bi    = $conn->query("SELECT COUNT(*) c FROM Equipment WHERE condition_status != 'Hỏng' OR condition_status IS NULL")->fetch_assoc()['c'];

        echo json_encode([
            'success'     => true,
            'total'       => (int)$total,
            'active'      => (int)$active,
            'maintenance' => (int)$maintenance,
            'capacity'    => (int)$capacity,
            'thiet_bi'    => (int)$thiet_bi,
        ]);
        break;

    // ========================
    // DANH SÁCH PHÒNG TẬP
    // ========================
    case 'get_gyms':
        $page   = max(1, (int)($_GET['page']  ?? 1));
        $limit  = (int)($_GET['limit']         ?? 12);
        $search = trim($_GET['search']          ?? '');
        $status = trim($_GET['status']          ?? '');
$sort   = $_GET['sort']                 ?? 'id_desc';
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];
        $types  = '';

        if ($search !== '') {
            $where[] = "(room_name LIKE ? OR description LIKE ?)";
            $s = "%$search%";
            $params[] = $s; $params[] = $s;
            $types .= 'ss';
        }
        if ($status !== '') { $where[] = "status = ?";    $params[] = $status; $types .= 's'; }


        $whereStr = implode(' AND ', $where);

        $orderBy = match($sort) {
            'id_asc'   => 'room_id ASC',
            'name_asc' => 'room_name ASC',
            'cap_desc' => 'capacity DESC',
            default    => 'room_id DESC'
        };

        // Count
        $cs = $conn->prepare("SELECT COUNT(*) total FROM GymRoom WHERE $whereStr");
        if ($params) $cs->bind_param($types, ...$params);
        $cs->execute();
        $total = $cs->get_result()->fetch_assoc()['total'];

        // Data + equipment count
        $ds = $conn->prepare("
            SELECT gr.*,
                   gr.package_type_id,
                   pt.type_name  AS package_type_name,
                   pt.color_code AS package_type_color,
                   (SELECT COUNT(*) FROM Equipment e WHERE e.room_id = gr.room_id) AS so_thiet_bi
            FROM GymRoom gr
            LEFT JOIN PackageType pt ON pt.type_id = gr.package_type_id
            WHERE $whereStr
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ");
        $dp = $params; $dt = $types;
        $dp[] = $limit; $dp[] = $offset; $dt .= 'ii';
        if ($dp) $ds->bind_param($dt, ...$dp);
        $ds->execute();
        $rows = $ds->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'success'    => true,
            'data'       => $rows,
            'total'      => (int)$total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => max(1, ceil($total / $limit))
        ]);
        break;

    // ========================
    // CHI TIẾT PHÒNG TẬP
    // ========================
    case 'get_detail':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }

        $s = $conn->prepare("
            SELECT gr.*,
                   gr.package_type_id,
                   pt.type_name  AS package_type_name,
                   pt.color_code AS package_type_color
            FROM GymRoom gr
            LEFT JOIN PackageType pt ON pt.type_id = gr.package_type_id
            WHERE gr.room_id = ?
        ");
        $s->bind_param('i', $id); $s->execute();
        $room = $s->get_result()->fetch_assoc();
        if (!$room) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy phòng tập']); exit; }

        $s2 = $conn->prepare("SELECT * FROM Equipment WHERE room_id = ? ORDER BY equipment_name ASC");
        $s2->bind_param('i', $id); $s2->execute();
        $thiet_bi = $s2->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success' => true, 'room' => $room, 'thiet_bi' => $thiet_bi]);
        break;

    // ========================
    // THÊM PHÒNG TẬP
    // ========================
    case 'add_gym':
        $room_name   = trim($_POST['ten_phong']  ?? '');
        $status      = trim($_POST['trang_thai'] ?? 'Hoạt động');
        $capacity    = (int)($_POST['suc_chua']  ?? 0) ?: null;
        $area        = (float)($_POST['dien_tich'] ?? 0) ?: null;
        $floor_raw   = trim($_POST['tang']        ?? '');
        $floor       = $floor_raw !== '' ? (int)$floor_raw : null;
        $open_time   = trim($_POST['gio_mo']      ?? '') ?: null;
        $description     = trim($_POST['mo_ta']         ?? '') ?: null;
        $package_type_id = intval($_POST['package_type_id'] ?? 0) ?: null;

        if (!$room_name) {
            echo json_encode(['success' => false, 'message' => 'Tên phòng không được để trống']);
            exit;
        }

        $chk = $conn->prepare("SELECT room_id FROM GymRoom WHERE room_name = ?");
        $chk->bind_param('s', $room_name); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Tên phòng tập đã tồn tại']); exit;
        }

        $s = $conn->prepare("
            INSERT INTO GymRoom (room_name, status, capacity, area, floor, open_time, description, package_type_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $s->bind_param('ssidissi', $room_name, $status, $capacity, $area, $floor, $open_time, $description, $package_type_id);
        echo json_encode($s->execute()
            ? ['success' => true, 'message' => 'Thêm phòng tập thành công', 'id' => $conn->insert_id]
            : ['success' => false, 'message' => $conn->error]);
        break;

    // ========================
    // CẬP NHẬT PHÒNG TẬP
    // ========================
    case 'update_gym':
        $id          = (int)($_POST['id']          ?? 0);
        $room_name   = trim($_POST['ten_phong']    ?? '');
        $status      = trim($_POST['trang_thai']   ?? 'Hoạt động');
        $capacity    = (int)($_POST['suc_chua']    ?? 0) ?: null;
        $area        = (float)($_POST['dien_tich'] ?? 0) ?: null;
        $floor_raw   = trim($_POST['tang']         ?? '');
        $floor       = $floor_raw !== '' ? (int)$floor_raw : null;
        $open_time   = trim($_POST['gio_mo']       ?? '') ?: null;
        $description     = trim($_POST['mo_ta']         ?? '') ?: null;
        $package_type_id = intval($_POST['package_type_id'] ?? 0) ?: null;

        if (!$id || !$room_name) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']); exit;
        }

        $chk = $conn->prepare("SELECT room_id FROM GymRoom WHERE room_name = ? AND room_id != ?");
        $chk->bind_param('si', $room_name, $id); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Tên phòng tập đã tồn tại']); exit;
        }

        $s = $conn->prepare("
            UPDATE GymRoom
            SET room_name=?, status=?, capacity=?, area=?, floor=?, open_time=?, description=?, package_type_id=?
            WHERE room_id=?
        ");
        $s->bind_param('ssidissii', $room_name, $status, $capacity, $area, $floor, $open_time, $description, $package_type_id, $id);
        echo json_encode($s->execute()
            ? ['success' => true, 'message' => 'Cập nhật phòng tập thành công']
            : ['success' => false, 'message' => $conn->error]);
        break;

    // ========================
    // XÓA PHÒNG TẬP
    // ========================
    case 'delete_gym':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }

        $chk = $conn->prepare("SELECT COUNT(*) c FROM Equipment WHERE room_id = ?");
        $chk->bind_param('i', $id); $chk->execute();
        if ($chk->get_result()->fetch_assoc()['c'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Không thể xóa: phòng tập còn thiết bị liên kết']); exit;
        }

        $s = $conn->prepare("DELETE FROM GymRoom WHERE room_id = ?");
        $s->bind_param('i', $id);
        echo json_encode($s->execute()
            ? ['success' => true, 'message' => 'Đã xóa phòng tập']
            : ['success' => false, 'message' => $conn->error]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
}
?>
