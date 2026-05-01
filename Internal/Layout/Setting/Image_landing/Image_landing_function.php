<?php
/**
 * Image_landing_function.php
 * Xử lý AJAX: upload, xóa, toggle, rename, reorder, list ảnh slideshow
 *
 * Vị trí file : C:\wamp64\www\PHP\ELite_GYM\Internal\Layout\Setting\Image_landing\Image_landing_function.php
 * Kết nối DB  : C:\wamp64\www\PHP\ELite_GYM\Database\db.php   (database: datn)
 * Thư mục ảnh : C:\wamp64\www\PHP\ELite_GYM\upload\image_panel\
 */

ob_start();
session_start();

// ── Bảo vệ: chỉ Admin ────────────────────────────────────────────
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Không có quyền truy cập.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ── Kết nối DB ───────────────────────────────────────────────────
// Từ: Internal/Layout/Setting/Image_landing/
// Lên 4 cấp  → ELite_GYM/
// Vào        → Database/db.php
require_once __DIR__ . '/../../../../Database/db.php';
// $conn được định nghĩa trong db.php (database: datn)

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['ok' => false, 'msg' => 'Lỗi kết nối database.']);
    exit;
}

// ── Cấu hình đường dẫn ──────────────────────────────────────────
define('UPLOAD_DIR', 'C:/wamp64/www/PHP/ELite_GYM/upload/image_panel/');
define('UPLOAD_URL', '/PHP/ELite_GYM/upload/image_panel/');
define('MAX_SIZE',   5 * 1024 * 1024); // 5 MB

$ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

// Tạo thư mục nếu chưa có
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

