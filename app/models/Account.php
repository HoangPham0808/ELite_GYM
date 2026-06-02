<?php

class Account extends Model
{
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT account_id, username, password, role, is_active FROM Account WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function logLogin(int $accountId, string $ip, string $userAgent, string $result): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO LoginHistory (account_id, login_time, ip_address, user_agent, result)
                VALUES (?, NOW(), ?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param("isss", $accountId, $ip, $userAgent, $result);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log("Log login error: " . $e->getMessage());
        }
    }

    public function usernameExists(string $username): bool
    {
        $stmt = $this->db->prepare("SELECT account_id FROM Account WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function createAccount(string $username, string $password, string $role = 'Customer', int $isActive = 1): int|false
    {
        $stmt = $this->db->prepare("INSERT INTO Account (username, password, role, is_active) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $username, $password, $role, $isActive);
        $ok = $stmt->execute();
        $id = $ok ? (int)$this->db->insert_id : false;
        $stmt->close();
        return $id;
    }

    public function getAdminStats(): array
    {
        $total      = $this->db->query("SELECT COUNT(*) c FROM Account")->fetch_assoc()['c'];
        $active     = $this->db->query("SELECT COUNT(*) c FROM Account WHERE is_active=1")->fetch_assoc()['c'];
        $admin      = $this->db->query("SELECT COUNT(*) c FROM Account WHERE role='Admin'")->fetch_assoc()['c'];
        $employee   = $this->db->query("SELECT COUNT(*) c FROM Account WHERE role='Employee'")->fetch_assoc()['c'];
        $customer   = $this->db->query("SELECT COUNT(*) c FROM Account WHERE role='Customer'")->fetch_assoc()['c'];
        $today      = $this->db->query("SELECT COUNT(*) c FROM LoginHistory WHERE DATE(login_time)=CURDATE() AND result='Success'")->fetch_assoc()['c'];
        $fail_today = $this->db->query("SELECT COUNT(*) c FROM LoginHistory WHERE DATE(login_time)=CURDATE() AND result='Failed'")->fetch_assoc()['c'];
        
        return [
            'total'       => (int)$total,
            'active'      => (int)$active,
            'admin'       => (int)$admin,
            'employee'    => (int)$employee,
            'customer'    => (int)$customer,
            'today_login' => (int)$today,
            'fail_today'  => (int)$fail_today,
        ];
    }

    public function getAccountsList(int $page, int $limit, string $search, string $role, string $status): array
    {
        $offset = ($page - 1) * $limit;
        $w = []; $p = []; $t = '';
        
        if ($search !== '') {
            $w[] = "(a.username LIKE ? OR COALESCE(e.full_name, c.full_name) LIKE ?)";
            $p[] = "%$search%"; $p[] = "%$search%"; $t .= 'ss';
        }
        
        // SỬA LỖI: Nếu lọc tab Staff không truyền role cụ thể, lọc cả Admin và Employee ở Server luôn
        if ($role !== '') {
            if (strpos($role, ',') !== false) {
                $roles = explode(',', $role);
                $placeholders = implode(',', array_fill(0, count($roles), '?'));
                $w[] = "a.role IN ($placeholders)";
                foreach ($roles as $r) {
                    $p[] = $r;
                    $t .= 's';
                }
            } else {
                $w[] = "a.role=?";
                $p[] = $role;
                $t .= 's';
            }
        }
        
        if ($status !== '') { 
            $w[] = "a.is_active=?";  
            $p[] = (int)$status; 
            $t .= 'i'; 
        }
        
        $ws = $w ? 'WHERE ' . implode(' AND ', $w) : '';

        $base = "FROM Account a
                 LEFT JOIN Employee e ON e.account_id = a.account_id
                 LEFT JOIN Customer c ON c.account_id = a.account_id
                 $ws";

        $cs = $this->db->prepare("SELECT COUNT(*) total $base");
        if ($p) $cs->bind_param($t, ...$p);
        $cs->execute();
        $total = $cs->get_result()->fetch_assoc()['total'];
        $cs->close();

        $ds = $this->db->prepare("
            SELECT
                a.account_id, a.username, a.role, a.is_active, a.created_at, a.last_login,
                COALESCE(e.full_name, c.full_name) AS full_name,
                COALESCE(e.email,     c.email)     AS email,
                COALESCE(e.phone,     c.phone)     AS phone,
                (SELECT COUNT(*) FROM LoginHistory WHERE account_id=a.account_id AND result='Success') AS login_count,
                (SELECT COUNT(*) FROM LoginHistory WHERE account_id=a.account_id AND result='Failed'
                    AND DATE(login_time)=CURDATE()) AS fail_today
            $base ORDER BY a.account_id DESC LIMIT ? OFFSET ?");
            
        $dp = $p; $dt = $t; $dp[] = $limit; $dp[] = $offset; $dt .= 'ii';
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

    public function getAccountDetail(int $id): array
    {
        $s  = $this->db->prepare("
            SELECT a.*,
                COALESCE(e.full_name, c.full_name) AS full_name,
                COALESCE(e.email,     c.email)     AS email,
                COALESCE(e.phone,     c.phone)     AS phone,
                e.employee_id, c.customer_id
            FROM Account a
            LEFT JOIN Employee e ON e.account_id = a.account_id
            LEFT JOIN Customer c ON c.account_id = a.account_id
            WHERE a.account_id = ?");
        $s->bind_param('i', $id);
        $s->execute();
        $acc = $s->get_result()->fetch_assoc();
        $s->close();

        $s2 = $this->db->prepare("SELECT * FROM LoginHistory WHERE account_id=? ORDER BY login_time DESC LIMIT 10");
        $s2->bind_param('i', $id);
        $s2->execute();
        $hist = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
        $s2->close();

        return ['account' => $acc, 'history' => $hist];
    }

    public function updateAccount(int $id, string $role, int $isActive, string $password = ''): bool
    {
        if ($password !== '') {
            $s = $this->db->prepare("UPDATE Account SET role=?, is_active=?, password=? WHERE account_id=?");
            $s->bind_param('sisi', $role, $isActive, $password, $id);
        } else {
            $s = $this->db->prepare("UPDATE Account SET role=?, is_active=? WHERE account_id=?");
            $s->bind_param('sii', $role, $isActive, $id);
        }
        $ok = $s->execute();
        $s->close();
        return $ok;
    }

    public function toggleStatus(int $id): ?int
    {
        $stmt = $this->db->prepare("SELECT is_active FROM Account WHERE account_id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) return null;

        $newActive = $row['is_active'] ? 0 : 1;
        $stmt2 = $this->db->prepare("UPDATE Account SET is_active = ? WHERE account_id = ?");
        $stmt2->bind_param('ii', $newActive, $id);
        $stmt2->execute();
        $stmt2->close();

        return $newActive;
    }

    public function resetPassword(int $id, string $password): bool
    {
        $s = $this->db->prepare("UPDATE Account SET password=? WHERE account_id=?");
        $s->bind_param('si', $password, $id);
        $ok = $s->execute();
        $s->close();
        return $ok;
    }

    public function deleteAccount(int $id): array
    {
        $emp = $this->db->query("SELECT COUNT(*) c FROM Employee WHERE account_id=$id")->fetch_assoc()['c'];
        $cus = $this->db->query("SELECT COUNT(*) c FROM Customer WHERE account_id=$id")->fetch_assoc()['c'];
        if ($emp > 0 || $cus > 0) {
            return ['success' => false, 'message' => 'Cannot delete: account is linked to an employee/customer profile'];
        }
        $this->db->query("DELETE FROM LoginHistory WHERE account_id=$id");
        $s = $this->db->prepare("DELETE FROM Account WHERE account_id=?");
        $s->bind_param('i', $id);
        $ok = $s->execute();
        $err = $this->db->error;
        $s->close();
        return $ok
            ? ['success' => true, 'message' => 'Account deleted']
            : ['success' => false, 'message' => $err];
    }

    public function getLoginHistory(int $page, int $limit, string $search, string $result, string $date, int $accountId): array
    {
        $offset = ($page - 1) * $limit;
        $w = []; $p = []; $t = '';
        if ($search !== '') { $w[] = "a.username LIKE ?";       $p[] = "%$search%"; $t .= 's'; }
        if ($result !== '') { $w[] = "lh.result=?";             $p[] = $result;     $t .= 's'; }
        if ($date   !== '') { $w[] = "DATE(lh.login_time)=?";  $p[] = $date;       $t .= 's'; }
        if ($accountId > 0) { $w[] = "lh.account_id=?";        $p[] = $accountId;  $t .= 'i'; }
        $ws = $w ? 'WHERE ' . implode(' AND ', $w) : '';

        $base = "FROM LoginHistory lh
                 JOIN Account a ON a.account_id = lh.account_id
                 LEFT JOIN Employee e ON e.account_id = a.account_id
                 LEFT JOIN Customer c ON c.account_id = a.account_id
                 $ws";

        $cs = $this->db->prepare("SELECT COUNT(*) c $base");
        if ($p) $cs->bind_param($t, ...$p);
        $cs->execute();
        $total = $cs->get_result()->fetch_assoc()['c'];
        $cs->close();

        $ds = $this->db->prepare("
            SELECT lh.*, a.username, a.role,
                COALESCE(e.full_name, c.full_name) AS full_name
            $base ORDER BY lh.login_time DESC LIMIT ? OFFSET ?");
        $dp = $p; $dt = $t; $dp[] = $limit; $dp[] = $offset; $dt .= 'ii';
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

    public function clearLoginHistory(int $accountId): void
    {
        if ($accountId > 0) {
            $this->db->query("DELETE FROM LoginHistory WHERE account_id=$accountId");
        } else {
            $this->db->query("DELETE FROM LoginHistory WHERE login_time < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        }
    }
}