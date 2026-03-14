<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require_once '../../../Database/db.php';

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Không thể kết nối DB']);
    exit;
}
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

// ── STATS ──────────────────────────────────────────────────────────
case 'get_stats':
    $total    = $conn->query("SELECT COUNT(*) c FROM Equipment")->fetch_assoc()['c'];
    $hoatdong = $conn->query("SELECT COUNT(*) c FROM Equipment WHERE condition_status='Hoạt động'")->fetch_assoc()['c'];
    $hong     = $conn->query("SELECT COUNT(*) c FROM Equipment WHERE condition_status='Hỏng'")->fetch_assoc()['c'];
    $baoduong = $conn->query("SELECT COUNT(*) c FROM Equipment WHERE condition_status='Đang bảo dưỡng'")->fetch_assoc()['c'];
    $can_bao  = $conn->query("
        SELECT COUNT(*) c FROM Equipment e
        LEFT JOIN EquipmentType et ON et.type_id = e.type_id
        WHERE (e.last_maintenance_date IS NOT NULL OR e.purchase_date IS NOT NULL)
          AND DATE_ADD(COALESCE(e.last_maintenance_date, e.purchase_date, CURDATE()),
              INTERVAL COALESCE(et.maintenance_interval, 180) DAY) <= CURDATE()
    ")->fetch_assoc()['c'];
    $tong_gia = $conn->query("SELECT COALESCE(SUM(purchase_price), 0) s FROM Equipment")->fetch_assoc()['s'];
    $tong_bt  = $conn->query("SELECT COALESCE(SUM(cost), 0) s FROM EquipmentMaintenance")->fetch_assoc()['s'];
    echo json_encode([
        'success'     => true,
        'total'       => (int)$total,
        'hoat_dong'   => (int)$hoatdong,
        'hong'        => (int)$hong,
        'bao_duong'   => (int)$baoduong,
        'can_bao_tri' => (int)$can_bao,
        'tong_gia'    => (float)$tong_gia,
        'tong_bt'     => (float)$tong_bt
    ]);
    break;

// ── LOẠI THIẾT BỊ (EquipmentType) ──────────────────────────────────
case 'get_categories':
    // Returns: type_id, type_name, description, maintenance_interval
    $rows = $conn->query("SELECT * FROM EquipmentType ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    break;

case 'add_category':
    $ten = trim($_POST['ten_loai'] ?? '');
    $mo  = trim($_POST['mo_ta'] ?? '') ?: null;
    $han = max(1, (int)($_POST['han_bao_tri_ngay'] ?? 180));
    if (!$ten) { echo json_encode(['success' => false, 'message' => 'Tên loại không được trống']); exit; }
    $s = $conn->prepare("INSERT INTO EquipmentType (type_name, description, maintenance_interval) VALUES (?, ?, ?)");
    $s->bind_param('ssi', $ten, $mo, $han);
    echo json_encode($s->execute()
        ? ['success' => true, 'message' => 'Thêm loại thành công', 'id' => $conn->insert_id]
        : ['success' => false, 'message' => $conn->error]);
    break;

case 'update_category':
    $id  = (int)($_POST['id'] ?? 0);
    $ten = trim($_POST['ten_loai'] ?? '');
    $mo  = trim($_POST['mo_ta'] ?? '') ?: null;
    $han = max(1, (int)($_POST['han_bao_tri_ngay'] ?? 180));
    if (!$id || !$ten) { echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']); exit; }
    $s = $conn->prepare("UPDATE EquipmentType SET type_name=?, description=?, maintenance_interval=? WHERE type_id=?");
    $s->bind_param('ssii', $ten, $mo, $han, $id);
    echo json_encode($s->execute()
        ? ['success' => true, 'message' => 'Cập nhật thành công']
        : ['success' => false, 'message' => $conn->error]);
    break;

case 'delete_category':
    $id  = (int)($_POST['id'] ?? 0);
    $chk = $conn->query("SELECT COUNT(*) c FROM Equipment WHERE type_id = $id")->fetch_assoc()['c'];
    if ($chk > 0) {
        echo json_encode(['success' => false, 'message' => "Không thể xóa: có $chk thiết bị thuộc loại này"]);
        exit;
    }
    $s = $conn->prepare("DELETE FROM EquipmentType WHERE type_id = ?");
    $s->bind_param('i', $id);
    echo json_encode($s->execute()
        ? ['success' => true, 'message' => 'Đã xóa']
        : ['success' => false, 'message' => $conn->error]);
    break;

// ── THIẾT BỊ (Equipment) ────────────────────────────────────────────
case 'get_devices':
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = (int)($_GET['limit'] ?? 15);
    $search = trim($_GET['search'] ?? '');
    $loai   = trim($_GET['loai'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $alert  = trim($_GET['alert'] ?? '');
    $offset = ($page - 1) * $limit;

    $w = []; $p = []; $t = '';
    if ($search !== '') { $w[] = "e.equipment_name LIKE ?"; $p[] = "%$search%"; $t .= 's'; }
    if ($loai   !== '') { $w[] = "e.type_id = ?";            $p[] = $loai;       $t .= 'i'; }
    if ($status !== '') { $w[] = "e.condition_status = ?";   $p[] = $status;     $t .= 's'; }
    if ($alert === 'overdue') {
        $w[] = "(e.last_maintenance_date IS NOT NULL OR e.purchase_date IS NOT NULL)
                AND DATE_ADD(COALESCE(e.last_maintenance_date, e.purchase_date, CURDATE()),
                    INTERVAL COALESCE(et.maintenance_interval, 180) DAY) <= CURDATE()";
    }
    $ws = $w ? 'WHERE ' . implode(' AND ', $w) : '';

    $cs = $conn->prepare("SELECT COUNT(*) total FROM Equipment e LEFT JOIN EquipmentType et ON et.type_id = e.type_id $ws");
    if ($p) $cs->bind_param($t, ...$p);
    $cs->execute();
    $total = $cs->get_result()->fetch_assoc()['total'];

    // Returns: equipment_id, equipment_name, condition_status, type_id, type_name,
    //          purchase_price, purchase_date, last_maintenance_date, description,
    //          maintenance_interval, days_remaining
    $ds = $conn->prepare("
        SELECT e.*, et.type_name, et.maintenance_interval,
               DATEDIFF(
                   DATE_ADD(COALESCE(e.last_maintenance_date, e.purchase_date, CURDATE()),
                       INTERVAL COALESCE(et.maintenance_interval, 180) DAY),
                   CURDATE()
               ) AS days_remaining
        FROM Equipment e
        LEFT JOIN EquipmentType et ON et.type_id = e.type_id
        $ws
        ORDER BY e.equipment_id DESC
        LIMIT ? OFFSET ?
    ");
    $dp = $p; $dt = $t; $dp[] = $limit; $dp[] = $offset; $dt .= 'ii';
    $ds->bind_param($dt, ...$dp);
    $ds->execute();
    $rows = $ds->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode([
        'success'    => true, 'data' => $rows,
        'total'      => (int)$total, 'page' => $page,
        'limit'      => $limit,
        'totalPages' => max(1, ceil($total / $limit))
    ]);
    break;

case 'get_device_detail':
    $id = (int)($_GET['id'] ?? 0);
    $s  = $conn->prepare("
        SELECT e.*, et.type_name, et.maintenance_interval,
               DATEDIFF(
                   DATE_ADD(COALESCE(e.last_maintenance_date, e.purchase_date, CURDATE()),
                       INTERVAL COALESCE(et.maintenance_interval, 180) DAY),
                   CURDATE()
               ) AS days_remaining
        FROM Equipment e
        LEFT JOIN EquipmentType et ON et.type_id = e.type_id
        WHERE e.equipment_id = ?
    ");
    $s->bind_param('i', $id); $s->execute();
    $dev = $s->get_result()->fetch_assoc();

    // Returns: maintenance_id, equipment_id, maintenance_date, description, cost, performed_by, status
    $s2 = $conn->prepare("SELECT * FROM EquipmentMaintenance WHERE equipment_id = ? ORDER BY maintenance_date DESC LIMIT 5");
    $s2->bind_param('i', $id); $s2->execute();
    $hist = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'device' => $dev, 'history' => $hist]);
    break;

case 'add_device':
case 'update_device':
    $id        = (int)($_POST['id'] ?? 0);
    $ten       = trim($_POST['ten_thiet_bi'] ?? '');
    $type_id   = (int)($_POST['loai_id'] ?? 0) ?: null;
    $condition = trim($_POST['tinh_trang'] ?? 'Hoạt động');
    $gia       = trim($_POST['gia_mua'] ?? '') !== '' ? (float)$_POST['gia_mua'] : null;
    $ngay_mua  = trim($_POST['ngay_mua'] ?? '') ?: null;
    $ngay_bao  = trim($_POST['ngay_bao_tri_gan'] ?? '') ?: null;
    $mo_ta     = trim($_POST['mo_ta'] ?? '') ?: null;
    $room_id   = (int)($_POST['phong_tap_id'] ?? 0) ?: null;

    if (!$ten) { echo json_encode(['success' => false, 'message' => 'Tên thiết bị không được trống']); exit; }

    if ($action === 'add_device') {
        $s = $conn->prepare("
            INSERT INTO Equipment (equipment_name, type_id, condition_status, purchase_price, purchase_date, last_maintenance_date, description, room_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $s->bind_param('sisssssi', $ten, $type_id, $condition, $gia, $ngay_mua, $ngay_bao, $mo_ta, $room_id);
    } else {
        if (!$id) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }
        $s = $conn->prepare("
            UPDATE Equipment
            SET equipment_name=?, type_id=?, condition_status=?, purchase_price=?,
                purchase_date=?, last_maintenance_date=?, description=?, room_id=?
            WHERE equipment_id=?
        ");
        $s->bind_param('sisssssii', $ten, $type_id, $condition, $gia, $ngay_mua, $ngay_bao, $mo_ta, $room_id, $id);
    }
    echo json_encode($s->execute()
        ? ['success' => true, 'message' => $action === 'add_device' ? 'Thêm thiết bị thành công' : 'Cập nhật thành công', 'id' => $conn->insert_id]
        : ['success' => false, 'message' => $conn->error]);
    break;

case 'delete_device':
    $id = (int)($_POST['id'] ?? 0);
    $conn->query("DELETE FROM EquipmentMaintenance WHERE equipment_id = $id");
    $s = $conn->prepare("DELETE FROM Equipment WHERE equipment_id = ?");
    $s->bind_param('i', $id);
    echo json_encode($s->execute()
        ? ['success' => true, 'message' => 'Đã xóa thiết bị']
        : ['success' => false, 'message' => $conn->error]);
    break;

// ── BẢO TRÌ (EquipmentMaintenance) ─────────────────────────────────
case 'get_maintenance':
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = (int)($_GET['limit'] ?? 15);
    $offset = ($page - 1) * $limit;
    $dev_id = (int)($_GET['device_id'] ?? 0);
    $w      = $dev_id ? "WHERE em.equipment_id = $dev_id" : '';
    $total  = $conn->query("SELECT COUNT(*) c FROM EquipmentMaintenance em $w")->fetch_assoc()['c'];

    $s = $conn->prepare("
        SELECT em.*, e.equipment_name
        FROM EquipmentMaintenance em
        JOIN Equipment e ON e.equipment_id = em.equipment_id
        $w
        ORDER BY em.maintenance_date DESC
        LIMIT ? OFFSET ?
    ");
    $s->bind_param('ii', $limit, $offset); $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode([
        'success'    => true, 'data' => $rows,
        'total'      => (int)$total,
        'totalPages' => max(1, ceil($total / $limit)),
        'page'       => $page
    ]);
    break;

case 'add_maintenance':
    $dev_id = (int)($_POST['thiet_bi_id'] ?? 0);
    $ngay   = trim($_POST['ngay_bao_tri'] ?? date('Y-m-d'));
    $desc   = trim($_POST['noi_dung'] ?? '') ?: null;
    $cost   = (float)($_POST['gia_bao_tri'] ?? 0);
    $nguoi  = trim($_POST['nguoi_thuc_hien'] ?? '') ?: null;
    // ENUM: 'Completed', 'In Progress', 'Scheduled'
    $status = trim($_POST['trang_thai'] ?? 'Completed');

    if (!$dev_id) { echo json_encode(['success' => false, 'message' => 'Chọn thiết bị']); exit; }
    $s = $conn->prepare("
        INSERT INTO EquipmentMaintenance (equipment_id, maintenance_date, description, cost, performed_by, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $s->bind_param('issdss', $dev_id, $ngay, $desc, $cost, $nguoi, $status);
    if ($s->execute()) {
        if ($status === 'Completed')
            $conn->query("UPDATE Equipment SET last_maintenance_date = '$ngay' WHERE equipment_id = $dev_id");
        echo json_encode(['success' => true, 'message' => 'Thêm bảo trì thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    break;

case 'update_maintenance':
    $id     = (int)($_POST['id'] ?? 0);
    $ngay   = trim($_POST['ngay_bao_tri']    ?? date('Y-m-d'));
    $desc   = trim($_POST['noi_dung']        ?? '') ?: null;
    $cost   = (float)($_POST['gia_bao_tri']  ?? 0);
    $nguoi  = trim($_POST['nguoi_thuc_hien'] ?? '') ?: null;
    $status = trim($_POST['trang_thai']      ?? 'Completed');
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); exit; }
    $s = $conn->prepare("UPDATE EquipmentMaintenance SET maintenance_date=?, description=?, cost=?, performed_by=?, status=? WHERE maintenance_id=?");
    $s->bind_param('ssdssi', $ngay, $desc, $cost, $nguoi, $status, $id);
    if ($s->execute()) {
        // Nếu hoàn thành → cập nhật last_maintenance_date của thiết bị
        if ($status === 'Completed') {
            $row = $conn->query("SELECT equipment_id FROM EquipmentMaintenance WHERE maintenance_id=$id")->fetch_assoc();
            if ($row) $conn->query("UPDATE Equipment SET last_maintenance_date='$ngay' WHERE equipment_id={$row['equipment_id']}");
        }
        echo json_encode(['success' => true, 'message' => 'Cập nhật bảo trì thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    break;

case 'get_maintenance_detail':
    $id = (int)($_GET['id'] ?? 0);
    $s  = $conn->prepare("SELECT em.*, e.equipment_name FROM EquipmentMaintenance em JOIN Equipment e ON e.equipment_id=em.equipment_id WHERE em.maintenance_id=?");
    $s->bind_param('i', $id); $s->execute();
    $row = $s->get_result()->fetch_assoc();
    echo json_encode($row ? ['success' => true, 'data' => $row] : ['success' => false, 'message' => 'Không tìm thấy']);
    break;

case 'delete_maintenance':
    $id = (int)($_POST['id'] ?? 0);
    $s  = $conn->prepare("DELETE FROM EquipmentMaintenance WHERE maintenance_id = ?");
    $s->bind_param('i', $id);
    echo json_encode($s->execute()
        ? ['success' => true, 'message' => 'Đã xóa']
        : ['success' => false, 'message' => $conn->error]);
    break;

case 'get_all_devices_list':
    $rows = $conn->query("SELECT equipment_id, equipment_name FROM Equipment ORDER BY equipment_name")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    break;

default:
    echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
}
?>