// ════════════════════════════════════════════════════════════════
//  ACTION: upload
// ════════════════════════════════════════════════════════════════
if ($action === 'upload') {

    if (empty($_FILES['images']['tmp_name'])) {
        echo json_encode(['ok' => false, 'msg' => 'Không có file nào được gửi lên.']);
        exit;
    }

    $uploaded   = [];
    $errors     = [];
    $account_id = (int)($_SESSION['account_id'] ?? 0);

    foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {

        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = $_FILES['images']['name'][$i] . ': Lỗi upload.';
            continue;
        }

        $orig = basename($_FILES['images']['name'][$i]);
        $mime = mime_content_type($tmp);
        $size = (int)$_FILES['images']['size'][$i];
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

        // Kiểm tra định dạng
        if (!in_array($mime, $ALLOWED_MIME) || !in_array($ext, $ALLOWED_EXT)) {
            $errors[] = "$orig: Định dạng không hợp lệ (chỉ JPG, PNG, WEBP, GIF).";
            continue;
        }
        // Kiểm tra dung lượng
        if ($size > MAX_SIZE) {
            $errors[] = "$orig: File vượt quá 5 MB.";
            continue;
        }

        // Tên file an toàn + unique
        $base     = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
        $filename = $base . '_' . uniqid() . '.' . $ext;
        $dest     = UPLOAD_DIR . $filename;
        $url      = UPLOAD_URL . $filename;

        if (!move_uploaded_file($tmp, $dest)) {
            $errors[] = "$orig: Không thể lưu file vào server.";
            continue;
        }

        // Lấy sort_order tiếp theo
        $r = $conn->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS nx FROM landing_images");
        $next_order = (int)($r->fetch_assoc()['nx'] ?? 1);

        // Tạo tên hiển thị từ tên file (bỏ suffix uniqid 13 ký tự)
        $display = preg_replace('/_[a-f0-9]{13}$/', '', $base);
        $display = ucwords(str_replace(['_', '-'], ' ', $display));
        if (mb_strlen($display) < 2) $display = $orig; // fallback

        // Lưu vào DB
        $stmt = $conn->prepare("
            INSERT INTO landing_images
              (image_name, file_name, file_path, file_url, file_size, file_ext,
               sort_order, is_active, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $fpath = UPLOAD_DIR . $filename;
        $stmt->bind_param("ssssissi",
            $display, $filename, $fpath, $url,
            $size, $ext, $next_order, $account_id
        );

        if ($stmt->execute()) {
            $uploaded[] = [
                'image_id'      => (int)$conn->insert_id,
                'image_name'    => $display,
                'file_name'     => $filename,
                'file_url'      => $url,
                'file_size_fmt' => round($size / 1024) . ' KB',
                'file_ext'      => strtoupper($ext),
                'sort_order'    => $next_order,
                'is_active'     => 1,
            ];
        } else {
            @unlink($dest); // rollback file nếu DB thất bại
            $errors[] = "$orig: Lỗi lưu vào database.";
        }
        $stmt->close();
    }

    echo json_encode([
        'ok'       => count($uploaded) > 0,
        'uploaded' => $uploaded,
        'errors'   => $errors,
        'msg'      => count($uploaded) . ' ảnh upload thành công.'
                    . (count($errors) ? ' ' . count($errors) . ' lỗi.' : ''),
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════════
//  ACTION: delete
// ════════════════════════════════════════════════════════════════
if ($action === 'delete') {
    $image_id = (int)($_POST['image_id'] ?? 0);
    if (!$image_id) {
        echo json_encode(['ok' => false, 'msg' => 'Thiếu image_id.']);
        exit;
    }

    // Lấy tên file trước khi xóa
    $stmt = $conn->prepare("SELECT file_name FROM landing_images WHERE image_id = ?");
    $stmt->bind_param("i", $image_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy ảnh.']);
        exit;
    }

    // Xóa file vật lý
    $fpath = UPLOAD_DIR . $row['file_name'];
    if (file_exists($fpath) && is_file($fpath)) {
        @unlink($fpath);
    }

    // Xóa bản ghi trong DB
    $stmt2 = $conn->prepare("DELETE FROM landing_images WHERE image_id = ?");
    $stmt2->bind_param("i", $image_id);
    $stmt2->execute();
    $stmt2->close();

    echo json_encode(['ok' => true, 'msg' => 'Đã xóa ảnh.']);
    exit;
}

// ════════════════════════════════════════════════════════════════
//  ACTION: toggle (bật / tắt hiển thị)
// ════════════════════════════════════════════════════════════════
if ($action === 'toggle') {
    $image_id = (int)($_POST['image_id'] ?? 0);
    if (!$image_id) {
        echo json_encode(['ok' => false, 'msg' => 'Thiếu image_id.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE landing_images SET is_active = 1 - is_active WHERE image_id = ?");
    $stmt->bind_param("i", $image_id);
    $stmt->execute();
    $stmt->close();

    $row = $conn->query("SELECT is_active FROM landing_images WHERE image_id = $image_id")->fetch_assoc();
    echo json_encode(['ok' => true, 'is_active' => (int)$row['is_active']]);
    exit;
}

// ════════════════════════════════════════════════════════════════
//  ACTION: rename (đổi tên hiển thị)
// ════════════════════════════════════════════════════════════════
if ($action === 'rename') {
    $image_id   = (int)($_POST['image_id'] ?? 0);
    $image_name = trim($_POST['image_name'] ?? '');

    if (!$image_id || $image_name === '') {
        echo json_encode(['ok' => false, 'msg' => 'Dữ liệu không hợp lệ.']);
        exit;
    }

    $image_name = htmlspecialchars(mb_substr($image_name, 0, 255), ENT_QUOTES);
    $stmt = $conn->prepare("UPDATE landing_images SET image_name = ? WHERE image_id = ?");
    $stmt->bind_param("si", $image_name, $image_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true, 'image_name' => $image_name]);
    exit;
}

// ════════════════════════════════════════════════════════════════
//  ACTION: reorder (lưu thứ tự kéo thả)
// ════════════════════════════════════════════════════════════════
if ($action === 'reorder') {
    $ids = json_decode($_POST['ids'] ?? '[]', true);

    if (!is_array($ids) || empty($ids)) {
        echo json_encode(['ok' => false, 'msg' => 'Thiếu dữ liệu.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE landing_images SET sort_order = ? WHERE image_id = ?");
    foreach ($ids as $order => $id) {
        $o = $order + 1;
        $i = (int)$id;
        $stmt->bind_param("ii", $o, $i);
        $stmt->execute();
    }
    $stmt->close();

    echo json_encode(['ok' => true, 'msg' => 'Đã lưu thứ tự.']);
    exit;
}

// ════════════════════════════════════════════════════════════════
//  ACTION: list (lấy toàn bộ danh sách)
// ════════════════════════════════════════════════════════════════
if ($action === 'list') {
    $rows = $conn->query("
        SELECT  image_id,
                image_name,
                file_name,
                file_url,
                file_size,
                file_ext,
                sort_order,
                is_active,
                DATE_FORMAT(uploaded_at, '%d/%m/%Y %H:%i') AS uploaded_at
        FROM    landing_images
        ORDER BY sort_order ASC, image_id ASC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as &$r) {
        $r['file_size_fmt'] = $r['file_size'] > 0 ? round($r['file_size'] / 1024) . ' KB' : '?';
        $r['file_ext']      = strtoupper($r['file_ext']);
        $r['image_id']      = (int)$r['image_id'];
        $r['is_active']     = (int)$r['is_active'];
        $r['sort_order']    = (int)$r['sort_order'];
    }

    echo json_encode(['ok' => true, 'data' => $rows]);
    exit;
}

// ════════════════════════════════════════════════════════════════
//  ACTION: replace (thay ảnh mới — xóa file cũ, lưu file mới)
// ════════════════════════════════════════════════════════════════
if ($action === 'replace') {
    $image_id = (int)($_POST['image_id'] ?? 0);

    if (!$image_id || empty($_FILES['image']['tmp_name'])) {
        echo json_encode(['ok' => false, 'msg' => 'Thiếu image_id hoặc file ảnh.']);
        exit;
    }

    // Lấy thông tin ảnh cũ
    $stmt = $conn->prepare("SELECT file_name FROM landing_images WHERE image_id = ?");
    $stmt->bind_param("i", $image_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy ảnh trong DB.']);
        exit;
    }

    $tmp  = $_FILES['image']['tmp_name'];
    $orig = basename($_FILES['image']['name']);
    $mime = mime_content_type($tmp);
    $size = (int)$_FILES['image']['size'];
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

    if (!in_array($mime, $ALLOWED_MIME) || !in_array($ext, $ALLOWED_EXT)) {
        echo json_encode(['ok' => false, 'msg' => 'Định dạng không hợp lệ (chỉ JPG, PNG, WEBP, GIF).']);
        exit;
    }
    if ($size > MAX_SIZE) {
        echo json_encode(['ok' => false, 'msg' => 'File vượt quá 5 MB.']);
        exit;
    }

    // Tên file mới an toàn + unique
    $base     = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
    $filename = $base . '_' . uniqid() . '.' . $ext;
    $dest     = UPLOAD_DIR . $filename;
    $url      = UPLOAD_URL . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        echo json_encode(['ok' => false, 'msg' => 'Không thể lưu file mới vào server.']);
        exit;
    }

    // Xóa file cũ khỏi disk
    $oldPath = UPLOAD_DIR . $row['file_name'];
    if (file_exists($oldPath) && is_file($oldPath)) {
        @unlink($oldPath);
    }

    // Cập nhật DB (chỉ đổi file, giữ nguyên image_name và sort_order)
    $fpath = UPLOAD_DIR . $filename;
    $stmt2 = $conn->prepare("
        UPDATE landing_images
        SET file_name = ?, file_path = ?, file_url = ?, file_size = ?, file_ext = ?
        WHERE image_id = ?
    ");
    $stmt2->bind_param("sssisi", $filename, $fpath, $url, $size, $ext, $image_id);
    $stmt2->execute();
    $stmt2->close();

    echo json_encode([
        'ok'       => true,
        'file_url' => $url,
        'file_ext' => strtoupper($ext),
        'file_size_fmt' => round($size / 1024) . ' KB',
        'msg'      => 'Đã thay ảnh thành công.',
    ]);
    exit;
}

// ── Action không hợp lệ ─────────────────────────────────────────
echo json_encode(['ok' => false, 'msg' => "Action '$action' không hợp lệ."]);
exit;
