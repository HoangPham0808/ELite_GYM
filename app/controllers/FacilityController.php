<?php

class FacilityController extends Controller
{
    public function api(): void
    {
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        header('Content-Type: application/json; charset=utf-8');

        $facilityModel = new Facility();
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'get_stats':
                echo json_encode(array_merge(['success' => true], $facilityModel->getStats()));
                break;

            case 'get_categories':
                echo json_encode(['success' => true, 'data' => $facilityModel->getCategories()]);
                break;

            case 'get_rooms':
                echo json_encode(['success' => true, 'data' => $facilityModel->getRooms()]);
                break;

            case 'get_devices':
                $page   = max(1, (int)($_GET['page'] ?? 1));
                $limit  = (int)($_GET['limit'] ?? 15);
                $search = trim($_GET['search'] ?? '');
                $loai   = trim($_GET['loai'] ?? '');
                $status = trim($_GET['status'] ?? '');
                $alert  = trim($_GET['alert'] ?? '');

                $res = $facilityModel->getDevicesList($page, $limit, $search, $loai, $status, $alert);
                echo json_encode(array_merge(['success' => true, 'limit' => $limit, 'page' => $page], $res));
                break;

            case 'get_device_detail':
                $id = (int)($_GET['id'] ?? 0);
                $res = $facilityModel->getDeviceDetail($id);
                if (!$res) {
                    echo json_encode(['success' => false, 'message' => 'Không tìm thấy thiết bị']);
                } else {
                    echo json_encode(array_merge(['success' => true], $res));
                }
                break;

            case 'add_device':
            case 'update_device':
                $id        = (int)($_POST['id'] ?? 0);
                $ten       = trim($_POST['ten_thiet_bi'] ?? '');
                $type_id   = (int)($_POST['loai_id'] ?? 0) ?: null;
                $condition = trim($_POST['tinh_trang'] ?? 'Hoạt động');
                $gia       = trim($_POST['gia_mua'] ?? '') !== '' ? (float)$_POST['gia_mua'] : null;
                $ngay_mua_raw = trim($_POST['ngay_mua'] ?? '');
                $ngay_mua  = ($ngay_mua_raw && $ngay_mua_raw !== '0000-00-00' && strtotime($ngay_mua_raw))
                             ? date('Y-m-d', strtotime($ngay_mua_raw))
                             : date('Y-m-d');
                $ngay_bao_raw = trim($_POST['ngay_bao_tri_gan'] ?? '');
                $ngay_bao  = ($ngay_bao_raw && $ngay_bao_raw !== '0000-00-00' && strtotime($ngay_bao_raw))
                             ? date('Y-m-d', strtotime($ngay_bao_raw))
                             : null;
                $mo_ta     = trim($_POST['mo_ta'] ?? '') ?: null;
                $room_id   = (int)($_POST['phong_tap_id'] ?? 0) ?: null;

                if (!$type_id && !empty($_POST['type_name_import'])) {
                    $type_id = $facilityModel->resolveTypeIdByName($_POST['type_name_import']);
                }
                if (!$room_id && !empty($_POST['room_name_import'])) {
                    $room_id = $facilityModel->resolveRoomIdByName($_POST['room_name_import']);
                }

                $validConditions = ['Hoạt động', 'Hỏng', 'Đang bảo dưỡng', 'Ngừng sử dụng'];
                if (!in_array($condition, $validConditions)) $condition = 'Hoạt động';

                if (!$ten) {
                    echo json_encode(['success' => false, 'message' => 'Tên thiết bị không được trống']); break;
                }

                if ($action === 'add_device') {
                    $insertId = $facilityModel->addDevice($ten, $type_id, $condition, $gia, $ngay_mua, $ngay_bao, $mo_ta, $room_id);
                    echo json_encode($insertId
                        ? ['success' => true, 'message' => 'Thêm thiết bị thành công', 'id' => $insertId]
                        : ['success' => false, 'message' => 'Không thể thêm thiết bị']);
                } else {
                    if (!$id) {
                        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); break;
                    }
                    $ok = $facilityModel->updateDevice($id, $ten, $type_id, $condition, $gia, $ngay_mua, $ngay_bao, $mo_ta, $room_id);
                    echo json_encode($ok
                        ? ['success' => true, 'message' => 'Cập nhật thành công']
                        : ['success' => false, 'message' => 'Không thể cập nhật thiết bị']);
                }
                break;

