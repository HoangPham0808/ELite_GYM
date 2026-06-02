<?php

class Facility extends Model
{
    public function getStats(): array
    {
        $total    = $this->db->query("SELECT COUNT(*) c FROM Equipment")->fetch_assoc()['c'];
        $hoatdong = $this->db->query("SELECT COUNT(*) c FROM Equipment WHERE condition_status='Hoạt động'")->fetch_assoc()['c'];
        $hong     = $this->db->query("SELECT COUNT(*) c FROM Equipment WHERE condition_status='Hỏng'")->fetch_assoc()['c'];
        $baoduong = $this->db->query("SELECT COUNT(*) c FROM Equipment WHERE condition_status='Đang bảo dưỡng'")->fetch_assoc()['c'];
        $can_bao  = $this->db->query("
            SELECT COUNT(*) c FROM Equipment e
            LEFT JOIN EquipmentType et ON et.type_id = e.type_id
            WHERE (e.last_maintenance_date IS NOT NULL OR e.purchase_date IS NOT NULL)
              AND DATE_ADD(COALESCE(e.last_maintenance_date, e.purchase_date, CURDATE()),
                  INTERVAL COALESCE(et.maintenance_interval, 180) DAY) <= CURDATE()
        ")->fetch_assoc()['c'];
        $tong_gia = $this->db->query("SELECT COALESCE(SUM(purchase_price), 0) s FROM Equipment")->fetch_assoc()['s'];
        $tong_bt  = $this->db->query("SELECT COALESCE(SUM(cost), 0) s FROM EquipmentMaintenance")->fetch_assoc()['s'];

        return [
            'total'       => (int)$total,
            'hoat_dong'   => (int)$hoatdong,
            'hong'        => (int)$hong,
            'bao_duong'   => (int)$baoduong,
            'can_bao_tri' => (int)$can_bao,
            'tong_gia'    => (float)$tong_gia,
            'tong_bt'     => (float)$tong_bt
        ];
    }

    public function getCategories(): array
    {
        return $this->db->query("SELECT * FROM EquipmentType ORDER BY type_name")->fetch_all(MYSQLI_ASSOC);
    }

    public function getRooms(): array
    {
        return $this->db->query("SELECT room_id, room_name, status FROM GymRoom ORDER BY room_name")->fetch_all(MYSQLI_ASSOC);
    }

    public function getDevicesList(int $page, int $limit, string $search, string $loai, string $status, string $alert): array
    {
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

        $cs = $this->db->prepare("SELECT COUNT(*) total FROM Equipment e LEFT JOIN EquipmentType et ON et.type_id = e.type_id $ws");
        if ($p) $cs->bind_param($t, ...$p);
        $cs->execute();
        $total = $cs->get_result()->fetch_assoc()['total'];
        $cs->close();

        $ds = $this->db->prepare("
            SELECT e.*, et.type_name, et.maintenance_interval, gr.room_name,
                   DATEDIFF(
                       DATE_ADD(COALESCE(e.last_maintenance_date, e.purchase_date, CURDATE()),
                           INTERVAL COALESCE(et.maintenance_interval, 180) DAY),
                       CURDATE()
                   ) AS days_remaining
            FROM Equipment e
            LEFT JOIN EquipmentType et ON et.type_id = e.type_id
            LEFT JOIN GymRoom gr ON gr.room_id = e.room_id
            $ws
            ORDER BY e.equipment_id DESC
            LIMIT ? OFFSET ?
        ");
        $dp = $p; $dt = $t; $dp[] = $limit; $dp[] = $offset; $dt .= 'ii';
        $ds->bind_param($dt, ...$dp);
        $ds->execute();
        $rows = $ds->get_result()->fetch_all(MYSQLI_ASSOC);
        $ds->close();

        return [
            'data'       => $rows,
            'total'      => (int)$total,
            'totalPages' => max(1, ceil($total / $limit))
        ];
    }

    public function getDeviceDetail(int $id): ?array
    {
        $s  = $this->db->prepare("
            SELECT e.*, et.type_name, et.maintenance_interval, gr.room_name,
                   DATEDIFF(
                       DATE_ADD(COALESCE(e.last_maintenance_date, e.purchase_date, CURDATE()),
                           INTERVAL COALESCE(et.maintenance_interval, 180) DAY),
                       CURDATE()
                   ) AS days_remaining
            FROM Equipment e
            LEFT JOIN EquipmentType et ON et.type_id = e.type_id
            LEFT JOIN GymRoom gr ON gr.room_id = e.room_id
            WHERE e.equipment_id = ?
        ");
        $s->bind_param('i', $id); $s->execute();
        $dev = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$dev) return null;

        $s2 = $this->db->prepare("SELECT * FROM EquipmentMaintenance WHERE equipment_id = ? ORDER BY maintenance_date DESC LIMIT 5");
        $s2->bind_param('i', $id); $s2->execute();
        $hist = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
        $s2->close();

        return ['device' => $dev, 'history' => $hist];
    }

    public function resolveTypeIdByName(string $name): ?int
    {
        $stmt = $this->db->prepare("SELECT type_id FROM EquipmentType WHERE type_name = ? LIMIT 1");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['type_id'] : null;
    }

    public function resolveRoomIdByName(string $name): ?int
    {
        $stmt = $this->db->prepare("SELECT room_id FROM GymRoom WHERE room_name = ? LIMIT 1");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['room_id'] : null;
    }

    public function addDevice(string $ten, ?int $type_id, string $condition, ?float $gia, string $ngay_mua, ?string $ngay_bao, ?string $mo_ta, ?int $room_id): int|false
    {
        $s = $this->db->prepare("
            INSERT INTO Equipment (equipment_name, type_id, condition_status, purchase_price, purchase_date, last_maintenance_date, description, room_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $s->bind_param('sisssssi', $ten, $type_id, $condition, $gia, $ngay_mua, $ngay_bao, $mo_ta, $room_id);
        $ok = $s->execute();
        $id = $ok ? (int)$this->db->insert_id : false;
        $s->close();
        return $id;
    }

    public function updateDevice(int $id, string $ten, ?int $type_id, string $condition, ?float $gia, string $ngay_mua, ?string $ngay_bao, ?string $mo_ta, ?int $room_id): bool
    {
        $s = $this->db->prepare("
            UPDATE Equipment
            SET equipment_name=?, type_id=?, condition_status=?, purchase_price=?,
                purchase_date=?, last_maintenance_date=?, description=?, room_id=?
            WHERE equipment_id=?
        ");
        $s->bind_param('sisssssii', $ten, $type_id, $condition, $gia, $ngay_mua, $ngay_bao, $mo_ta, $room_id, $id);
        $ok = $s->execute();
        $s->close();
        return $ok;
    }

    public function deleteDevice(int $id): bool
    {
        $this->db->query("DELETE FROM EquipmentMaintenance WHERE equipment_id = $id");
        $s = $this->db->prepare("DELETE FROM Equipment WHERE equipment_id = ?");
        $s->bind_param('i', $id);
        $ok = $s->execute();
        $s->close();
        return $ok;
    }

    public function getMaintenanceList(int $page, int $limit, int $dev_id): array
    {
        $offset = ($page - 1) * $limit;
        $w      = $dev_id ? "WHERE em.equipment_id = $dev_id" : '';
        $total  = $this->db->query("SELECT COUNT(*) c FROM EquipmentMaintenance em $w")->fetch_assoc()['c'];

        $s = $this->db->prepare("
            SELECT em.*, e.equipment_name
            FROM EquipmentMaintenance em
            JOIN Equipment e ON e.equipment_id = em.equipment_id
            $w
            ORDER BY em.maintenance_date DESC
            LIMIT ? OFFSET ?
        ");
        $s->bind_param('ii', $limit, $offset); $s->execute();
        $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();

        return [
            'data'       => $rows,
            'total'      => (int)$total,
            'totalPages' => max(1, ceil($total / $limit))
        ];
    }

    public function addMaintenance(int $dev_id, string $ngay, ?string $desc, float $cost, ?string $nguoi, string $status): bool
    {
        $s = $this->db->prepare("
            INSERT INTO EquipmentMaintenance (equipment_id, maintenance_date, description, cost, performed_by, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $s->bind_param('issdss', $dev_id, $ngay, $desc, $cost, $nguoi, $status);
        $ok = $s->execute();
        $s->close();
        if ($ok && $status === 'Completed') {
            $this->db->query("UPDATE Equipment SET last_maintenance_date = '$ngay' WHERE equipment_id = $dev_id");
        }
        return $ok;
    }

    public function updateMaintenance(int $id, string $ngay, ?string $desc, float $cost, ?string $nguoi, string $status): bool
    {
        $s = $this->db->prepare("UPDATE EquipmentMaintenance SET maintenance_date=?, description=?, cost=?, performed_by=?, status=? WHERE maintenance_id=?");
        $s->bind_param('ssdssi', $ngay, $desc, $cost, $nguoi, $status, $id);
        $ok = $s->execute();
        $s->close();
        if ($ok && $status === 'Completed') {
            $row = $this->db->query("SELECT equipment_id FROM EquipmentMaintenance WHERE maintenance_id=$id")->fetch_assoc();
            if ($row) $this->db->query("UPDATE Equipment SET last_maintenance_date='$ngay' WHERE equipment_id={$row['equipment_id']}");
        }
        return $ok;
    }

    public function getMaintenanceDetail(int $id): ?array
    {
        $s  = $this->db->prepare("SELECT em.*, e.equipment_name FROM EquipmentMaintenance em JOIN Equipment e ON e.equipment_id=em.equipment_id WHERE em.maintenance_id=?");
        $s->bind_param('i', $id); $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        return $row ?: null;
    }

    public function deleteMaintenance(int $id): bool
    {
        $s  = $this->db->prepare("DELETE FROM EquipmentMaintenance WHERE maintenance_id = ?");
        $s->bind_param('i', $id);
        $ok = $s->execute();
        $s->close();
        return $ok;
    }

    public function getAllDevicesList(): array
    {
        return $this->db->query("SELECT equipment_id, equipment_name FROM Equipment ORDER BY equipment_name")->fetch_all(MYSQLI_ASSOC);
    }

    public function getTrainerRoomDevices(int $trainer_id, int $page, int $limit): ?array
    {
        $today = date('Y-m-d');
        $roomRes = $this->db->prepare(
            "SELECT tc.room_id, gr.room_name
             FROM TrainingClass tc
             LEFT JOIN GymRoom gr ON gr.room_id = tc.room_id
             WHERE tc.trainer_id = ? AND DATE(tc.start_time) = ?
               AND tc.room_id IS NOT NULL
             LIMIT 1"
        );
        $roomRes->bind_param('is', $trainer_id, $today);
        $roomRes->execute();
        $roomRow = $roomRes->get_result()->fetch_assoc();
        $roomRes->close();

        if (!$roomRow) return null;

        $room_id   = (int)$roomRow['room_id'];
        $room_name = $roomRow['room_name'];
        $offset = ($page - 1) * $limit;

        $total = (int)$this->db->query("SELECT COUNT(*) c FROM Equipment WHERE room_id = $room_id")->fetch_assoc()['c'];

        $s = $this->db->prepare("
            SELECT e.*, et.type_name, et.maintenance_interval, gr.room_name,
                   DATEDIFF(
                       DATE_ADD(COALESCE(e.last_maintenance_date, e.purchase_date, CURDATE()),
                           INTERVAL COALESCE(et.maintenance_interval, 180) DAY),
                       CURDATE()
                   ) AS days_remaining
            FROM Equipment e
            LEFT JOIN EquipmentType et ON et.type_id = e.type_id
            LEFT JOIN GymRoom gr       ON gr.room_id  = e.room_id
            WHERE e.room_id = ?
            ORDER BY e.equipment_id DESC
            LIMIT ? OFFSET ?
        ");
        $s->bind_param('iii', $room_id, $limit, $offset);
        $s->execute();
        $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();

        return [
            'data'       => $rows,
            'total'      => $total,
            'totalPages' => max(1, ceil($total / $limit)),
            'room_id'    => $room_id,
            'room_name'  => $room_name,
        ];
    }
}
