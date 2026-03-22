<?php
require_once '../../../Database/db.php';
header('Content-Type: application/json');
// Bypass ngrok browser warning page
header('ngrok-skip-browser-warning: true');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ========================
    // GET CUSTOMER LIST
    // ========================
    case 'get_customers':
        $page   = max(1, intval($_GET['page']   ?? 1));
        $limit  = intval($_GET['limit']  ?? 15);
        $search = trim($_GET['search']   ?? '');
        $gender = $_GET['gender']        ?? '';
        $status = $_GET['status']        ?? '';  // active | expired | expiring | none
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

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM ($sql) as sub";
        $stmt = $conn->prepare($countSql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];

        // Paginated data
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $conn->prepare($sql);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result    = $stmt->get_result();
        $customers = [];
        while ($row = $result->fetch_assoc()) $customers[] = $row;

        echo json_encode([
            'success'    => true,
            'data'       => $customers,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => ceil($total / $limit)
        ]);
        break;

    // ========================
    // ADD CUSTOMER
    // ========================
    case 'add_customer':
        $full_name    = trim($_POST['full_name']    ?? '');
        $date_of_birth= $_POST['date_of_birth']     ?? null;
        $gender       = $_POST['gender']            ?? null;
        $phone        = trim($_POST['phone']        ?? '');
        $email        = trim($_POST['email']        ?? '');
        $address      = trim($_POST['address']      ?? '');
        $username     = trim($_POST['username']     ?? '');
        $password_raw = trim($_POST['password']     ?? '');

        if ($full_name === '') {
            echo json_encode(['success' => false, 'message' => 'Full name is required']); exit;
        }
        if ($username === '') {
            echo json_encode(['success' => false, 'message' => 'Username is required when adding a new customer']); exit;
        }
        if (strlen($username) < 3) {
            echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters']); exit;
        }

        // Check duplicate phone
        if ($phone) {
            $chk = $conn->prepare("SELECT customer_id FROM Customer WHERE phone = ?");
            $chk->bind_param('s', $phone); $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Phone number already registered']); exit;
            }
        }

        // Check duplicate username
        $chk = $conn->prepare("SELECT account_id FROM Account WHERE username = ?");
        $chk->bind_param('s', $username); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']); exit;
        }

        $raw_pass = ($password_raw !== '') ? $password_raw : 'elitegym@2025';
        $hashed   = password_hash($raw_pass, PASSWORD_BCRYPT);

        $date_of_birth_val = $date_of_birth ?: null;
        $gender_val        = $gender        ?: null;
        $phone_val         = $phone         ?: null;
        $email_val         = $email         ?: null;
        $address_val       = $address       ?: null;

        $conn->begin_transaction();
        try {
            // Create Account with role Customer
            $stmt = $conn->prepare("INSERT INTO Account (username, password, role) VALUES (?, ?, 'Customer')");
            $stmt->bind_param('ss', $username, $hashed);
            if (!$stmt->execute()) throw new Exception('Account creation failed: ' . $conn->error);
            $account_id = $conn->insert_id;

            // Create Customer linked to Account
            $stmt = $conn->prepare("
                INSERT INTO Customer (full_name, date_of_birth, gender, phone, email, address, registered_at, account_id)
                VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)
            ");
            $stmt->bind_param('ssssssi',
                $full_name, $date_of_birth_val, $gender_val,
                $phone_val, $email_val, $address_val, $account_id
            );
            if (!$stmt->execute()) throw new Exception('Customer creation failed: ' . $conn->error);
            $customer_id = $conn->insert_id;

            $conn->commit();
            $pw_msg = ($password_raw !== '') ? '' : ' | Default password: elitegym@2025';
            echo json_encode([
                'success' => true,
                'message' => "Customer added successfully. Account: $username$pw_msg",
                'id'      => $customer_id
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    // ========================
    // UPDATE CUSTOMER
    // ========================
    case 'update_customer':
        $id            = intval($_POST['id']            ?? 0);
        $full_name     = trim($_POST['full_name']       ?? '');
        $date_of_birth = $_POST['date_of_birth']        ?? null;
        $gender        = $_POST['gender']               ?? null;
        $phone         = trim($_POST['phone']           ?? '');
        $email         = trim($_POST['email']           ?? '');
        $address       = trim($_POST['address']         ?? '');

        if ($id === 0 || $full_name === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid data']); exit;
        }

        // Check duplicate phone (excluding self)
        if ($phone) {
            $chk = $conn->prepare("SELECT customer_id FROM Customer WHERE phone = ? AND customer_id != ?");
            $chk->bind_param('si', $phone, $id); $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'Phone number already registered']); exit;
            }
        }

        $date_of_birth_val = $date_of_birth ?: null;
        $gender_val        = $gender        ?: null;
        $phone_val         = $phone         ?: null;
        $email_val         = $email         ?: null;
        $address_val       = $address       ?: null;

        $stmt = $conn->prepare("
            UPDATE Customer
            SET full_name=?, date_of_birth=?, gender=?, phone=?, email=?, address=?
            WHERE customer_id=?
        ");
        $stmt->bind_param('ssssssi',
            $full_name, $date_of_birth_val, $gender_val,
            $phone_val, $email_val, $address_val, $id
        );
        echo json_encode($stmt->execute()
            ? ['success' => true,  'message' => 'Customer updated successfully']
            : ['success' => false, 'message' => 'Error: ' . $conn->error]);
        break;

    // ========================
    // DELETE CUSTOMER
    // ========================
    case 'delete_customer':
        $id = intval($_POST['id'] ?? 0);
        if ($id === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit;
        }

        $checks = [
            "SELECT COUNT(*) as c FROM Invoice             WHERE customer_id = $id",
            "SELECT COUNT(*) as c FROM MembershipRegistration WHERE customer_id = $id",
            "SELECT COUNT(*) as c FROM GymCheckIn          WHERE customer_id = $id",
        ];
        foreach ($checks as $chk) {
            if ($conn->query($chk)->fetch_assoc()['c'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete: customer has related records (invoices, memberships, check-in history)']);
                exit;
            }
        }

        $stmt = $conn->prepare("DELETE FROM Customer WHERE customer_id = ?");
        $stmt->bind_param('i', $id);
        echo json_encode($stmt->execute()
            ? ['success' => true,  'message' => 'Customer deleted']
            : ['success' => false, 'message' => 'Error: ' . $conn->error]);
        break;

    // ========================
    // GET CUSTOMER DETAIL
    // ========================
    case 'get_detail':
        $id = intval($_GET['id'] ?? 0);
        if ($id === 0) { echo json_encode(['success' => false]); exit; }

        $stmt = $conn->prepare("SELECT * FROM Customer WHERE customer_id = ?");
        $stmt->bind_param('i', $id); $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        if (!$customer) { echo json_encode(['success' => false, 'message' => 'Customer not found']); exit; }

        // Membership packages
        $stmt = $conn->prepare("
            SELECT mr.*, mp.plan_name, mp.price
            FROM MembershipRegistration mr
            JOIN MembershipPlan mp ON mp.plan_id = mr.plan_id
            WHERE mr.customer_id = ?
            ORDER BY mr.start_date DESC
        ");
        $stmt->bind_param('i', $id); $stmt->execute();
        $packages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Invoices
        $stmt = $conn->prepare("
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

        // Check-in stats
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total_checkin, MAX(check_time) AS last_checkin
            FROM GymCheckIn WHERE customer_id = ? AND type = 'checkin'
        ");
        $stmt->bind_param('i', $id); $stmt->execute();
        $checkin = $stmt->get_result()->fetch_assoc();

        echo json_encode([
            'success'  => true,
            'customer' => $customer,
            'packages' => $packages,
            'invoices' => $invoices,
            'checkin'  => $checkin
        ]);
        break;

    // ========================
    // STATS
    // ========================
    case 'get_stats':
        $total     = $conn->query("SELECT COUNT(*) AS c FROM Customer")->fetch_assoc()['c'];
        $new_month = $conn->query("SELECT COUNT(*) AS c FROM Customer WHERE MONTH(registered_at)=MONTH(CURDATE()) AND YEAR(registered_at)=YEAR(CURDATE())")->fetch_assoc()['c'];
        $active    = $conn->query("SELECT COUNT(DISTINCT customer_id) AS c FROM MembershipRegistration WHERE end_date >= CURDATE()")->fetch_assoc()['c'];
        $expiring  = $conn->query("SELECT COUNT(DISTINCT customer_id) AS c FROM MembershipRegistration WHERE end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
        echo json_encode(['success' => true, 'total' => $total, 'new_month' => $new_month, 'active' => $active, 'expiring' => $expiring]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
