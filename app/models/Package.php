<?php

class Package extends Model
{
    public function getPackageTypes(): array
    {
        $res = $this->db->query("SELECT type_id, type_name, color_code FROM PackageType WHERE is_active=1 ORDER BY sort_order ASC, type_id ASC");
        $types = [];
        if ($res) while ($r = $res->fetch_assoc()) $types[] = $r;
        return $types;
    }

    public function getStats(): array
    {
        $total = $this->db->query("SELECT COUNT(*) as c FROM MembershipPlan")->fetch_assoc()['c'];
        $active_subscribers = $this->db->query("SELECT COUNT(DISTINCT customer_id) as c FROM MembershipRegistration WHERE end_date >= CURDATE()")->fetch_assoc()['c'];
        $popularRow = $this->db->query("SELECT mp.plan_name, COUNT(mr.registration_id) as cnt FROM MembershipPlan mp LEFT JOIN MembershipRegistration mr ON mr.plan_id = mp.plan_id GROUP BY mp.plan_id ORDER BY cnt DESC LIMIT 1")->fetch_assoc();
        $avg_price = $this->db->query("SELECT AVG(price) as avg FROM MembershipPlan")->fetch_assoc()['avg'];
        return [
            'total'              => (int)$total,
            'active_subscribers' => (int)$active_subscribers,
            'popular_name'       => $popularRow['plan_name'] ?? null,
            'avg_price'          => $avg_price ? (int)round($avg_price) : 0
        ];
    }

    public function getPackagesList(int $page, int $limit, string $search, int $duration, string $sort): array
    {
        $offset = ($page - 1) * $limit;
        $where  = ['1=1']; $params = []; $types = '';

        if ($search !== '') {
            $where[] = "(mp.plan_name LIKE ? OR mp.description LIKE ?)";
            $s = "%$search%"; $params[] = $s; $params[] = $s; $types .= 'ss';
        }
        if ($duration > 0) { $where[] = "mp.duration_months = ?"; $params[] = $duration; $types .= 'i'; }

        $whereStr = implode(' AND ', $where);
        $orderBy = match($sort) {
            'price_asc'        => 'mp.price ASC',
            'price_desc'       => 'mp.price DESC',
            'duration_asc'     => 'mp.duration_months ASC',
            'subscribers_desc' => 'total_subscribers DESC',
            default            => 'mp.plan_id DESC'
        };

        $sql = "SELECT mp.plan_id, mp.plan_name, mp.duration_months, mp.price, mp.description, mp.image_url, mp.package_type_id,
                    pt.type_name AS package_type_name, pt.color_code AS package_type_color,
                    COUNT(DISTINCT mr.registration_id) AS total_subscribers,
                    COUNT(DISTINCT CASE WHEN mr.end_date >= CURDATE() THEN mr.registration_id END) AS active_subscribers
                FROM MembershipPlan mp
                LEFT JOIN PackageType pt ON pt.type_id = mp.package_type_id
                LEFT JOIN MembershipRegistration mr ON mr.plan_id = mp.plan_id
                WHERE $whereStr
                GROUP BY mp.plan_id, mp.plan_name, mp.duration_months, mp.price, mp.description, mp.image_url, mp.package_type_id, pt.type_name, pt.color_code
                ORDER BY $orderBy";

        $countSql = "SELECT COUNT(*) as total FROM ($sql) as sub";
        $stmt = $this->db->prepare($countSql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit; $params[] = $offset; $types .= 'ii';
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $packages = [];
        while ($row = $result->fetch_assoc()) $packages[] = $row;
        $stmt->close();

        return ['data' => $packages, 'total' => (int)$total, 'totalPages' => max(1, ceil($total / $limit))];
    }

    public function nameExists(string $name, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $s = $this->db->prepare("SELECT plan_id FROM MembershipPlan WHERE plan_name = ? AND plan_id != ? LIMIT 1");
            $s->bind_param('si', $name, $excludeId);
        } else {
            $s = $this->db->prepare("SELECT plan_id FROM MembershipPlan WHERE plan_name = ? LIMIT 1");
            $s->bind_param('s', $name);
        }
        $s->execute(); $exists = $s->get_result()->num_rows > 0; $s->close();
        return $exists;
    }

