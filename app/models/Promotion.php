<?php

class Promotion extends Model
{
    public function autoExpire(): void
    {
        $this->db->query("UPDATE Promotion SET status = 'Expired' WHERE status = 'Active' AND end_date < CURDATE() AND end_date NOT IN ('0000-00-00') AND end_date IS NOT NULL");
    }

    public function getStats(): array
    {
        $today      = date('Y-m-d');
        $total      = (int)$this->db->query("SELECT COUNT(*) as c FROM Promotion")->fetch_assoc()['c'];
        $active     = (int)$this->db->query("SELECT COUNT(*) as c FROM Promotion WHERE status = 'Active' AND end_date >= '$today'")->fetch_assoc()['c'];
        $expired    = (int)$this->db->query("SELECT COUNT(*) as c FROM Promotion WHERE status = 'Expired' OR end_date < '$today'")->fetch_assoc()['c'];
        $total_used = (int)$this->db->query("SELECT COALESCE(SUM(usage_count), 0) as c FROM Promotion")->fetch_assoc()['c'];
        $avg_res    = $this->db->query("SELECT ROUND(AVG(discount_percent), 1) as avg_d FROM Promotion");
        $avg_discount = $avg_res ? ($avg_res->fetch_assoc()['avg_d'] ?? 0) : 0;
        return compact('total', 'active', 'expired', 'total_used', 'avg_discount');
    }

    public function getPromosList(int $page, int $limit, string $search, string $status, string $sort): array
    {
        $offset  = ($page - 1) * $limit;
        $where   = ['1=1']; $params = []; $types = '';

        if ($search !== '') {
            $where[] = "(promotion_name LIKE ? OR description LIKE ?)";
            $s = "%$search%"; $params[] = $s; $params[] = $s; $types .= 'ss';
        }
        if ($status !== '') { $where[] = "status = ?"; $params[] = $status; $types .= 's'; }

        $whereStr = implode(' AND ', $where);
        $orderBy  = match($sort) {
            'id_asc'        => 'promotion_id ASC',
            'discount_desc' => 'discount_percent DESC',
            'end_asc'       => 'end_date ASC',
            default         => 'promotion_id DESC'
        };

        $countSql = "SELECT COUNT(*) as total FROM Promotion WHERE $whereStr";
        $stmt = $this->db->prepare($countSql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $dp = $params; $dt = $types; $dp[] = $limit; $dp[] = $offset; $dt .= 'ii';
        $stmt = $this->db->prepare("SELECT * FROM Promotion WHERE $whereStr ORDER BY $orderBy LIMIT ? OFFSET ?");
        if (!empty($dp)) $stmt->bind_param($dt, ...$dp);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return ['data' => $rows, 'total' => (int)$total, 'totalPages' => max(1, ceil($total / $limit))];
    }

    public function getDetail(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM Promotion WHERE promotion_id = ?");
        $stmt->bind_param('i', $id); $stmt->execute();
        $promo = $stmt->get_result()->fetch_assoc(); $stmt->close();
        return $promo ?: null;
    }

    public function addPromo(array $data): int|false
    {
        $pn  = $this->db->real_escape_string($data['promotion_name']);
        $dp  = floatval($data['discount_percent']);
        $st  = $this->db->real_escape_string($data['status']);
        $mov = floatval($data['min_order_value']);
        $mda = $data['max_discount_amount'] !== null ? floatval($data['max_discount_amount']) : 'NULL';
        $mu  = $data['max_usage'] !== null ? intval($data['max_usage']) : 'NULL';
        $sd  = $this->db->real_escape_string($data['start_date']);
        $ed  = $this->db->real_escape_string($data['end_date']);
        $desc = $data['description'] !== null ? "'" . $this->db->real_escape_string($data['description']) . "'" : 'NULL';

        $sql = "INSERT INTO Promotion (promotion_name, discount_percent, status, min_order_value, max_discount_amount, max_usage, start_date, end_date, description)
                VALUES ('$pn', $dp, '$st', $mov, $mda, $mu, '$sd', '$ed', $desc)";
        if ($this->db->query($sql)) return (int)$this->db->insert_id;
        return false;
    }

    public function updatePromo(int $id, array $data): bool
    {
        $pn  = $this->db->real_escape_string($data['promotion_name']);
        $dp  = floatval($data['discount_percent']);
        $st  = $this->db->real_escape_string($data['status']);
        $mov = floatval($data['min_order_value']);
        $mda = $data['max_discount_amount'] !== null ? floatval($data['max_discount_amount']) : 'NULL';
        $mu  = $data['max_usage'] !== null ? intval($data['max_usage']) : 'NULL';
        $sd  = $this->db->real_escape_string($data['start_date']);
        $ed  = $this->db->real_escape_string($data['end_date']);
        $desc = $data['description'] !== null ? "'" . $this->db->real_escape_string($data['description']) . "'" : 'NULL';

        $sql = "UPDATE Promotion SET promotion_name='$pn', discount_percent=$dp, status='$st', min_order_value=$mov,
                    max_discount_amount=$mda, max_usage=$mu, start_date='$sd', end_date='$ed', description=$desc
                WHERE promotion_id = $id";
        return (bool)$this->db->query($sql);
    }

    public function deletePromo(int $id): array
    {
        $tableExists = $this->db->query("SHOW TABLES LIKE 'LichSuPromotion'")->num_rows > 0;
        if ($tableExists) {
            $chk = $this->db->query("SELECT COUNT(*) as c FROM LichSuPromotion WHERE promotion_id = $id");
            if ($chk && $chk->fetch_assoc()['c'] > 0)
                return ['success' => false, 'message' => 'Cannot delete: promotion has been used in history'];
        }
        $stmt = $this->db->prepare("DELETE FROM Promotion WHERE promotion_id = ?");
        $stmt->bind_param('i', $id); $ok = $stmt->execute(); $stmt->close();
        return $ok ? ['success' => true, 'message' => 'Promotion deleted'] : ['success' => false, 'message' => $this->db->error];
    }
}