            case 'delete_device':
                $id = (int)($_POST['id'] ?? 0);
                $ok = $facilityModel->deleteDevice($id);
                echo json_encode($ok
                    ? ['success' => true, 'message' => 'Đã xóa thiết bị']
                    : ['success' => false, 'message' => 'Lỗi khi xóa thiết bị']);
                break;

            case 'get_maintenance':
                $page   = max(1, (int)($_GET['page'] ?? 1));
                $limit  = (int)($_GET['limit'] ?? 15);
                $dev_id = (int)($_GET['device_id'] ?? 0);

                $res = $facilityModel->getMaintenanceList($page, $limit, $dev_id);
                echo json_encode(array_merge(['success' => true, 'page' => $page], $res));
                break;

            case 'add_maintenance':
                $dev_id = (int)($_POST['thiet_bi_id'] ?? 0);
                $ngay   = trim($_POST['ngay_bao_tri'] ?? date('Y-m-d'));
                $desc   = trim($_POST['noi_dung'] ?? '') ?: null;
                $cost   = (float)($_POST['gia_bao_tri'] ?? 0);
                $nguoi  = trim($_POST['nguoi_thuc_hien'] ?? '') ?: null;
                $status = trim($_POST['trang_thai'] ?? 'Completed');

                if (!$dev_id) {
                    echo json_encode(['success' => false, 'message' => 'Chọn thiết bị']); break;
                }
                $ok = $facilityModel->addMaintenance($dev_id, $ngay, $desc, $cost, $nguoi, $status);
                echo json_encode($ok
                    ? ['success' => true, 'message' => 'Thêm bảo trì thành công']
                    : ['success' => false, 'message' => 'Lỗi khi thêm bảo trì']);
                break;

            case 'update_maintenance':
                $id     = (int)($_POST['id'] ?? 0);
                $ngay   = trim($_POST['ngay_bao_tri']    ?? date('Y-m-d'));
                $desc   = trim($_POST['noi_dung']        ?? '') ?: null;
                $cost   = (float)($_POST['gia_bao_tri']  ?? 0);
                $nguoi  = trim($_POST['nguoi_thuc_hien'] ?? '') ?: null;
                $status = trim($_POST['trang_thai']      ?? 'Completed');

                if (!$id) {
                    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']); break;
                }
                $ok = $facilityModel->updateMaintenance($id, $ngay, $desc, $cost, $nguoi, $status);
                echo json_encode($ok
                    ? ['success' => true, 'message' => 'Cập nhật bảo trì thành công']
                    : ['success' => false, 'message' => 'Lỗi khi cập nhật bảo trì']);
                break;

            case 'get_maintenance_detail':
                $id = (int)($_GET['id'] ?? 0);
                $row = $facilityModel->getMaintenanceDetail($id);
                echo json_encode($row ? ['success' => true, 'data' => $row] : ['success' => false, 'message' => 'Không tìm thấy']);
                break;

            case 'delete_maintenance':
                $id = (int)($_POST['id'] ?? 0);
                $ok = $facilityModel->deleteMaintenance($id);
                echo json_encode($ok ? ['success' => true, 'message' => 'Đã xóa'] : ['success' => false, 'message' => 'Lỗi khi xóa']);
                break;

            case 'get_all_devices_list':
                echo json_encode(['success' => true, 'data' => $facilityModel->getAllDevicesList()]);
                break;

            case 'get_trainer_room_devices':
                $trainer_id = (int)($_GET['trainer_id'] ?? 0);
                if (!$trainer_id) {
                    echo json_encode(['success' => false, 'message' => 'Thiếu trainer_id']); break;
                }
                $page   = max(1, (int)($_GET['page'] ?? 1));
                $limit  = (int)($_GET['limit'] ?? 15);

                $res = $facilityModel->getTrainerRoomDevices($trainer_id, $page, $limit);
                if ($res === null) {
                    echo json_encode([
                        'success'   => true,
                        'data'      => [],
                        'room_name' => null,
                        'message'   => 'Hôm nay bạn chưa đăng ký dạy buổi nào hoặc buổi tập không có phòng.'
                    ]);
                } else {
                    echo json_encode(array_merge(['success' => true], $res));
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
        }
    }
}