    public function getOldImage(int $id): ?string
    {
        $r = $this->db->query("SELECT image_url FROM MembershipPlan WHERE plan_id = $id")->fetch_assoc();
        return $r['image_url'] ?? null;
    }

    public function addPackage(string $name, int $duration, float $price, ?string $desc, ?string $imageUrl, ?int $packageTypeId): int|false
    {
        $stmt = $this->db->prepare("INSERT INTO MembershipPlan (plan_name, duration_months, price, description, image_url, package_type_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sidssi', $name, $duration, $price, $desc, $imageUrl, $packageTypeId);
        $ok = $stmt->execute(); $id = $ok ? (int)$this->db->insert_id : false; $stmt->close();
        return $id;
    }

    public function updatePackage(int $id, string $name, int $duration, float $price, ?string $desc, ?string $imageUrl, ?int $packageTypeId): bool
    {
        $stmt = $this->db->prepare("UPDATE MembershipPlan SET plan_name=?, duration_months=?, price=?, description=?, image_url=?, package_type_id=? WHERE plan_id=?");
        $stmt->bind_param('sidssii', $name, $duration, $price, $desc, $imageUrl, $packageTypeId, $id);
        $ok = $stmt->execute(); $stmt->close();
        return $ok;
    }

    public function deletePackage(int $id): array
    {
        $checks = [
            ["SELECT COUNT(*) as c FROM MembershipRegistration WHERE plan_id = $id", 'membership registrations'],
            ["SELECT COUNT(*) as c FROM InvoiceDetail WHERE plan_id = $id", 'invoices'],
        ];
        foreach ($checks as [$chk, $label]) {
            if ($this->db->query($chk)->fetch_assoc()['c'] > 0) {
                return ['success' => false, 'message' => "Cannot delete: plan has related data ($label)"];
            }
        }
        $old = $this->db->query("SELECT image_url FROM MembershipPlan WHERE plan_id = $id")->fetch_assoc();
        $stmt = $this->db->prepare("DELETE FROM MembershipPlan WHERE plan_id = ?");
        $stmt->bind_param('i', $id); $ok = $stmt->execute(); $stmt->close();
        if ($ok && !empty($old['image_url'])) {
            $oldFile = PKG_UPLOAD_DIR . basename($old['image_url']);
            if (file_exists($oldFile)) @unlink($oldFile);
        }
        return $ok ? ['success' => true, 'message' => 'Đã xóa gói tập'] : ['success' => false, 'message' => $this->db->error];
    }

    public function getDetail(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT plan_id, plan_name, duration_months, price, description, image_url FROM MembershipPlan WHERE plan_id = ?");
        $stmt->bind_param('i', $id); $stmt->execute();
        $package = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$package) return null;

        $stmt = $this->db->prepare("SELECT COUNT(*) as total_subscribers, COUNT(CASE WHEN end_date >= CURDATE() THEN 1 END) as active_subscribers, MAX(start_date) as last_registered FROM MembershipRegistration WHERE plan_id = ?");
        $stmt->bind_param('i', $id); $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc(); $stmt->close();

        $stmt = $this->db->prepare("SELECT COALESCE(SUM(id_tbl.unit_price * id_tbl.quantity), 0) as total_revenue FROM InvoiceDetail id_tbl WHERE id_tbl.plan_id = ?");
        $stmt->bind_param('i', $id); $stmt->execute();
        $revenue = $stmt->get_result()->fetch_assoc(); $stmt->close();

        $stmt = $this->db->prepare("SELECT c.full_name, c.phone, mr.start_date, mr.end_date FROM MembershipRegistration mr JOIN Customer c ON c.customer_id = mr.customer_id WHERE mr.plan_id = ? ORDER BY mr.start_date DESC LIMIT 20");
        $stmt->bind_param('i', $id); $stmt->execute();
        $subscribers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

        return [
            'package'            => $package,
            'total_subscribers'  => $stats['total_subscribers'],
            'active_subscribers' => $stats['active_subscribers'],
            'last_registered'    => $stats['last_registered'],
            'total_revenue'      => $revenue['total_revenue'],
            'subscribers'        => $subscribers
        ];
    }
}
