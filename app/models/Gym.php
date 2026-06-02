<?php

class Gym extends Model
{
    public function getPackageTypes(): array
    {
        $res = $this->db->query("SELECT type_id, type_name, color_code FROM PackageType WHERE is_active=1 ORDER BY sort_order ASC, type_id ASC");
        $types = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $types[] = $r;
            }
        }
        return $types;
    }

    public function getStats(): array
    {
        $total       = $this->db->query("SELECT COUNT(*) c FROM GymRoom")->fetch_assoc()['c'];
        $active      = $this->db->query("SELECT COUNT(*) c FROM GymRoom WHERE REPLACE(REPLACE(TRIM(status), CHAR(13), ''), CHAR(10), '') = 'Active'")->fetch_assoc()['c'];
        $maintenance = $this->db->query("SELECT COUNT(*) c FROM GymRoom WHERE REPLACE(REPLACE(TRIM(status), CHAR(13), ''), CHAR(10), '') = 'Maintenance'")->fetch_assoc()['c'];
        $capacity    = $this->db->query("SELECT COALESCE(SUM(capacity),0) c FROM GymRoom")->fetch_assoc()['c'];
        $thiet_bi    = $this->db->query("SELECT COUNT(*) c FROM Equipment WHERE condition_status != 'Hỏng' OR condition_status IS NULL")->fetch_assoc()['c'];

        return [
            'total'       => (int)$total,
            'active'      => (int)$active,
            'maintenance' => (int)$maintenance,
            'capacity'    => (int)$capacity,
            'thiet_bi'    => (int)$thiet_bi,
        ];
    }

    public function getGymsList(int $page, int $limit, string $search, string $status, string $sort): array
    {
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
        if ($status !== '') {
            $where[] = "REPLACE(REPLACE(TRIM(status), CHAR(13), ''), CHAR(10), '') = ?";
            $params[] = $status;
            $types .= 's';
        }

        $whereStr = implode(' AND ', $where);

        $orderBy = match($sort) {
            'id_asc'   => 'room_id ASC',
            'name_asc' => 'room_name ASC',
            'cap_desc' => 'capacity DESC',
            default    => 'room_id DESC'
        };

        $cs = $this->db->prepare("SELECT COUNT(*) total FROM GymRoom WHERE $whereStr");
        if ($params) $cs->bind_param($types, ...$params);
        $cs->execute();
        $total = $cs->get_result()->fetch_assoc()['total'];
        $cs->close();

        $ds = $this->db->prepare("
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
        $ds->bind_param($dt, ...$dp);
        $ds->execute();
        $rows = $ds->get_result()->fetch_all(MYSQLI_ASSOC);
        $ds->close();

        return [
            'data'       => $rows,
            'total'      => (int)$total,
            'totalPages' => max(1, ceil($total / $limit)),
        ];
    }

    public function getDetail(int $id): ?array
    {
        $s = $this->db->prepare("
            SELECT gr.*,
                   gr.package_type_id,
                   pt.type_name  AS package_type_name,
                   pt.color_code AS package_type_color
            FROM GymRoom gr
            LEFT JOIN PackageType pt ON pt.type_id = gr.package_type_id
            WHERE gr.room_id = ?
        ");
        $s->bind_param('i', $id);
        $s->execute();
        $room = $s->get_result()->fetch_assoc();
        $s->close();

        if (!$room) return null;

        $s2 = $this->db->prepare("SELECT * FROM Equipment WHERE room_id = ? ORDER BY equipment_name ASC");
        $s2->bind_param('i', $id);
        $s2->execute();
        $thiet_bi = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
        $s2->close();

        return ['room' => $room, 'thiet_bi' => $thiet_bi];
    }

    public function roomNameExists(string $name, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $stmt = $this->db->prepare("SELECT room_id FROM GymRoom WHERE room_name = ? AND room_id != ? LIMIT 1");
            $stmt->bind_param('si', $name, $excludeId);
        } else {
            $stmt = $this->db->prepare("SELECT room_id FROM GymRoom WHERE room_name = ? LIMIT 1");
            $stmt->bind_param('s', $name);
        }
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function addGym(string $roomName, string $status, ?int $capacity, ?float $area, ?int $floor, ?string $openTime, ?string $closeTime, ?string $desc, ?int $packageTypeId): int|false
    {
        $stmt = $this->db->prepare("
            INSERT INTO GymRoom (room_name, status, capacity, area, floor, open_time, close_time, description, package_type_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('ssidisssi', $roomName, $status, $capacity, $area, $floor, $openTime, $closeTime, $desc, $packageTypeId);
        $ok = $stmt->execute();
        $id = $ok ? (int)$this->db->insert_id : false;
        $stmt->close();
        return $id;
    }

    public function updateGym(int $id, string $roomName, string $status, ?int $capacity, ?float $area, ?int $floor, ?string $openTime, ?string $closeTime, ?string $desc, ?int $packageTypeId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE GymRoom
            SET room_name=?, status=?, capacity=?, area=?, floor=?, open_time=?, close_time=?, description=?, package_type_id=?
            WHERE room_id=?
        ");
        $stmt->bind_param('ssidisssii', $roomName, $status, $capacity, $area, $floor, $openTime, $closeTime, $desc, $packageTypeId, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteGym(int $id): array
    {
        $chk = $this->db->prepare("SELECT COUNT(*) c FROM Equipment WHERE room_id = ?");
        $chk->bind_param('i', $id);
        $chk->execute();
        $count = $chk->get_result()->fetch_assoc()['c'];
        $chk->close();

        if ($count > 0) {
            return ['success' => false, 'message' => 'Không thể xóa: phòng tập còn thiết bị liên kết'];
        }

        $stmt = $this->db->prepare("DELETE FROM GymRoom WHERE room_id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $err = $this->db->error;
        $stmt->close();

        return $ok
            ? ['success' => true, 'message' => 'Đã xóa phòng tập']
            : ['success' => false, 'message' => $err];
    }
}
