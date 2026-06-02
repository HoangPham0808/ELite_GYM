<?php

class PackageController extends Controller
{
    private Package $packageModel;

    public function __construct()
    {
        $this->packageModel = new Package();
    }

    public function api(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        AuthMiddleware::requireRole(['Admin', 'Employee']);
        ensureSession();

        if (!is_dir(PKG_UPLOAD_DIR)) mkdir(PKG_UPLOAD_DIR, 0755, true);

        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        switch ($action) {
            case 'get_package_types':
                $types = $this->packageModel->getPackageTypes();
                echo json_encode(['success' => true, 'data' => $types]);
                break;

            case 'get_packages':
                $page     = max(1, intval($_GET['page'] ?? 1));
                $limit    = intval($_GET['limit'] ?? 15);
                $search   = trim($_GET['search'] ?? '');
                $duration = intval($_GET['duration'] ?? 0);
                $sort     = $_GET['sort'] ?? 'id_desc';

                $result = $this->packageModel->getPackagesList($page, $limit, $search, $duration, $sort);
                echo json_encode(['success' => true, 'page' => $page, 'limit' => $limit, ...$result]);
                break;

            case 'get_stats':
                echo json_encode(['success' => true, ...$this->packageModel->getStats()]);
                break;

            case 'add_package':
                $name            = trim($_POST['plan_name'] ?? '');
                $duration        = intval($_POST['duration_months'] ?? 0);
                $price           = floatval($_POST['price'] ?? 0);
                $description     = trim($_POST['description'] ?? '') ?: null;
                $package_type_id = intval($_POST['package_type_id'] ?? 0) ?: null;

                if ($name === '') { echo json_encode(['success' => false, 'message' => 'Plan name is required']); exit; }
                if ($duration < 1) { echo json_encode(['success' => false, 'message' => 'Duration must be greater than 0']); exit; }
                if ($price < 0) { echo json_encode(['success' => false, 'message' => 'Invalid price']); exit; }
                if ($this->packageModel->nameExists($name)) { echo json_encode(['success' => false, 'message' => 'Plan name already exists']); exit; }

                $image_url = null;
                if (!empty($_FILES['image_package']['name'])) {
                    $up = $this->handlePackageImage($_FILES['image_package']);
                    if (!$up['ok']) { echo json_encode(['success' => false, 'message' => $up['msg']]); exit; }
                    $image_url = $up['url'];
                }

                $id = $this->packageModel->addPackage($name, $duration, $price, $description, $image_url, $package_type_id);
                if ($id) echo json_encode(['success' => true, 'message' => 'Thêm gói tập thành công', 'id' => $id]);
                else echo json_encode(['success' => false, 'message' => 'Database error']);
                break;

            case 'update_package':
                $id              = intval($_POST['id'] ?? 0);
                $name            = trim($_POST['plan_name'] ?? '');
                $duration        = intval($_POST['duration_months'] ?? 0);
                $price           = floatval($_POST['price'] ?? 0);
                $description     = trim($_POST['description'] ?? '') ?: null;
                $package_type_id = intval($_POST['package_type_id'] ?? 0) ?: null;

                if ($id === 0 || $name === '') { echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']); exit; }
                if ($duration < 1) { echo json_encode(['success' => false, 'message' => 'Duration must be greater than 0']); exit; }
                if ($this->packageModel->nameExists($name, $id)) { echo json_encode(['success' => false, 'message' => 'Plan name already exists']); exit; }

                $old_image = $this->packageModel->getOldImage($id);
                $image_url = $old_image;

                if (!empty($_FILES['image_package']['name'])) {
                    $up = $this->handlePackageImage($_FILES['image_package']);
                    if (!$up['ok']) { echo json_encode(['success' => false, 'message' => $up['msg']]); exit; }
                    if ($old_image) { $f = PKG_UPLOAD_DIR . basename($old_image); if (file_exists($f)) @unlink($f); }
                    $image_url = $up['url'];
                }

                if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
                    if ($old_image) { $f = PKG_UPLOAD_DIR . basename($old_image); if (file_exists($f)) @unlink($f); }
                    $image_url = null;
                }

                $ok = $this->packageModel->updatePackage($id, $name, $duration, $price, $description, $image_url, $package_type_id);
                echo json_encode($ok ? ['success' => true, 'message' => 'Cập nhật gói tập thành công'] : ['success' => false, 'message' => 'Database error']);
                break;

            case 'delete_package':
                $id = intval($_POST['id'] ?? 0);
                if ($id === 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
                echo json_encode($this->packageModel->deletePackage($id));
                break;

            case 'get_detail':
                $id = intval($_GET['id'] ?? 0);
                if ($id === 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
                $detail = $this->packageModel->getDetail($id);
                if (!$detail) { echo json_encode(['success' => false, 'message' => 'Membership plan not found']); exit; }
                echo json_encode(['success' => true, ...$detail]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }

    private function handlePackageImage(array $file): array
    {
        $allowedMime = ['image/jpeg','image/png','image/webp','image/gif'];
        $allowedExt  = ['jpg','jpeg','png','webp','gif'];
        $maxSize     = 5 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'msg' => 'Lỗi upload file.'];
        if ($file['size'] > $maxSize) return ['ok' => false, 'msg' => 'Ảnh vượt quá 5 MB.'];

        $mime = mime_content_type($file['tmp_name']);
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($mime, $allowedMime) || !in_array($ext, $allowedExt))
            return ['ok' => false, 'msg' => 'Định dạng ảnh không hợp lệ (JPG, PNG, WEBP, GIF).'];

        $base     = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $filename = 'pkg_' . $base . '_' . uniqid() . '.' . $ext;
        $dest     = PKG_UPLOAD_DIR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) return ['ok' => false, 'msg' => 'Không thể lưu file.'];
        return ['ok' => true, 'filename' => $filename, 'url' => PKG_UPLOAD_URL . $filename];
    }
}
