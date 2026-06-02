<?php

class Customer extends Model
{
    public function findIdByAccount(int $accountId): int
    {
        $stmt = $this->db->prepare('SELECT customer_id FROM Customer WHERE account_id = ? LIMIT 1');
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['customer_id'] ?? 0);
    }

    public function getByAccountId(int $accountId): ?array
    {
        $stmt = $this->db->prepare("SELECT full_name, email, phone FROM Customer WHERE account_id = ? LIMIT 1");
        $stmt->bind_param("i", $accountId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT customer_id FROM Customer WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function phoneExists(string $phone, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            $stmt = $this->db->prepare("SELECT customer_id FROM Customer WHERE phone = ? AND customer_id != ? LIMIT 1");
            $stmt->bind_param("si", $phone, $excludeId);
        } else {
            $stmt = $this->db->prepare("SELECT customer_id FROM Customer WHERE phone = ? LIMIT 1");
            $stmt->bind_param("s", $phone);
        }
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function getCustomerStats(): array
    {
        $total     = $this->db->query("SELECT COUNT(*) AS c FROM Customer")->fetch_assoc()['c'];
        $new_month = $this->db->query("SELECT COUNT(*) AS c FROM Customer WHERE MONTH(registered_at)=MONTH(CURDATE()) AND YEAR(registered_at)=YEAR(CURDATE())")->fetch_assoc()['c'];
        $active    = $this->db->query("SELECT COUNT(DISTINCT customer_id) AS c FROM MembershipRegistration WHERE end_date >= CURDATE()")->fetch_assoc()['c'];
        $expiring  = $this->db->query("SELECT COUNT(DISTINCT customer_id) AS c FROM MembershipRegistration WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
        
        return [
            'total'     => (int)$total,
            'new_month' => (int)$new_month,
            'active'    => (int)$active,
            'expiring'  => (int)$expiring,
        ];
    }

    public function getCustomersList(int $page, int $limit, string $search, string $gender, string $status): array
    {
        $offset = ($page - 1) * $limit;
        $where  = ['1=1'];
        $params = [];
        $types  = '';

        if ($search !== '') {
            $where[] = "(c.full_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
            $s = "%$search%";
            $params[] = $s; $params[] = $s; $params[] = $s;
            $types .= 'sss';
        }

        if ($gender !== '') {
            $where[] = "c.gender = ?";
            $params[] = $gender;
            $types .= 's';
        }

        $whereStr = implode(' AND ', $where);

        $statusWhere = '';
        if ($status !== '') {
            switch ($status) {
                case 'active':   $statusWhere = "AND pkg_status = 'active'";   break;
                case 'expired':  $statusWhere = "AND pkg_status = 'expired'";  break;
                case 'expiring': $statusWhere = "AND pkg_status = 'expiring'"; break;
                case 'none':     $statusWhere = "AND pkg_status = 'none'";     break;
            }
        }

        $sql = "
            SELECT
                c.customer_id,
                c.full_name,
                c.date_of_birth,
                c.gender,
                c.phone,
                c.email,
                c.address,
                c.registered_at,
                COALESCE(
                    (SELECT
                        CASE
                            WHEN MAX(end_date) >= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'active'
                            WHEN MAX(end_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'expiring'
                            WHEN MAX(end_date) < CURDATE() THEN 'expired'
                            ELSE 'none'
                        END
                    FROM MembershipRegistration WHERE customer_id = c.customer_id),
                'none') AS pkg_status,
                (SELECT mp.plan_name
                 FROM MembershipRegistration mr
                 JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
                 WHERE mr.customer_id = c.customer_id
                 ORDER BY mr.end_date DESC LIMIT 1) AS pkg_name,
                (SELECT MAX(end_date)
                 FROM MembershipRegistration WHERE customer_id = c.customer_id) AS pkg_end
            FROM Customer c
            WHERE $whereStr
            HAVING 1=1 $statusWhere
            ORDER BY c.customer_id DESC
        ";

        $countSql = "SELECT COUNT(*) as total FROM ($sql) as sub";
        $stmt = $this->db->prepare($countSql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->db->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $customers = [];
        while ($row = $result->fetch_assoc()) $customers[] = $row;
        $stmt->close();

        return [
            'data'       => $customers,
            'total'      => (int)$total,
            'totalPages' => (int)max(1, ceil($total / $limit)),
        ];
    }

    public function getCustomerDetail(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM Customer WHERE customer_id = ?");
        $stmt->bind_param('i', $id); $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$customer) return null;

        $stmt = $this->db->prepare("
            SELECT mr.*, mp.plan_name, mp.price
            FROM MembershipRegistration mr
            JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
            WHERE mr.customer_id = ?
            ORDER BY mr.start_date DESC
        ");
        $stmt->bind_param('i', $id); $stmt->execute();
        $packages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $this->db->prepare("
            SELECT inv.invoice_id, inv.invoice_date, inv.final_amount,
                   GROUP_CONCAT(mp.plan_name SEPARATOR ', ') AS plan_names
            FROM Invoice inv
            LEFT JOIN InvoiceDetail id2 ON id2.invoice_id = inv.invoice_id
            LEFT JOIN MembershipPlan mp ON mp.plan_id    = id2.plan_id
            WHERE inv.customer_id = ?
            GROUP BY inv.invoice_id
            ORDER BY inv.invoice_date DESC LIMIT 10
        ");
        $stmt->bind_param('i', $id); $stmt->execute();
        $invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total_checkin, MAX(check_time) AS last_checkin
            FROM GymCheckIn WHERE customer_id = ? AND type = 'checkin'
        ");
        $stmt->bind_param('i', $id); $stmt->execute();
        $checkin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'customer' => $customer,
            'packages' => $packages,
            'invoices' => $invoices,
            'checkin'  => $checkin
        ];
    }

    public function createCustomer(string $fullName, string $email, string $phone, string $registeredAt, int $accountId): int|false
    {
        $stmt = $this->db->prepare("
            INSERT INTO Customer (full_name, email, phone, registered_at, account_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssi", $fullName, $email, $phone, $registeredAt, $accountId);
        $ok = $stmt->execute();
        $id = $ok ? (int)$this->db->insert_id : false;
        $stmt->close();
        return $id;
    }

    public function createCustomerAdmin(string $fullName, ?string $dob, ?string $gender, ?string $phone, ?string $email, ?string $address, int $accountId): int|false
    {
        $stmt = $this->db->prepare("
            INSERT INTO Customer (full_name, date_of_birth, gender, phone, email, address, registered_at, account_id)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)
        ");
        $stmt->bind_param("ssssssi", $fullName, $dob, $gender, $phone, $email, $address, $accountId);
        $ok = $stmt->execute();
        $id = $ok ? (int)$this->db->insert_id : false;
        $stmt->close();
        return $id;
    }

    public function updateCustomer(int $id, string $fullName, ?string $dob, ?string $gender, ?string $phone, ?string $email, ?string $address): bool
    {
        $stmt = $this->db->prepare("
            UPDATE Customer
            SET full_name=?, date_of_birth=?, gender=?, phone=?, email=?, address=?
            WHERE customer_id=?
        ");
        $stmt->bind_param('ssssssi', $fullName, $dob, $gender, $phone, $email, $address, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteCustomer(int $id): array
    {
        $checks = [
            "SELECT COUNT(*) as c FROM Invoice             WHERE customer_id = $id",
            "SELECT COUNT(*) as c FROM MembershipRegistration WHERE customer_id = $id",
            "SELECT COUNT(*) as c FROM GymCheckIn          WHERE customer_id = $id",
        ];
        foreach ($checks as $chk) {
            if ($this->db->query($chk)->fetch_assoc()['c'] > 0) {
                return ['success' => false, 'message' => 'Cannot delete: customer has related records (invoices, memberships, check-in history)'];
            }
        }

        $stmt = $this->db->prepare("DELETE FROM Customer WHERE customer_id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $err = $this->db->error;
        $stmt->close();
        return $ok
            ? ['success' => true,  'message' => 'Customer deleted']
            : ['success' => false, 'message' => 'Error: ' . $err];
    }
}
