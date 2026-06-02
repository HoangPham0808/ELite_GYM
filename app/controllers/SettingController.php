<?php

class SettingController extends Controller
{
    // ════════════════════════════════════════════════════════════════════════
    // SYSTEM (PackageType + EquipmentType)
    // ════════════════════════════════════════════════════════════════════════

    public function apiSystem(): void
    {
        AuthMiddleware::requireRole('Admin');

        $model  = new SystemSetting();
        $action = trim($_GET['action'] ?? $_POST['action'] ?? '');

        // ── Package Type ──────────────────────────────────────────────────
        if ($action === 'list') {
            $this->jsonOut(['ok' => true, 'data' => $model->listPackageTypes()]);
        }

        if ($action === 'add') {
            $name  = trim($_POST['type_name']   ?? '');
            $desc  = trim($_POST['description'] ?? '');
            $color = trim($_POST['color_code']  ?? '#6b7280');
            $order = max(0, (int) ($_POST['sort_order'] ?? 0));

            if (!$name) $this->jsonOut(['ok' => false, 'msg' => 'Tên loại gói không được trống']);
            if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $color)) $color = '#6b7280';

            $id = $model->addPackageType($name, $desc, $color, $order);
            $id !== false
                ? $this->jsonOut(['ok' => true,  'msg' => 'Đã thêm loại gói thành công!', 'id' => $id])
                : $this->jsonOut(['ok' => false, 'msg' => 'Lỗi khi thêm loại gói.']);
        }

        if ($action === 'update') {
            $id    = (int)  ($_POST['type_id']     ?? 0);
            $name  = trim(   $_POST['type_name']   ?? '');
            $desc  = trim(   $_POST['description'] ?? '');
            $color = trim(   $_POST['color_code']  ?? '#6b7280');
            $order = (int)  ($_POST['sort_order']  ?? 0);

            if (!$id)   $this->jsonOut(['ok' => false, 'msg' => 'ID không hợp lệ']);
            if (!$name) $this->jsonOut(['ok' => false, 'msg' => 'Tên không được trống']);
            if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $color)) $color = '#6b7280';

            $model->updatePackageType($id, $name, $desc, $color, $order)
                ? $this->jsonOut(['ok' => true,  'msg' => 'Đã cập nhật thành công!'])
                : $this->jsonOut(['ok' => false, 'msg' => 'Lỗi khi cập nhật.']);
        }

        if ($action === 'toggle') {
            $id = (int) ($_POST['type_id'] ?? 0);
            if (!$id) $this->jsonOut(['ok' => false, 'msg' => 'ID không hợp lệ']);

            $active = $model->togglePackageType($id);
            $this->jsonOut(['ok' => true, 'msg' => ($active ? 'Đã bật' : 'Đã tắt') . ' loại gói!']);
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['type_id'] ?? 0);
            if (!$id) $this->jsonOut(['ok' => false, 'msg' => 'ID không hợp lệ']);
            $this->jsonOut($model->deletePackageType($id));
        }

        // ── Equipment Type ────────────────────────────────────────────────
        if ($action === 'eq_list') {
            $this->jsonOut(['ok' => true, 'data' => $model->listEquipmentTypes()]);
        }

        if ($action === 'eq_add') {
            $name     = trim($_POST['type_name']   ?? '');
            $desc     = trim($_POST['description'] ?? '');
            $interval = max(1, (int) ($_POST['maintenance_interval'] ?? 180));

            if (!$name) $this->jsonOut(['ok' => false, 'msg' => 'Tên loại thiết bị không được trống']);

            $id = $model->addEquipmentType($name, $desc, $interval);
            $id === false
                ? $this->jsonOut(['ok' => false, 'msg' => 'Tên loại thiết bị đã tồn tại'])
                : $this->jsonOut(['ok' => true,  'msg' => 'Đã thêm loại thiết bị thành công!', 'id' => $id]);
        }

        if ($action === 'eq_update') {
            $id       = (int)  ($_POST['type_id']              ?? 0);
            $name     = trim(   $_POST['type_name']            ?? '');
            $desc     = trim(   $_POST['description']          ?? '');
            $interval = max(1, (int) ($_POST['maintenance_interval'] ?? 180));

            if (!$id)   $this->jsonOut(['ok' => false, 'msg' => 'ID không hợp lệ']);
            if (!$name) $this->jsonOut(['ok' => false, 'msg' => 'Tên không được trống']);

            $result = $model->updateEquipmentType($id, $name, $desc, $interval);
            if ($result === 'Tên đã tồn tại') $this->jsonOut(['ok' => false, 'msg' => 'Tên đã tồn tại']);
            $result
                ? $this->jsonOut(['ok' => true,  'msg' => 'Đã cập nhật thành công!'])
                : $this->jsonOut(['ok' => false, 'msg' => 'Lỗi khi cập nhật.']);
        }

        if ($action === 'eq_delete') {
            $id = (int) ($_POST['type_id'] ?? 0);
            if (!$id) $this->jsonOut(['ok' => false, 'msg' => 'ID không hợp lệ']);
            $this->jsonOut($model->deleteEquipmentType($id));
        }

        $this->jsonOut(['ok' => false, 'msg' => "Action '$action' không hợp lệ"]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // GPS
    // ════════════════════════════════════════════════════════════════════════

    public function apiGps(): void
    {
        AuthMiddleware::requireRole('Admin');

        $model  = new SystemSetting();
        $action = trim($_POST['action'] ?? $_GET['action'] ?? '');

        if ($action === 'search_location') {
            $this->jsonOut($this->proxyNominatim(trim($_GET['q'] ?? '')));
        }

        if ($action === 'save_gym_location') {
            $lat    = (float) ($_POST['lat']            ?? 0);
            $lng    = (float) ($_POST['lng']            ?? 0);
            $radius = max(50, (int) ($_POST['radius']   ?? 100));
            $name   = trim($_POST['name']               ?? 'Elite Gym');
            $check  = (int) ($_POST['location_check']   ?? 1);
            $by     = (int) ($_SESSION['account_id']    ?? 0);

            if ($lat == 0 && $lng == 0) $this->jsonOut(['ok' => false, 'msg' => 'Tọa độ không hợp lệ']);

            $model->saveLocation($lat, $lng, $radius, $name, $check, $by)
                ? $this->jsonOut(['ok' => true,  'msg' => 'Đã lưu cài đặt GPS!'])
                : $this->jsonOut(['ok' => false, 'msg' => 'Lỗi khi lưu GPS.']);
        }

        if ($action === 'get_stats') {
            $date  = $_GET['date'] ?? date('Y-m-d');
            $stats = $model->getAttendanceStats($date);
            $this->jsonOut(array_merge(['ok' => true], $stats));
        }

        $this->jsonOut(['ok' => false, 'msg' => "Action '$action' không hợp lệ"]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // LANDING IMAGES
    // ════════════════════════════════════════════════════════════════════════

    public function apiLanding(): void
    {
        AuthMiddleware::requireRole('Admin');

        $model  = new SystemSetting();
        $action = trim($_POST['action'] ?? $_GET['action'] ?? '');

        if ($action === 'list') {
            $this->jsonOut(['ok' => true, 'data' => $model->listImages()]);
        }

        if ($action === 'upload') {
            if (empty($_FILES['images']['tmp_name'])) {
                $this->jsonOut(['ok' => false, 'msg' => 'Không có file nào được gửi lên.']);
            }
            $result   = $model->uploadImages($_FILES['images'], (int) ($_SESSION['account_id'] ?? 0));
            $uploaded = $result['uploaded'];
            $errors   = $result['errors'];
            $this->jsonOut([
                'ok'       => count($uploaded) > 0,
                'uploaded' => $uploaded,
                'errors'   => $errors,
                'msg'      => count($uploaded) . ' ảnh upload thành công.'
                            . (count($errors) ? ' ' . count($errors) . ' lỗi.' : ''),
            ]);
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['image_id'] ?? 0);
            if (!$id) $this->jsonOut(['ok' => false, 'msg' => 'Thiếu image_id.']);
            $model->deleteImage($id)
                ? $this->jsonOut(['ok' => true,  'msg' => 'Đã xóa ảnh.'])
                : $this->jsonOut(['ok' => false, 'msg' => 'Không tìm thấy ảnh.']);
        }

        if ($action === 'toggle') {
            $id = (int) ($_POST['image_id'] ?? 0);
            if (!$id) $this->jsonOut(['ok' => false, 'msg' => 'Thiếu image_id.']);
            $this->jsonOut(['ok' => true, 'is_active' => $model->toggleImage($id)]);
        }

        if ($action === 'rename') {
            $id   = (int)  ($_POST['image_id']   ?? 0);
            $name = trim(   $_POST['image_name'] ?? '');
            if (!$id || $name === '') $this->jsonOut(['ok' => false, 'msg' => 'Dữ liệu không hợp lệ.']);
            $this->jsonOut(['ok' => true, 'image_name' => $model->renameImage($id, $name)]);
        }

        if ($action === 'reorder') {
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            if (!is_array($ids) || empty($ids)) $this->jsonOut(['ok' => false, 'msg' => 'Thiếu dữ liệu.']);
            $model->reorderImages($ids);
            $this->jsonOut(['ok' => true, 'msg' => 'Đã lưu thứ tự.']);
        }

        if ($action === 'replace') {
            $id = (int) ($_POST['image_id'] ?? 0);
            if (!$id || empty($_FILES['image']['tmp_name'])) {
                $this->jsonOut(['ok' => false, 'msg' => 'Thiếu image_id hoặc file ảnh.']);
            }
            $result = $model->replaceImage($id, $_FILES['image']);
            if (!$result)              $this->jsonOut(['ok' => false, 'msg' => 'Không tìm thấy ảnh trong DB.']);
            if (isset($result['error'])) $this->jsonOut(['ok' => false, 'msg' => $result['error']]);
            $this->jsonOut(array_merge(['ok' => true, 'msg' => 'Đã thay ảnh thành công.'], $result));
        }

        $this->jsonOut(['ok' => false, 'msg' => "Action '$action' không hợp lệ."]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════════════

    private function jsonOut(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function proxyNominatim(string $q): array
    {
        if (!$q) return ['ok' => false, 'results' => []];

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $q, 'format' => 'json', 'limit' => 5, 'accept-language' => 'vi',
        ]);
        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'header' => "User-Agent: EliteGym/1.0\r\n", 'timeout' => 8],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return ['ok' => false, 'results' => [], 'msg' => 'Không kết nối Nominatim'];
        return ['ok' => true, 'results' => json_decode($raw, true) ?? []];
    }
}
