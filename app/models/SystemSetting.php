<?php

class SystemSetting extends Model
{
    private string $uploadDir;
    private string $uploadUrl;
    private int    $maxSize     = 5242880; // 5 MB
    private array  $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private array  $allowedExt  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function __construct()
    {
        parent::__construct();
        $this->uploadDir = PANEL_UPLOAD_DIR;
        $this->uploadUrl = PANEL_UPLOAD_URL;

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // GPS / LOCATION
    // ════════════════════════════════════════════════════════════════════════

    public function saveLocation(float $lat, float $lng, int $radius, string $name, int $check, int $updatedBy): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO gym_settings (setting_key, setting_value, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)"
        );

        foreach ([
            ['gym_lat',           (string) $lat],
            ['gym_lng',           (string) $lng],
            ['gym_radius_m',      (string) $radius],
            ['gym_location_name', $name],
            ['location_check',    (string) $check],
        ] as [$k, $v]) {
            $stmt->bind_param('ssi', $k, $v, $updatedBy);
            if (!$stmt->execute()) { $stmt->close(); return false; }
        }

        $stmt->close();
        return true;
    }

    public function getAttendanceStats(string $date): array
    {
        $date    = $this->db->real_escape_string($date);
        $total   = (int) $this->db->query("SELECT COUNT(*) AS c FROM Employee")->fetch_assoc()['c'];
        $present = (int) $this->db->query(
            "SELECT COUNT(*) AS c FROM Attendance WHERE work_date='$date' AND status IN ('Present','Late')"
        )->fetch_assoc()['c'];
        $gpsRes  = $this->db->query(
            "SELECT COUNT(*) AS c FROM Attendance WHERE work_date='$date' AND checkin_lat IS NOT NULL"
        );
        $gps = $gpsRes ? (int) $gpsRes->fetch_assoc()['c'] : 0;

        return ['total' => $total, 'present' => $present, 'not_yet' => max(0, $total - $present), 'gps_count' => $gps];
    }

    // ════════════════════════════════════════════════════════════════════════
    // LANDING IMAGES
    // ════════════════════════════════════════════════════════════════════════

    public function listImages(): array
    {
        $rows = $this->db->query(
            "SELECT image_id, image_name, file_name, file_url, file_size,
                    file_ext, sort_order, is_active,
                    DATE_FORMAT(uploaded_at,'%d/%m/%Y %H:%i') AS uploaded_at
             FROM landing_images ORDER BY sort_order ASC, image_id ASC"
        )->fetch_all(MYSQLI_ASSOC);

        foreach ($rows as &$r) {
            $r['file_size_fmt'] = $r['file_size'] > 0 ? round($r['file_size'] / 1024) . ' KB' : '?';
            $r['file_ext']      = strtoupper($r['file_ext']);
            $r['image_id']      = (int) $r['image_id'];
            $r['is_active']     = (int) $r['is_active'];
            $r['sort_order']    = (int) $r['sort_order'];
        }
        return $rows;
    }

    public function uploadImages(array $files, int $accountId): array
    {
        $uploaded = [];
        $errors   = [];

        foreach ($files['tmp_name'] as $i => $tmp) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = $files['name'][$i] . ': Lỗi upload.'; continue;
            }

            $orig = basename($files['name'][$i]);
            $mime = mime_content_type($tmp);
            $size = (int) $files['size'][$i];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

            if (!in_array($mime, $this->allowedMime) || !in_array($ext, $this->allowedExt)) {
                $errors[] = "$orig: Định dạng không hợp lệ (chỉ JPG, PNG, WEBP, GIF)."; continue;
            }
            if ($size > $this->maxSize) {
                $errors[] = "$orig: File vượt quá 5 MB."; continue;
            }

            $filename = $this->generateFilename($orig, $ext);
            $dest     = $this->uploadDir . $filename;
            $url      = $this->uploadUrl . $filename;

            if (!move_uploaded_file($tmp, $dest)) {
                $errors[] = "$orig: Không thể lưu file vào server."; continue;
            }

            $nextOrder = $this->getNextImageOrder();
            $display   = $this->buildDisplayName($orig);
            $fpath     = $this->uploadDir . $filename;

            $stmt = $this->db->prepare(
                "INSERT INTO landing_images
                   (image_name, file_name, file_path, file_url, file_size, file_ext, sort_order, is_active, uploaded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)"
            );
            $stmt->bind_param('ssssissi', $display, $filename, $fpath, $url, $size, $ext, $nextOrder, $accountId);

            if ($stmt->execute()) {
                $uploaded[] = [
                    'image_id'      => (int) $this->db->insert_id,
                    'image_name'    => $display,
                    'file_name'     => $filename,
                    'file_url'      => $url,
                    'file_size_fmt' => round($size / 1024) . ' KB',
                    'file_ext'      => strtoupper($ext),
                    'sort_order'    => $nextOrder,
                    'is_active'     => 1,
                ];
            } else {
                @unlink($dest);
                $errors[] = "$orig: Lỗi lưu vào database.";
            }
            $stmt->close();
        }
        return ['uploaded' => $uploaded, 'errors' => $errors];
    }

    public function deleteImage(int $id): bool
    {
        $row = $this->findImageById($id);
        if (!$row) return false;

        $fpath = $this->uploadDir . $row['file_name'];
        if (file_exists($fpath) && is_file($fpath)) @unlink($fpath);

        $stmt = $this->db->prepare("DELETE FROM landing_images WHERE image_id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function toggleImage(int $id): ?int
    {
        $stmt = $this->db->prepare("UPDATE landing_images SET is_active = 1 - is_active WHERE image_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $row = $this->db->query("SELECT is_active FROM landing_images WHERE image_id = $id")->fetch_assoc();
        return $row ? (int) $row['is_active'] : null;
    }

    public function renameImage(int $id, string $name): string
    {
        $name = htmlspecialchars(mb_substr($name, 0, 255), ENT_QUOTES);
        $stmt = $this->db->prepare("UPDATE landing_images SET image_name = ? WHERE image_id = ?");
        $stmt->bind_param('si', $name, $id);
        $stmt->execute();
        $stmt->close();
        return $name;
    }

    public function reorderImages(array $ids): void
    {
        $stmt = $this->db->prepare("UPDATE landing_images SET sort_order = ? WHERE image_id = ?");
        foreach ($ids as $order => $id) {
            $o = $order + 1; $i = (int) $id;
            $stmt->bind_param('ii', $o, $i);
            $stmt->execute();
        }
        $stmt->close();
    }

    public function replaceImage(int $id, array $file): ?array
    {
        $row = $this->findImageById($id);
        if (!$row) return null;

        $tmp  = $file['tmp_name'];
        $orig = basename($file['name']);
        $mime = mime_content_type($tmp);
        $size = (int) $file['size'];
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

        if (!in_array($mime, $this->allowedMime) || !in_array($ext, $this->allowedExt))
            return ['error' => 'Định dạng không hợp lệ.'];
        if ($size > $this->maxSize)
            return ['error' => 'File vượt quá 5 MB.'];

        $filename = $this->generateFilename($orig, $ext);
        $dest     = $this->uploadDir . $filename;
        $url      = $this->uploadUrl . $filename;

        if (!move_uploaded_file($tmp, $dest))
            return ['error' => 'Không thể lưu file mới vào server.'];

        $oldPath = $this->uploadDir . $row['file_name'];
        if (file_exists($oldPath) && is_file($oldPath)) @unlink($oldPath);

        $fpath = $this->uploadDir . $filename;
        $stmt  = $this->db->prepare(
            "UPDATE landing_images SET file_name=?, file_path=?, file_url=?, file_size=?, file_ext=? WHERE image_id=?"
        );
        $stmt->bind_param('sssisi', $filename, $fpath, $url, $size, $ext, $id);
        $stmt->execute();
        $stmt->close();

        return ['file_url' => $url, 'file_ext' => strtoupper($ext), 'file_size_fmt' => round($size / 1024) . ' KB'];
    }

    // ════════════════════════════════════════════════════════════════════════
    // PACKAGE TYPE
    // ════════════════════════════════════════════════════════════════════════

    public function listPackageTypes(): array
    {
        $res  = $this->db->query("SELECT * FROM PackageType ORDER BY sort_order ASC, type_id ASC");
        $rows = [];
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        return $rows;
    }

    public function addPackageType(string $name, string $desc, string $color, int $order): int|false
    {
        if ($order === 0) {
            $max   = $this->db->query("SELECT MAX(sort_order) AS m FROM PackageType")->fetch_assoc()['m'] ?? 0;
            $order = (int) $max + 1;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO PackageType (type_name, description, color_code, sort_order, is_active) VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->bind_param('sssi', $name, $desc, $color, $order);
        $ok = $stmt->execute();
        $id = $ok ? (int) $this->db->insert_id : false;
        $stmt->close();
        return $id;
    }

    public function updatePackageType(int $id, string $name, string $desc, string $color, int $order): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE PackageType SET type_name=?, description=?, color_code=?, sort_order=? WHERE type_id=?"
        );
        $stmt->bind_param('sssii', $name, $desc, $color, $order, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function togglePackageType(int $id): ?bool
    {
        $stmt = $this->db->prepare("UPDATE PackageType SET is_active = 1 - is_active WHERE type_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $row = $this->db->query("SELECT is_active FROM PackageType WHERE type_id=$id")->fetch_assoc();
        return $row ? (bool) $row['is_active'] : null;
    }

    public function deletePackageType(int $id): array
    {
        $used = (int) $this->db->query(
            "SELECT COUNT(*) c FROM MembershipPlan WHERE package_type_id=$id"
        )->fetch_assoc()['c'];
        if ($used > 0) return ['ok' => false, 'msg' => "Không thể xóa — có $used gói tập đang dùng!"];

        $stmt = $this->db->prepare("DELETE FROM PackageType WHERE type_id=?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? ['ok' => true, 'msg' => 'Đã xóa loại gói!'] : ['ok' => false, 'msg' => 'Lỗi: ' . $this->db->error];
    }

    // ════════════════════════════════════════════════════════════════════════
    // EQUIPMENT TYPE
    // ════════════════════════════════════════════════════════════════════════

    public function listEquipmentTypes(): array
    {
        $res  = $this->db->query("SELECT * FROM EquipmentType ORDER BY type_id ASC");
        $rows = [];
        if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
        return $rows;
    }

    public function addEquipmentType(string $name, string $desc, int $interval): int|false
    {
        $chk = $this->db->prepare("SELECT type_id FROM EquipmentType WHERE type_name=?");
        $chk->bind_param('s', $name);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();
        if ($exists) return false;

        $stmt = $this->db->prepare(
            "INSERT INTO EquipmentType (type_name, description, maintenance_interval) VALUES (?, ?, ?)"
        );
        $stmt->bind_param('ssi', $name, $desc, $interval);
        $ok = $stmt->execute();
        $id = $ok ? (int) $this->db->insert_id : false;
        $stmt->close();
        return $id;
    }

    public function updateEquipmentType(int $id, string $name, string $desc, int $interval): bool|string
    {
        $chk = $this->db->prepare("SELECT type_id FROM EquipmentType WHERE type_name=? AND type_id!=?");
        $chk->bind_param('si', $name, $id);
        $chk->execute();
        $dup = $chk->get_result()->num_rows > 0;
        $chk->close();
        if ($dup) return 'Tên đã tồn tại';

        $stmt = $this->db->prepare(
            "UPDATE EquipmentType SET type_name=?, description=?, maintenance_interval=? WHERE type_id=?"
        );
        $stmt->bind_param('ssii', $name, $desc, $interval, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteEquipmentType(int $id): array
    {
        $used = (int) $this->db->query(
            "SELECT COUNT(*) c FROM Equipment WHERE type_id=$id"
        )->fetch_assoc()['c'];
        if ($used > 0) return ['ok' => false, 'msg' => "Không thể xóa — có $used thiết bị đang dùng!"];

        $stmt = $this->db->prepare("DELETE FROM EquipmentType WHERE type_id=?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? ['ok' => true, 'msg' => 'Đã xóa loại thiết bị!'] : ['ok' => false, 'msg' => 'Lỗi: ' . $this->db->error];
    }

    // ════════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════════════

    private function findImageById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT file_name FROM landing_images WHERE image_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    private function getNextImageOrder(): int
    {
        $r = $this->db->query("SELECT COALESCE(MAX(sort_order), 0) + 1 AS nx FROM landing_images");
        return (int) ($r->fetch_assoc()['nx'] ?? 1);
    }

    private function generateFilename(string $orig, string $ext): string
    {
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
        return $base . '_' . uniqid() . '.' . $ext;
    }

    private function buildDisplayName(string $orig): string
    {
        $base    = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
        $display = preg_replace('/_[a-f0-9]{13}$/', '', $base);
        $display = ucwords(str_replace(['_', '-'], ' ', $display));
        return mb_strlen($display) >= 2 ? $display : $orig;
    }
}
